<?php

namespace RoleManager;

use PDO;
use Exception;

/**
 * A simple logger class that can log to the console (stderr) and a database table.
 * It supports different log levels for each channel and provides convenience methods
 * for each level (e.g., ->info(), ->error()).
 */
class RoleManagerLogger implements LoggerInterface {
    /** @var PDO */
    private $db;
    
    /** @var string[] */
    private array $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'fatal'];
    
    /** @var string */
    private string $console_level = 'info';
    
    /** @var string */
    private string $db_level = 'warning';
    
    /**
     * RoleManagerLogger constructor.
     *
     * @param PDO $database_connection The active PDO database connection.
     */
    public function __construct(PDO $database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Sets the minimum log level for console output.
     *
     * @param string $level The log level (e.g., 'info', 'warning').
     */
    public function setConsoleLevel(string $level): void {
        if (in_array($level, $this->levels)) {
            $this->console_level = $level;
        }
    }
    
    /**
     * Sets the minimum log level for database logging.
     *
     * @param string $level The log level (e.g., 'error', 'critical').
     */
    public function setDbLevel(string $level): void {
        if (in_array($level, $this->levels)) {
            $this->db_level = $level;
        }
    }
    
    /**
     * Logs a message with a given level.
     *
     * @param string      $level     The log level.
     * @param string      $message   The log message.
     * @param array|null  $context   Optional context data to be stored as JSON.
     * @param bool        $force_db  If true, forces the log entry to be saved to the database, ignoring the db_level.
     * @return bool True on success.
     */
    public function log(string $level, string $message, ?array $context = null, bool $force_db = false): bool {
        if (!in_array($level, $this->levels)) return false;
        
        $timestamp = date('Y-m-d H:i:s');
        $level_value = array_search($level, $this->levels);
        
        if ($level_value >= array_search($this->console_level, $this->levels)) {
            file_put_contents('php://stderr', "[{$timestamp}] {$level}: {$message}\n");
        }
        
        if ($force_db || $level_value >= array_search($this->db_level, $this->levels)) {
            $this->logToDatabase($level, $message, $context);
        }
        
        return true;
    }
    
    private function logToDatabase(string $level, string $message, ?array $context): void {
        try {
            $stmt = $this->db->prepare("INSERT INTO role_manager_logs (level, message, context) VALUES (?, ?, ?)");
            $context_json = $context ? json_encode($context) : null;
            $stmt->execute([$level, $message, $context_json]);
        } catch (Exception $e) {
            // Avoid throwing an exception from the logger itself
            error_log("RoleManager Logger Error: " . $e->getMessage());
        }
    }
    
    // Convenience methods implementing the LoggerInterface

    public function debug(string $message, ?array $context = null): bool {
        return $this->log('debug', $message, $context, false);
    }
    public function info(string $message, ?array $context = null): bool {
        return $this->log('info', $message, $context, false);
    }
    public function notice(string $message, ?array $context = null): bool {
        return $this->log('notice', $message, $context, false);
    }
    public function warning(string $message, ?array $context = null): bool {
        return $this->log('warning', $message, $context, false);
    }
    public function error(string $message, ?array $context = null): bool {
        return $this->log('error', $message, $context, true);
    }
    public function critical(string $message, ?array $context = null): bool {
        return $this->log('critical', $message, $context, true);
    }
    public function alert(string $message, ?array $context = null): bool {
        return $this->log('alert', $message, $context, true);
    }
    public function fatal(string $message, ?array $context = null): bool {
        return $this->log('fatal', $message, $context, true);
    }
}
