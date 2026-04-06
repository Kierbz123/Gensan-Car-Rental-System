<?php
// /var/www/html/gensan-car-rental-system/config/database.php

/**
 * Database Connection and Management Class
 */

class Database
{
    private static $instance = null;
    private $connection;
    private $lastQuery;
    private $queryCount = 0;

    // Private constructor for singleton pattern
    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Failed to connect to database. " . $e->getMessage());
        }
    }

    // Get singleton instance
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get PDO connection
    public function getConnection()
    {
        return $this->connection;
    }

    // Execute query with parameters
    public function query($sql, $params = []): PDOStatement
    {
        try {
            $this->lastQuery = $sql;
            $this->queryCount++;

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            return $stmt;

        } catch (PDOException $e) {
            $errorMsg = "Database query failed: " . $e->getMessage() . " | SQL: " . $this->lastQuery;
            if (!empty($params)) {
                $errorMsg .= " | Params: " . json_encode($params);
            }
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }
    }

    // Fetch single row
    public function fetchOne($sql, $params = []): array|bool
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch() ?: false;
    }

    // Fetch all rows
    public function fetchAll($sql, $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    // Fetch column
    public function fetchColumn($sql, $params = [], $column = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    // Insert and return last ID
    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    // Update/Delete - return affected rows
    public function execute($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Begin transaction
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    // Commit transaction
    public function commit()
    {
        return $this->connection->commit();
    }

    // Rollback transaction
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    // Check if in transaction
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    // Get last insert ID
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    // Sanitize input
    public function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    // Get query count
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    // Get last query
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    // Close connection
    public function close()
    {
        $this->connection = null;
        self::$instance = null;
    }
}

// Helper function to get database instance
function db()
{
    return Database::getInstance();
}
