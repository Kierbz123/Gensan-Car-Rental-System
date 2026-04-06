CREATE TABLE IF NOT EXISTS `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('customer','vehicle','rental_agreement','system','supplier','other') NOT NULL DEFAULT 'other',
  `entity_id` varchar(50) DEFAULT NULL,
  `document_category` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`document_id`),
  KEY `fk_documents_user` (`uploaded_by`),
  KEY `idx_documents_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_documents_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional index for faster category/status searches
CREATE INDEX `idx_documents_search` ON `documents` (`document_category`, `status`);
