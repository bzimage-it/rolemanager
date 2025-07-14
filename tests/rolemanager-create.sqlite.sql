-- ============================================
-- ROLE MANAGER DATABASE STRUCTURE (SQLite Version for Testing)
-- ============================================

-- Logging table
CREATE TABLE `role_manager_logs` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `timestamp` TEXT DEFAULT CURRENT_TIMESTAMP,
    `level` TEXT NOT NULL CHECK(`level` IN ('debug','info','notice','warning','error','critical','alert','fatal')),
    `message` TEXT NOT NULL,
    `context` TEXT DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX `idx_timestamp` ON `role_manager_logs` (`timestamp`);
CREATE INDEX `idx_level` ON `role_manager_logs` (`level`);

-- Users table
CREATE TABLE `role_manager_users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `login` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Groups table
CREATE TABLE `role_manager_groups` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Users-Groups relationship (many-to-many)
CREATE TABLE `role_manager_user_groups` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id` INTEGER NOT NULL,
    `group_id` INTEGER NOT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `role_manager_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    UNIQUE (`user_id`, `group_id`)
);

-- Groups-Groups relationship (many-to-many for subgroups)
CREATE TABLE `role_manager_group_subgroups` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `parent_group_id` INTEGER NOT NULL,
    `child_group_id` INTEGER NOT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`child_group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    UNIQUE (`parent_group_id`, `child_group_id`),
    CHECK (`parent_group_id` != `child_group_id`)
);

-- Right groups table (groups rights into families)
CREATE TABLE `role_manager_rightgroups` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Right type ranges table (defines available range types)
CREATE TABLE `role_manager_righttype_ranges` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `min_value` DECIMAL(10, 2) NOT NULL,
    `max_value` DECIMAL(10, 2) NOT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    CHECK (`max_value` >= `min_value`)
);

-- Rights table
CREATE TABLE `role_manager_rights` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `rightgroup_id` INTEGER NOT NULL,
    `type` TEXT NOT NULL CHECK(`type` IN ('boolean', 'range')),
    `righttype_range_id` INTEGER DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`rightgroup_id`) REFERENCES `role_manager_rightgroups`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`righttype_range_id`) REFERENCES `role_manager_righttype_ranges`(`id`) ON DELETE RESTRICT,
    CHECK (
        (type = 'boolean' AND righttype_range_id IS NULL) OR
        (type = 'range' AND righttype_range_id IS NOT NULL)
    )
);

-- Roles table
CREATE TABLE `role_manager_roles` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Role rights table (many-to-many with values)
CREATE TABLE `role_manager_role_rights` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `role_id` INTEGER NOT NULL,
    `right_id` INTEGER NOT NULL,
    `range_value` DECIMAL(10, 2) DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`right_id`) REFERENCES `role_manager_rights`(`id`) ON DELETE RESTRICT,
    UNIQUE (`role_id`, `right_id`)
);

-- Contexts table
CREATE TABLE `role_manager_contexts` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- User-Context-Role assignments
CREATE TABLE `role_manager_user_context_roles` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id` INTEGER NOT NULL,
    `context_id` INTEGER DEFAULT NULL,
    `role_id` INTEGER NOT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `role_manager_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`context_id`) REFERENCES `role_manager_contexts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE RESTRICT,
    UNIQUE (`user_id`, `context_id`, `role_id`)
);

-- Group-Context-Role assignments
CREATE TABLE `role_manager_group_context_roles` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `group_id` INTEGER NOT NULL,
    `context_id` INTEGER DEFAULT NULL,
    `role_id` INTEGER NOT NULL,
    `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `role_manager_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`context_id`) REFERENCES `role_manager_contexts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `role_manager_roles`(`id`) ON DELETE RESTRICT,
    UNIQUE (`group_id`, `context_id`, `role_id`)
);

-- Configuration table (for caching version token, etc.)
CREATE TABLE `role_manager_config` (
  `config_key` VARCHAR(50) PRIMARY KEY,
  `config_value` VARCHAR(255) NOT NULL,
  `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Initialize the permissions version for caching
INSERT INTO `role_manager_config` (`config_key`, `config_value`) VALUES ('permissions_version', '1');