<?php

namespace RoleManager;

/**
 * Describes a logger instance for the RoleManager library.
 * This allows for custom logger implementations to be used.
 */
interface LoggerInterface
{
    /**
     * Sets the minimum log level for console output.
     * @param string $level The log level (e.g., 'info', 'warning').
     */
    public function setConsoleLevel(string $level): void;

    /**
     * Sets the minimum log level for database logging.
     * @param string $level The log level (e.g., 'error', 'critical').
     */
    public function setDbLevel(string $level): void;

    /**
     * Logs a message with a given level.
     *
     * @param string      $level     The log level.
     * @param string      $message   The log message.
     * @param array|null  $context   Optional context data to be stored as JSON.
     * @param bool        $force_db  If true, forces the log entry to be saved to the database, ignoring the db_level.
     * @return bool True on success.
     */
    public function log(string $level, string $message, ?array $context = null, bool $force_db = false): bool;

    public function debug(string $message, ?array $context = null): bool;

    public function info(string $message, ?array $context = null): bool;

    public function notice(string $message, ?array $context = null): bool;

    public function warning(string $message, ?array $context = null): bool;

    public function error(string $message, ?array $context = null): bool;

    public function critical(string $message, ?array $context = null): bool;

    public function alert(string $message, ?array $context = null): bool;

    public function fatal(string $message, ?array $context = null): bool;
}