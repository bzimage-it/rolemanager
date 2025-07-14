<?php

namespace RoleManager;

use PDO;
use Exception;

/**
 * Abstract base class for all manager classes.
 * Provides common properties and methods like database connection, logger, and validation.
 */
abstract class BaseManager {
    /** @var PDO */
    protected $db;

    /** @var LoggerInterface */
    protected $logger;
    
    /**
     * BaseManager constructor.
     *
     * @param PDO               $database_connection The active PDO database connection.
     * @param LoggerInterface   $logger              The logger instance.
     */
    public function __construct(PDO $database_connection, LoggerInterface $logger) {
        $this->db = $database_connection;
        $this->logger = $logger;
    }
    
    /**
     * Validates that a value is not empty.
     *
     * @param mixed  $value      The value to check.
     * @param string $field_name The name of the field for the exception message.
     * @throws Exception if the value is empty.
     */
    protected function validateNotEmpty(mixed $value, string $field_name): void {
        if (empty($value)) {
            throw new Exception("$field_name is required");
        }
    }

    /**
     * Increments the global permissions version to invalidate caches. This is the core
     * of the "Version Token" cache invalidation strategy.
     */
    protected function incrementPermissionsVersion(): void {
        $stmt = $this->db->prepare("UPDATE role_manager_config SET config_value = config_value + 1 WHERE config_key = 'permissions_version'");
        $stmt->execute();
        $this->logger->debug("Permissions version incremented.");
    }
}
