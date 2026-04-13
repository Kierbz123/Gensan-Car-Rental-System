<?php
// /var/www/html/gensan-car-rental-system/classes/User.php

/**
 * User Management Class
 * Handles authentication, authorization, and user operations
 */

class User
{
    private $db;
    private $userId;
    private $userData;
    private $permissions;

    public function __construct($userId = null)
    {
        $this->db = Database::getInstance();

        if ($userId) {
            $this->userId = $userId;
            $this->loadUserData();
        }
    }

    /**
     * Authenticate user login
     * 
     * @param string $username Username or email
     * @param string $password Plain text password
     * @param string $ipAddress Client IP
     * @param string $userAgent Client user agent
     * @return array|bool User data on success, false on failure
     */
    public function authenticate($username, $password, $ipAddress = null, $userAgent = null)
    {
        $ipAddress = $ipAddress ?? getClientIP();
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        try {

            // Fetch user with password hash
            $user = $this->db->fetchOne(
                "SELECT user_id, username, email, password_hash, first_name, last_name, 
                        role, department, status, must_change_password, login_attempts
                 FROM users 
                 WHERE (username = ? OR email = ? OR employee_id = ?) 
                 AND deleted_at IS NULL",
                [$username, $username, $username]
            );

            if (!$user) {
                $this->logFailedAttempt($username, $ipAddress, $userAgent, 'User not found');
                return false;
            }

            if ($user['status'] !== 'active') {
                throw new Exception("Account is {$user['status']}. Please contact administrator.");
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->handleFailedLogin($user['user_id'], $ipAddress, $userAgent);
                return false;
            }

            // Check if password needs rehash (algorithm upgrade)
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => HASH_COST])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $this->db->execute(
                    "UPDATE users SET password_hash = ? WHERE user_id = ?",
                    [$newHash, $user['user_id']]
                );
            }

            // Successful login - update user record
            $this->db->execute(
                "UPDATE users 
                 SET last_login = NOW(), 
                     last_login_ip = ?,
                     login_attempts = 0,
                     locked_until = NULL
                 WHERE user_id = ?",
                [$ipAddress, $user['user_id']]
            );

            // Create session
            $this->createSession($user['user_id'], $ipAddress, $userAgent);

            // Log audit
            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $user['user_id'],
                    $user['first_name'] . ' ' . $user['last_name'],
                    $user['role'],
                    'login',
                    'auth',
                    'users',
                    $user['user_id'],
                    "User login: {$user['username']}",
                    null,
                    null,
                    $ipAddress,
                    $userAgent,
                    'POST',
                    '/login',
                    'info'
                );
            }

            // Return user data (without password)
            unset($user['password_hash']);
            return $user;

        } catch (Exception $e) {
            error_log("Authentication Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create user session
     */
    private function createSession($userId, $ipAddress, $userAgent)
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

        $this->db->execute(
            "INSERT INTO user_sessions 
             (session_id, user_id, ip_address, user_agent, expires_at) 
             VALUES (?, ?, ?, ?, ?)",
            [$sessionId, $userId, $ipAddress, $userAgent, $expiresAt]
        );

        // Set session cookie
        setcookie(SESSION_NAME, $sessionId, [
            'expires'  => time() + SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => (defined('ENVIRONMENT') && ENVIRONMENT === 'production'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Regenerate PHP native session ID BEFORE writing session data
        // (prevents fixation AND avoids destroying just-written data)
        session_regenerate_id(true);

        $_SESSION['user_id']    = $userId;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['login_time'] = time();
    }

    /**
     * Handle failed login attempt (logs only, no lockout)
     */
    private function handleFailedLogin($userId, $ipAddress, $userAgent)
    {
        $this->db->execute(
            "UPDATE users SET login_attempts = login_attempts + 1 WHERE user_id = ?",
            [$userId]
        );

        $this->logFailedAttempt($userId, $ipAddress, $userAgent, 'Invalid password');
    }

    /**
     * Log failed authentication attempt
     */
    private function logFailedAttempt($identifier, $ipAddress, $userAgent, $reason)
    {
        error_log("Failed Login: {$identifier} from {$ipAddress} - {$reason}");

        // Could also insert into failed_login_attempts table for security monitoring
    }

    /**
     * Validate active session
     */
    public static function validateSession()
    {
        if (!isset($_COOKIE[SESSION_NAME])) {
            return false;
        }

        $sessionId = $_COOKIE[SESSION_NAME];
        $db = Database::getInstance();

        $session = $db->fetchOne(
            "SELECT s.*, u.user_id, u.username, u.email, u.first_name, u.last_name, 
                    u.role, u.department, u.status, u.must_change_password
             FROM user_sessions s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.session_id = ? 
             AND s.expires_at > NOW()
             AND s.is_valid = TRUE
             AND u.status = 'active'",
            [$sessionId]
        );

        if (!$session) {
            // Do NOT call logout() here — a transient DB failure would permanently
            // destroy the session. Just return false and let the redirect happen.
            return false;
        }

        // Update last activity
        $db->execute(
            "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?",
            [$sessionId]
        );

        // Regenerate session ID periodically — but use last_activity, not login_time.
        // login_time never updates, so the old code regenerated on EVERY request after 30 min,
        // causing a race condition with concurrent tabs.
        if (!empty($session['last_activity']) && time() - strtotime($session['last_activity']) > 1800) {
            self::regenerateSession($sessionId);
        }

        // Restore $_SESSION variables if PHP's native session was flushed
        // but our custom DB-backed session cookie is still valid
        if (empty($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['session_id'] = $session['session_id'];
            $_SESSION['login_time'] = strtotime($session['login_time']);
        }

        return $session;
    }

    /**
     * Regenerate session ID for security
     */
    private static function regenerateSession($oldSessionId)
    {
        $newSessionId = bin2hex(random_bytes(32));
        $db = Database::getInstance();

        $db->execute(
            "UPDATE user_sessions 
             SET session_id = ?, 
                 expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE session_id = ?",
            [$newSessionId, SESSION_TIMEOUT, $oldSessionId]
        );

        setcookie(SESSION_NAME, $newSessionId, [
            'expires'  => time() + SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => (defined('ENVIRONMENT') && ENVIRONMENT === 'production'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (isset($_SESSION['session_id'])) {
            $_SESSION['session_id'] = $newSessionId;
            // Reset PHP-side login_time so the session-manager idle check
            // doesn't fire exactly 1 hour after login regardless of activity
            $_SESSION['login_time'] = time();
        }
    }

    /**
     * Logout user
     */
    public static function logout($userId = null, $sessionId = null)
    {
        $db = Database::getInstance();

        if ($sessionId) {
            $db->execute(
                "UPDATE user_sessions SET is_valid = FALSE WHERE session_id = ?",
                [$sessionId]
            );
        }

        if ($userId && class_exists('AuditLogger')) {
            // Log audit
            AuditLogger::log(
                $userId,
                null,
                null,
                'logout',
                'auth',
                'users',
                $userId,
                "User logout",
                null,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/logout',
                'info'
            );
        }

        // Clear cookie
        setcookie(SESSION_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (defined('ENVIRONMENT') && ENVIRONMENT === 'production'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($permission)
    {
        global $ROLE_PERMISSIONS;

        if (!$this->userData) {
            return false;
        }

        $role = $this->userData['role'];
        $permissions = $ROLE_PERMISSIONS[$role] ?? [];

        // Check for wildcard
        if (in_array('*', $permissions)) {
            return true;
        }

        // Check exact permission
        if (in_array($permission, $permissions)) {
            return true;
        }

        // Check wildcard permissions (e.g., 'vehicles.*' matches 'vehicles.create')
        $parts = explode('.', $permission);
        $wildcard = $parts[0] . '.*';
        if (in_array($wildcard, $permissions)) {
            return true;
        }

        return false;
    }

    /**
     * Require permission or redirect
     */
    public function requirePermission($permission, $redirect = '/dashboard')
    {
        if (!$this->hasPermission($permission)) {
            $_SESSION['error_message'] = 'You do not have permission to access this resource.';
            header("Location: " . BASE_URL . ltrim($redirect, '/'));
            exit;
        }
    }

    /**
     * Load user data
     */
    private function loadUserData()
    {
        $this->userData = $this->db->fetchOne(
            "SELECT user_id, employee_id, username, email, first_name, last_name, 
                    middle_name, phone, department, role, avatar_path, status,
                    last_login, created_at
             FROM users 
             WHERE user_id = ? AND deleted_at IS NULL",
            [$this->userId]
        );
    }

    /**
     * Create new user
     */
    public function create($data, $createdBy)
    {
        // Validate required fields
        $required = ['employee_id', 'username', 'email', 'password', 'first_name', 'last_name', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required.");
            }
        }

        // Check uniqueness
        $exists = $this->db->fetchOne(
            "SELECT user_id FROM users 
             WHERE username = ? OR email = ? OR employee_id = ?",
            [$data['username'], $data['email'], $data['employee_id']]
        );

        if ($exists) {
            throw new Exception("Username, email, or employee ID already exists.");
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);

        $userId = $this->db->insert(
            "INSERT INTO users 
             (employee_id, username, email, password_hash, first_name, last_name, 
              middle_name, phone, department, role, created_by, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['employee_id'],
                $data['username'],
                $data['email'],
                $passwordHash,
                $data['first_name'],
                $data['last_name'],
                $data['middle_name'] ?? null,
                $data['phone'] ?? null,
                $data['department'] ?? 'operations',
                $data['role'],
                $createdBy,
                $data['must_change_password'] ?? true
            ]
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $createdBy,
                null,
                null,
                'create',
                'users',
                'users',
                $userId,
                "Created user: {$data['username']}",
                null,
                json_encode(['username' => $data['username'], 'role' => $data['role']]),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/users/create',
                'info'
            );
        }

        return $userId;
    }

    /**
     * Update user
     */
    public function update($userId, $data, $updatedBy)
    {
        $allowedFields = [
            'email',
            'first_name',
            'last_name',
            'middle_name',
            'phone',
            'department',
            'role',
            'status',
            'avatar_path'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $userId;

        $this->db->execute(
            "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE user_id = ?",
            $params
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $updatedBy,
                null,
                null,
                'update',
                'users',
                'users',
                $userId,
                "Updated user ID: {$userId}",
                null,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/users/update',
                'info'
            );
        }

        return true;
    }

    /**
     * Soft delete user
     */
    public function softDelete($userId, $deletedBy)
    {
        $this->db->execute(
            "UPDATE users SET deleted_at = NOW(), status = 'suspended' WHERE user_id = ?",
            [$userId]
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $deletedBy,
                null,
                null,
                'delete',
                'users',
                'users',
                $userId,
                "Soft deleted user ID: {$userId}",
                null,
                json_encode(['action' => 'soft_delete']),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/users/delete',
                'warning'
            );
        }

        return true;
    }

    /**
     * Change password
     */
    public function changePassword($userId, $oldPassword, $newPassword, $changedBy)
    {
        $user = $this->db->fetchOne(
            "SELECT password_hash FROM users WHERE user_id = ?",
            [$userId]
        );

        if (!$user) {
            throw new Exception("User not found.");
        }

        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new Exception("Current password is incorrect.");
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

        $this->db->execute(
            "UPDATE users 
             SET password_hash = ?, 
                 password_changed_at = NOW(),
                 must_change_password = FALSE
             WHERE user_id = ?",
            [$newHash, $userId]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $changedBy,
                null,
                null,
                'update',
                'users',
                'users',
                $userId,
                "Password changed for user ID: {$userId}",
                null,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/users/change-password',
                'info'
            );
        }

        return true;
    }

    /**
     * Get all users
     */
    public function getAll($filters = [], $page = 1, $perPage = ITEMS_PER_PAGE)
    {
        $where = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['department'])) {
            $where[] = "department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $whereClause = implode(' AND ', $where);

        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;

        $users = $this->db->fetchAll(
            "SELECT user_id, employee_id, username, email, first_name, last_name, 
                    department, role, status, last_login, created_at
             FROM users 
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $users,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($count / $perPage)
        ];
    }

    // Getters
    public function getId()
    {
        return $this->userId;
    }
    public function getData()
    {
        return $this->userData;
    }
    public function getFullName()
    {
        return $this->userData['first_name'] . ' ' . $this->userData['last_name'];
    }
}
