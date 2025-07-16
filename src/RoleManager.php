<?php

namespace RoleManager;

use PDO;
use Exception;

/**
 * Main entry point for the RoleManager library.
 *
 * This class acts as a factory to provide access to the various manager
 * classes that handle specific entities like users, groups, roles, etc.
 */
class RoleManager {
    /**
     * The current version of the RoleManager library.
     * Follows Semantic Versioning (https://semver.org).
     */
    public const VERSION = '0.1.0';

    /** @var PDO */
    private $db;

    /** @var LoggerInterface */
    private $logger;
    
    /**
     * RoleManager constructor.
     *
     * @param PDO                  $database_connection An active PDO database connection.
     * @param LoggerInterface|null $logger              An optional logger instance. If null, a default one is created.
     */
    public function __construct(PDO $database_connection, ?LoggerInterface $logger = null) {
        $this->db = $database_connection;
        $this->logger = $logger ?? new RoleManagerLogger($this->db);
    }
    
    /**
     * @return UserManager
     */
    public function users(): UserManager { return new UserManager($this->db, $this->logger); }
    
    /**
     * @return GroupManager
     */
    public function groups(): GroupManager { return new GroupManager($this->db, $this->logger); }
    
    /**
     * @return RightManager
     */
    public function rights(): RightManager { return new RightManager($this->db, $this->logger); }
    
    /**
     * @return RoleManager_Roles
     */
    public function roles(): RoleManager_Roles { return new RoleManager_Roles($this->db, $this->logger); }
    
    /**
     * @return ContextManager
     */
    public function contexts(): ContextManager { return new ContextManager($this->db, $this->logger); }
    
    /**
     * @return AuthManager
     */
    public function auth(): AuthManager { return new AuthManager($this->db, $this->logger); }
    
    /**
     * @return RightTypeManager
     */
    public function rightTypes(): RightTypeManager { return new RightTypeManager($this->db, $this->logger); }
    
    /**
     * @return RightGroupManager
     */
    public function rightGroups(): RightGroupManager { return new RightGroupManager($this->db, $this->logger); }
    
    /**
     * Returns the logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface { return $this->logger; }
}
