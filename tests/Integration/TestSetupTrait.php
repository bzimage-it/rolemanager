<?php

namespace RoleManager\Tests\Integration;

use PDO;

/**
 * A trait to handle common test setup tasks like creating the database connection
 * and schema for tests.
 */
trait TestSetupTrait
{
    protected static function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    protected static function createSchema(PDO $pdo): void
    {
        // Use environment variables for MySQL, otherwise fall back to SQLite.
        // You can set these in your shell: export DB_DSN="mysql:host=localhost;dbname=test_db"
        $dsn = getenv('DB_DSN');

        $driver = $dsn ? (new PDO($dsn))->getAttribute(PDO::ATTR_DRIVER_NAME) : 'sqlite';

        $sqlFile = $driver === 'mysql' ? __DIR__ . '/../../rolemanager-create.sql' : __DIR__ . '/../../tests/rolemanager-create.sqlite.sql';
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
    }
}