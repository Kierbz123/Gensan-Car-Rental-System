CREATE TABLE IF NOT EXISTS backups (
    backup_id VARCHAR(50) PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    backup_type ENUM('manual', 'automated') DEFAULT 'manual',
    status ENUM('completed', 'failed', 'in_progress') DEFAULT 'in_progress',
    created_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);
