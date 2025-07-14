<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class GroupManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $roleManager;
    private $groupManager;
    private $userManager;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = self::createPdo();
        self::createSchema(self::$pdo);
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        $this->roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->groupManager = $this->roleManager->groups();
        $this->userManager = $this->roleManager->users();
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    public function testCreateAndGetGroup()
    {
        $group_id = $this->groupManager->create('Editors', 'Can edit content');
        $this->assertIsNumeric($group_id);

        $group = $this->groupManager->getById($group_id);
        $this->assertEquals('Editors', $group['name']);
        $this->assertEquals('Can edit content', $group['description']);

        $groupByName = $this->groupManager->getByName('Editors');
        $this->assertEquals($group_id, $groupByName['id']);
    }

    public function testUpdateGroup()
    {
        $group_id = $this->groupManager->create('Old Name');
        $this->groupManager->update($group_id, ['name' => 'New Name', 'description' => 'New Desc']);

        $group = $this->groupManager->getById($group_id);
        $this->assertEquals('New Name', $group['name']);
        $this->assertEquals('New Desc', $group['description']);
    }

    public function testDeleteGroup()
    {
        $group_id = $this->groupManager->create('To Be Deleted');
        $result = $this->groupManager->delete($group_id);
        $this->assertTrue($result);

        $group = $this->groupManager->getById($group_id);
        $this->assertFalse($group);
    }

    public function testDeleteGroupWithDependenciesFails()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot delete group: it has roles assigned, user memberships, or is part of a hierarchy.");

        $user_id = $this->userManager->create('testuser', 'pass', 'test@user.com');
        $group_id = $this->groupManager->create('Important Group');
        $this->groupManager->addUserToGroup($user_id, $group_id);

        $this->groupManager->delete($group_id);
    }

    public function testAddAndRemoveUserFromGroup()
    {
        $user_id = $this->userManager->create('testuser', 'pass', 'test@user.com');
        $group_id = $this->groupManager->create('Test Group');

        $this->groupManager->addUserToGroup($user_id, $group_id);
        $users = $this->groupManager->getUsersInGroup($group_id);
        $this->assertCount(1, $users);
        $this->assertEquals($user_id, $users[0]['id']);

        $this->groupManager->removeUserFromGroup($user_id, $group_id);
        $users_after = $this->groupManager->getUsersInGroup($group_id);
        $this->assertCount(0, $users_after);
    }

    public function testGroupHierarchy()
    {
        $parent_id = $this->groupManager->create('Parent');
        $child_id = $this->groupManager->create('Child');

        $this->groupManager->addSubgroup($parent_id, $child_id);

        $parents = $this->groupManager->getParentGroups($child_id);
        $this->assertCount(1, $parents);
        $this->assertEquals($parent_id, $parents[0]['id']);

        $children = $this->groupManager->getChildGroups($parent_id);
        $this->assertCount(1, $children);
        $this->assertEquals($child_id, $children[0]['id']);

        $this->groupManager->removeSubgroup($parent_id, $child_id);
        $parents_after = $this->groupManager->getParentGroups($child_id);
        $this->assertCount(0, $parents_after);
    }

    public function testGetUsersInGroupRecursively()
    {
        $user1_id = $this->userManager->create('user1', 'p', 'u1@t.com');
        $user2_id = $this->userManager->create('user2', 'p', 'u2@t.com');

        $parent_id = $this->groupManager->create('Parent');
        $child_id = $this->groupManager->create('Child');

        $this->groupManager->addSubgroup($parent_id, $child_id);
        $this->groupManager->addUserToGroup($user1_id, $parent_id);
        $this->groupManager->addUserToGroup($user2_id, $child_id);

        $users = $this->groupManager->getUsersInGroup($parent_id, true);
        $this->assertCount(2, $users);
    }

    public function testCircularDependencyInGroupsShouldThrowException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Circular dependency detected");

        // A -> B -> C
        $gA = $this->groupManager->create('GroupA');
        $gB = $this->groupManager->create('GroupB');
        $gC = $this->groupManager->create('GroupC');

        $this->groupManager->addSubgroup($gA, $gB);
        $this->groupManager->addSubgroup($gB, $gC);

        // Now try to add C -> A, which creates a loop
        $this->groupManager->addSubgroup($gC, $gA);
    }
}