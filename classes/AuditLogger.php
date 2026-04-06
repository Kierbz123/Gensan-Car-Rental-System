<?php
// /var/www/html/gensan-car-rental-system/classes/AuditLogger.php

/**
 * Audit Logging Class
 * Comprehensive audit trail for all system activities
 */

class AuditLogger
{
    private static $db;

    /**
     * Log an audit entry
     */
    public static function log(
        $userId,
        $userName,
        $userRole,
        $action,
        $module,
        $tableName,
        $recordId,
        $recordDescription,
        $oldValues,
        $newValues,
        $ipAddress,
        $userAgent,
        $requestMethod,
        $requestUrl,
        $severity = 'info'
    ) {

        self::$db = Database::getInstance();

        // Detect changed fields
        $changedFields = null;
        if ($oldValues && $newValues) {
            $old = json_decode($oldValues, true) ?? [];
            $new = json_decode($newValues, true) ?? [];
            $changed = [];
            foreach ($new as $key => $value) {
                if (!isset($old[$key]) || $old[$key] !== $value) {
                    $changed[] = $key;
                }
            }
            $changedFields = json_encode($changed);
        }

        self::$db->execute(
            "INSERT INTO audit_logs 
             (user_id, user_name, user_role, action, module, table_name, record_id,
              record_description, old_values, new_values, changed_fields,
              ip_address, user_agent, request_method, request_url, action_timestamp, severity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                $userId,
                $userName,
                $userRole,
                $action,
                $module,
                $tableName,
                $recordId,
                $recordDescription,
                $oldValues,
                $newValues,
                $changedFields,
                $ipAddress,
                $userAgent,
                $requestMethod,
                $requestUrl,
                $severity
            ]
        );

        return self::$db->lastInsertId();
    }

    /**
     * Get audit trail with filtering
     */
    public static function getAuditTrail($filters = [], $page = 1, $perPage = 50)
    {
        self::$db = Database::getInstance();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = "al.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['module'])) {
            $where[] = "al.module = ?";
            $params[] = $filters['module'];
        }

        if (!empty($filters['table_name'])) {
            $where[] = "al.table_name = ?";
            $params[] = $filters['table_name'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "al.action_timestamp >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "al.action_timestamp <= ?";
            // Append end-of-day time so the full date_to day is included (action_timestamp is DATETIME)
            $dateTo = $filters['date_to'];
            $params[] = (strlen($dateTo) === 10) ? $dateTo . ' 23:59:59' : $dateTo;
        }

        if (!empty($filters['search'])) {
            $where[] = "(al.record_description LIKE ? OR al.record_id LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search]);
        }

        if (!empty($filters['severity'])) {
            $where[] = "al.severity = ?";
            $params[] = $filters['severity'];
        }

        $whereClause = implode(' AND ', $where);

        $count = self::$db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs al WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;

        $logs = self::$db->fetchAll(
            "SELECT al.*, 
                    COALESCE(NULLIF(al.user_name, ''), CONCAT(u.first_name, ' ', u.last_name), 'System') as user_name
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.user_id
             WHERE {$whereClause}
             ORDER BY al.action_timestamp DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $logs,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($count / $perPage)
        ];
    }

    /**
     * Export audit trail to CSV/Excel
     */
    public static function export($filters = [], $format = 'csv')
    {
        $logs = self::getAuditTrail($filters, 1, 10000); // Max 10k records

        if ($format === 'csv') {
            if (ob_get_level())
                ob_end_clean();

            $filename = 'audit_trail_' . date('Y-m-d_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'User', 'Role', 'Action', 'Module', 'Table', 'Record ID', 'Description', 'IP Address']);

            foreach ($logs['data'] as $log) {
                fputcsv($output, [
                    $log['action_timestamp'] ?? '',
                    $log['user_name'] ?? 'System',
                    $log['user_role'] ?? '',
                    $log['action'] ?? '',
                    $log['module'] ?? '',
                    $log['table_name'] ?? '',
                    $log['record_id'] ?? '',
                    $log['record_description'] ?? '',
                    $log['ip_address'] ?? ''
                ]);
            }

            fclose($output);
            exit;
        }
    }
}
