<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class RightTypeManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $rightTypeManager;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        self::createSchema(self::$pdo);
        $roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->rightTypeManager = $roleManager->rightTypes();
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetRightType()
    {
        $id = $this->rightTypeManager->create('Percentage', 'A value from 0 to 100', 0, 100);
        $this->assertIsNumeric($id);

        $type = $this->rightTypeManager->getById($id);
        $this->assertEquals('Percentage', $type['name']);
        $this->assertEquals('0.00', $type['min_value']);
        $this->assertEquals('100.00', $type['max_value']);
    }

    public function testUpdateRightType()
    {
        $id = $this->rightTypeManager->create('Old Range', 'desc', 0, 10);
        $this->rightTypeManager->update($id, ['name' => 'New Range', 'max_value' => 20]);
        $type = $this->rightTypeManager->getById($id);
        $this->assertEquals('New Range', $type['name']);
        $this->assertEquals('20.00', $type['max_value']);
    }

    public function testDeleteRightType()
    {
        $id = $this->rightTypeManager->create('To Be Deleted', 'desc', 0, 1);
        $result = $this->rightTypeManager->delete($id);
        $this->assertTrue($result);
        $this->assertFalse($this->rightTypeManager->getById($id));
    }

    public function testDeleteRightTypeWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete right type range: it is in use by one or more rights.");

        $type_id = $this->rightTypeManager->create('Important Type', 'desc', 0, 100);
        self::$pdo->exec("INSERT INTO role_manager_rightgroups (id, name) VALUES (1, 'temp_group')");
        self::$pdo->exec("INSERT INTO role_manager_rights (name, rightgroup_id, type, righttype_range_id) VALUES ('test_right', 1, 'range', {$type_id})");

        $this->rightTypeManager->delete($type_id);
    }

    public function testGetAllRightTypes()
    {
        $this->rightTypeManager->create('Type A', 'desc', 1, 2);
        $this->rightTypeManager->create('Type B', 'desc', 3, 4);
        $types = $this->rightTypeManager->getAll();
        $this->assertCount(2, $types);
    }
    
    public function testCreateFailsWithInvalidRange()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("max_value must be greater than or equal to min_value");
        $this->rightTypeManager->create('Invalid Range', 'desc', 100, 0);
    }
}