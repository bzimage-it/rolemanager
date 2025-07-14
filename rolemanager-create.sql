-- ============================================
-- ROLE MANAGER DATABASE STRUCTURE
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Logging table
CREATE TABLE IF NOT EXISTS `role_manager_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `level` ENUM('debug','info','notice','warning','error','critical','alert','fatal') NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS `role_manager_users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `login` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_login` (`login`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups table
CREATE TABLE IF NOT EXISTS `role_manager_groups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users-Groups relationship (many-to-many)
CREATE TABLE IF NOT EXISTS `role_manager_user_groups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `role_manager_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_user_group` (`user_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups-Groups relationship (many-to-many for subgroups)
CREATE TABLE IF NOT EXISTS `role_manager_group_subgroups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `parent_group_id` INT NOT NULL,
    `child_group_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`child_group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_parent_child` (`parent_group_id`, `child_group_id`),
    -- Constraint to prevent self-referencing groups
    CHECK (`parent_group_id` != `child_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Right groups table (groups rights into families)
CREATE TABLE IF NOT EXISTS `role_manager_rightgroups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Right type ranges table (defines available range types)
CREATE TABLE IF NOT EXISTS `role_manager_righttype_ranges` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `min_value` DECIMAL(10, 2) NOT NULL,
    `max_value` DECIMAL(10, 2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`),
    CHECK (`max_value` >= `min_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rights table
CREATE TABLE IF NOT EXISTS `role_manager_rights` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `rightgroup_id` INT NOT NULL,
    `type` ENUM('boolean', 'range') NOT NULL,
    `righttype_range_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`rightgroup_id`) REFERENCES `role_manager_rightgroups`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`righttype_range_id`) REFERENCES `role_manager_righttype_ranges`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_name` (`name`),
    -- Constraints to ensure type consistency
    CHECK (
        (type = 'boolean' AND righttype_range_id IS NULL) OR
        (type = 'range' AND righttype_range_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS `role_manager_roles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role rights table (many-to-many with values)
CREATE TABLE IF NOT EXISTS `role_manager_role_rights` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `role_id` INT NOT NULL,
    `right_id` INT NOT NULL,
    `range_value` DECIMAL(10, 2) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`right_id`) REFERENCES `role_manager_rights`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_role_right` (`role_id`, `right_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contexts table
CREATE TABLE IF NOT EXISTS `role_manager_contexts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-Context-Role assignments
CREATE TABLE IF NOT EXISTS `role_manager_user_context_roles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `context_id` INT DEFAULT NULL,
    `role_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `role_manager_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`context_id`) REFERENCES `role_manager_contexts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_user_context_role` (`user_id`, `context_id`, `role_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_context_id` (`context_id`),
    INDEX `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group-Context-Role assignments
CREATE TABLE IF NOT EXISTS `role_manager_group_context_roles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `group_id` INT NOT NULL,
    `context_id` INT DEFAULT NULL,
    `role_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`context_id`) REFERENCES `role_manager_contexts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_group_context_role` (`group_id`, `context_id`, `role_id`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_g_context_id` (`context_id`),
    INDEX `idx_g_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration table (for caching version token, etc.)
CREATE TABLE IF NOT EXISTS `role_manager_config` (
  `config_key` VARCHAR(50) PRIMARY KEY,
  `config_value` VARCHAR(255) NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize the permissions version for caching
INSERT INTO `role_manager_config` (`config_key`, `config_value`) VALUES ('permissions_version', '1')
ON DUPLICATE KEY UPDATE config_key=config_key;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- ============================================

-- Indexes for frequent searches
CREATE INDEX `idx_user_login` ON `role_manager_users` (`login`);
CREATE INDEX `idx_group_name` ON `role_manager_groups` (`name`);
CREATE INDEX `idx_right_type` ON `role_manager_rights` (`type`);
CREATE INDEX `idx_right_rightgroup` ON `role_manager_rights` (`rightgroup_id`);

-- ============================================
-- TABLE DOCUMENTATION
-- ============================================

ALTER TABLE `role_manager_users` COMMENT = 'System users table';
ALTER TABLE `role_manager_groups` COMMENT = 'User groups table';
ALTER TABLE `role_manager_rights` COMMENT = 'Available system rights table';
ALTER TABLE `role_manager_roles` COMMENT = 'Roles table (sets of rights)';
ALTER TABLE `role_manager_contexts` COMMENT = 'Contexts table for role assignments';
ALTER TABLE `role_manager_logs` COMMENT = 'System logging table'; 