<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class RightManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $roleManager;
    private $rightManager;
    private $roleManager_Roles;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        self::createSchema(self::$pdo);
        $this->roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->rightManager = $this->roleManager->rights();
        $this->roleManager_Roles = $this->roleManager->roles();

        // Seed necessary base data for foreign keys
        self::$pdo->exec("INSERT INTO role_manager_rightgroups (id, name) VALUES (1, 'General')");
        self::$pdo->exec("INSERT INTO role_manager_righttype_ranges (id, name, min_value, max_value) VALUES (1, 'Score', 0, 100)");
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetRight()
    {
        // Test boolean right
        $bool_right_id = $this->rightManager->create('can_edit', 'Can edit content', 1, 'boolean');
        $this->assertIsNumeric($bool_right_id);
        $right = $this->rightManager->getById($bool_right_id);
        $this->assertEquals('can_edit', $right['name']);
        $this->assertEquals('boolean', $right['type']);
        $this->assertNull($right['righttype_range_id']);

        // Test range right
        $range_right_id = $this->rightManager->create('set_score', 'Can set a score', 1, 'range', 1);
        $this->assertIsNumeric($range_right_id);
        $right = $this->rightManager->getById($range_right_id);
        $this->assertEquals('set_score', $right['name']);
        $this->assertEquals('range', $right['type']);
        $this->assertEquals(1, $right['righttype_range_id']);
    }

    public function testCreateFailsForRangeRightWithoutRangeId()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A 'range' type right requires a 'righttype_range_id'.");
        $this->rightManager->create('invalid_range', 'desc', 1, 'range');
    }
    
    public function testCreateFailsForBooleanRightWithRangeId()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A 'boolean' type right must not have a 'righttype_range_id'.");
        $this->rightManager->create('invalid_bool', 'desc', 1, 'boolean', 1);
    }

    public function testUpdateRight()
    {
        $right_id = $this->rightManager->create('old_name', 'old desc', 1, 'boolean');
        $this->rightManager->update($right_id, ['name' => 'new_name', 'description' => 'new desc']);

        $right = $this->rightManager->getById($right_id);
        $this->assertEquals('new_name', $right['name']);
        $this->assertEquals('new desc', $right['description']);
    }

    public function testDeleteRight()
    {
        $right_id = $this->rightManager->create('to_be_deleted', 'desc', 1, 'boolean');
        $result = $this->rightManager->delete($right_id);
        $this->assertTrue($result);

        $right = $this->rightManager->getById($right_id);
        $this->assertFalse($right);
    }

    public function testDeleteRightWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete right: it is used in one or more roles.");

        $right_id = $this->rightManager->create('important_right', 'desc', 1, 'boolean');
        
        $role_id = $this->roleManager_Roles->create('Test Role');
        $this->roleManager_Roles->addRightToRole($role_id, $right_id);

        $this->rightManager->delete($right_id);
    }
}