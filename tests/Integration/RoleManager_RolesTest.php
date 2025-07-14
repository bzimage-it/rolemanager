<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class RoleManager_RolesTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $roleManager;
    private $roleManager_Roles;
    private $rightManager;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        self::createSchema(self::$pdo);
        $this->roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->roleManager_Roles = $this->roleManager->roles();
        $this->rightManager = $this->roleManager->rights();

        // Seed data
        self::$pdo->exec("INSERT INTO role_manager_rightgroups (id, name) VALUES (1, 'General')");
        self::$pdo->exec("INSERT INTO role_manager_righttype_ranges (id, name, min_value, max_value) VALUES (1, 'Score', 0.00, 100.00)");
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetRole()
    {
        $role_id = $this->roleManager_Roles->create('Editor', 'Can edit articles');
        $this->assertIsNumeric($role_id);

        $role = $this->roleManager_Roles->getById($role_id);
        $this->assertEquals('Editor', $role['name']);
        $this->assertEquals('Can edit articles', $role['description']);
    }

    public function testUpdateRole()
    {
        $role_id = $this->roleManager_Roles->create('Old Role');
        $this->roleManager_Roles->update($role_id, ['name' => 'New Role', 'description' => 'New Desc']);
        $role = $this->roleManager_Roles->getById($role_id);
        $this->assertEquals('New Role', $role['name']);
        $this->assertEquals('New Desc', $role['description']);
    }

    public function testDeleteRole()
    {
        $role_id = $this->roleManager_Roles->create('Temporary Role');
        $result = $this->roleManager_Roles->delete($role_id);
        $this->assertTrue($result);
        $this->assertFalse($this->roleManager_Roles->getById($role_id));
    }

    public function testDeleteRoleWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete role: it is currently assigned to users or groups.");

        $role_id = $this->roleManager_Roles->create('Critical Role');
        // Simulate assignment
        self::$pdo->exec("INSERT INTO role_manager_users (id, login, password_hash, email) VALUES (1, 'test', 'hash', 'e@ma.il')");
        self::$pdo->exec("INSERT INTO role_manager_user_context_roles (user_id, role_id) VALUES (1, {$role_id})");

        $this->roleManager_Roles->delete($role_id);
    }

    public function testAddAndRemoveRightsFromRole()
    {
        $role_id = $this->roleManager_Roles->create('Complex Role');
        $right_bool_id = $this->rightManager->create('can_view', 'desc', 1, 'boolean');
        $right_range_id = $this->rightManager->create('set_level', 'desc', 1, 'range', 1);

        $this->roleManager_Roles->addRightToRole($role_id, $right_bool_id);
        $this->roleManager_Roles->addRightToRole($role_id, $right_range_id, 50);

        $rights = $this->roleManager_Roles->getRightsForRole($role_id);
        $this->assertCount(2, $rights);
        $right_names = array_column($rights, 'name');
        $this->assertContains('can_view', $right_names);
        $this->assertContains('set_level', $right_names);

        $this->roleManager_Roles->removeRightFromRole($role_id, $right_bool_id);
        $rights_after_remove = $this->roleManager_Roles->getRightsForRole($role_id);
        $this->assertCount(1, $rights_after_remove);
        $this->assertEquals('set_level', $rights_after_remove[0]['name']);
    }

    public function testAddRightToRoleValidatesRangeValue()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Value 150 is out of the allowed range (0.00 - 100.00) for this right type.");

        $role_id = $this->roleManager_Roles->create('Invalid Role');
        $right_range_id = $this->rightManager->create('set_level', 'desc', 1, 'range', 1);
        $this->roleManager_Roles->addRightToRole($role_id, $right_range_id, 150);
    }

    public function testAddRightToRoleFailsForRangeRightWithoutValue()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A value is required for a 'range' type right.");

        $role_id = $this->roleManager_Roles->create('Invalid Role');
        $right_range_id = $this->rightManager->create('set_level', 'desc', 1, 'range', 1);
        $this->roleManager_Roles->addRightToRole($role_id, $right_range_id, null);
    }
}