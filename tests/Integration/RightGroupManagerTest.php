<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class RightGroupManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $rightGroupManager;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        self::createSchema(self::$pdo);
        $roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->rightGroupManager = $roleManager->rightGroups();
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetRightGroup()
    {
        $id = $this->rightGroupManager->create('Content', 'Rights related to content management');
        $this->assertIsNumeric($id);

        $group = $this->rightGroupManager->getById($id);
        $this->assertEquals('Content', $group['name']);
        $this->assertEquals('Rights related to content management', $group['description']);
    }

    public function testUpdateRightGroup()
    {
        $id = $this->rightGroupManager->create('Old Name');
        $this->rightGroupManager->update($id, ['name' => 'New Name', 'description' => 'New Desc']);
        $group = $this->rightGroupManager->getById($id);
        $this->assertEquals('New Name', $group['name']);
        $this->assertEquals('New Desc', $group['description']);
    }

    public function testDeleteRightGroup()
    {
        $id = $this->rightGroupManager->create('To Be Deleted');
        $result = $this->rightGroupManager->delete($id);
        $this->assertTrue($result);
        $this->assertFalse($this->rightGroupManager->getById($id));
    }

    public function testDeleteRightGroupWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete right group: it is in use by one or more rights.");

        $id = $this->rightGroupManager->create('Important Group');
        self::$pdo->exec("INSERT INTO role_manager_rights (name, rightgroup_id, type) VALUES ('test_right', {$id}, 'boolean')");

        $this->rightGroupManager->delete($id);
    }

    public function testGetAllRightGroups()
    {
        $this->rightGroupManager->create('Group A');
        $this->rightGroupManager->create('Group B');
        $groups = $this->rightGroupManager->getAll();
        $this->assertCount(2, $groups);
    }
}