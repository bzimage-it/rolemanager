<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class ContextManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $roleManager;
    private $contextManager;
    private $userManager;
    private $groupManager;
    private $roleManager_Roles;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
        self::createSchema(self::$pdo);
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        $this->roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->contextManager = $this->roleManager->contexts();
        $this->userManager = $this->roleManager->users();
        $this->groupManager = $this->roleManager->groups();
        $this->roleManager_Roles = $this->roleManager->roles();

        // Seed data
        self::$pdo->exec("INSERT INTO role_manager_users (id, login, password_hash, email) VALUES (1, 'testuser', 'hash', 'test@user.com')");
        self::$pdo->exec("INSERT INTO role_manager_groups (id, name) VALUES (1, 'Test Group')");
        self::$pdo->exec("INSERT INTO role_manager_roles (id, name) VALUES (1, 'Test Role')");
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetContext()
    {
        $context_id = $this->contextManager->create('Forum', 'Discussion forum context');
        $this->assertIsNumeric($context_id);

        $context = $this->contextManager->getById($context_id);
        $this->assertEquals('Forum', $context['name']);
    }

    public function testUpdateContext()
    {
        $context_id = $this->contextManager->create('Old Context');
        $this->contextManager->update($context_id, ['name' => 'New Context', 'description' => 'New Desc']);
        $context = $this->contextManager->getById($context_id);
        $this->assertEquals('New Context', $context['name']);
        $this->assertEquals('New Desc', $context['description']);
    }

    public function testDeleteContext()
    {
        $context_id = $this->contextManager->create('Temp Context');
        $result = $this->contextManager->delete($context_id);
        $this->assertTrue($result);
        $this->assertFalse($this->contextManager->getById($context_id));
    }

    public function testDeleteContextWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete context: it is currently in use.");
        
        $context_id = $this->contextManager->create('Important Context');
        $this->contextManager->assignRoleToUser(1, 1, $context_id);

        $this->contextManager->delete($context_id);
    }

    public function testAssignAndRemoveRoleFromUserInContext()
    {
        $context_id = $this->contextManager->create('Project Alpha');
        $this->contextManager->assignRoleToUser(1, 1, $context_id);
        
        $result_remove = $this->contextManager->removeRoleFromUser(1, 1, $context_id);
        $this->assertTrue($result_remove);
    }

    public function testAssignAndRemoveRoleFromUserGlobally()
    {
        $this->contextManager->assignRoleToUser(1, 1, null); // Global assignment
        
        $result_remove = $this->contextManager->removeRoleFromUser(1, 1, null);
        $this->assertTrue($result_remove);
    }

    public function testAssignAndRemoveRoleFromGroupInContext()
    {
        $context_id = $this->contextManager->create('Project Beta');
        $this->contextManager->assignRoleToGroup(1, 1, $context_id);
        
        $result_remove = $this->contextManager->removeRoleFromGroup(1, 1, $context_id);
        $this->assertTrue($result_remove);
    }

    public function testAssignAndRemoveRoleFromGroupGlobally()
    {
        $this->contextManager->assignRoleToGroup(1, 1, null); // Global assignment
        
        $result_remove = $this->contextManager->removeRoleFromGroup(1, 1, null);
        $this->assertTrue($result_remove);
    }
}