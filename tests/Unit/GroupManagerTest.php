<?php

namespace RoleManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RoleManager\GroupManager;
use RoleManager\LoggerInterface;
use PDO;
use PDOStatement;
use Exception;

/**
 * Unit tests for the GroupManager class.
 *
 * These tests focus on logic that can be tested in isolation from the database,
 * such as the circular dependency check.
 */
class GroupManagerTest extends TestCase
{
    private $pdoMock;
    private $loggerMock;
    private $stmtMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
    }

    public function testAddSubgroupThrowsExceptionOnCircularDependency()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Circular dependency detected. Cannot add subgroup.");

        // The test simulates the GroupManager's `isCircularDependency` method,
        // which uses a single recursive SQL query.

        // 1. We expect a single call to prepare the recursive SQL query.
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WITH RECURSIVE SubgroupHierarchy'))
            ->willReturn($this->stmtMock);

        // 2. We expect the statement to be executed once with the correct parameters.
        // We are testing addSubgroup(3, 1), so child_id=1 and parent_id=3.
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([':child_id' => 1, ':parent_id' => 3]);

        // 3. We simulate that the query found a dependency by returning a count > 0.
        $this->stmtMock->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1);

        // 4. Instantiate the manager and attempt the operation that creates the loop.
        $groupManager = new GroupManager($this->pdoMock, $this->loggerMock);
        $groupManager->addSubgroup(3, 1); // Try to add group A(1) as a child of group C(3)
    }
}