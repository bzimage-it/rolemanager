<?php

namespace RoleManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RoleManager\RoleManager_Roles;
use RoleManager\LoggerInterface;
use PDO;
use PDOStatement;
use Exception;

/**
 * Unit tests for the RoleManager_Roles class.
 *
 * These tests verify the validation logic within the class by mocking
 * database interactions.
 */
class RoleManager_RolesTest extends TestCase
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

    public function testAddRightToRoleThrowsExceptionForBooleanRightWithValue()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A value must not be provided for a 'boolean' type right.");

        // 1. Mock the query that fetches right information
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT r.type, rr.min_value, rr.max_value'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())->method('execute')->with([1]); // right_id = 1

        // 2. Simulate the DB returning a 'boolean' right
        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn(['type' => 'boolean', 'min_value' => null, 'max_value' => null]);

        // 3. Instantiate and call the method with a value, which should fail
        $rolesManager = new RoleManager_Roles($this->pdoMock, $this->loggerMock);
        $rolesManager->addRightToRole(1, 1, 50); // role_id=1, right_id=1, value=50
    }

    public function testAddRightToRoleThrowsExceptionForRangeRightWithoutValue()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A value is required for a 'range' type right.");

        // 1. Mock the query that fetches right information
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT r.type, rr.min_value, rr.max_value'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())->method('execute')->with([2]); // right_id = 2

        // 2. Simulate the DB returning a 'range' right
        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn(['type' => 'range', 'min_value' => '0.00', 'max_value' => '100.00']);

        // 3. Instantiate and call the method with a null value, which should fail
        $rolesManager = new RoleManager_Roles($this->pdoMock, $this->loggerMock);
        $rolesManager->addRightToRole(1, 2, null);
    }

    public function testAddRightToRoleThrowsExceptionForValueOutOfRange()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Value 150 is out of the allowed range (0.00 - 100.00) for this right type.");

        // 1. Mock the query that fetches right information
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT r.type, rr.min_value, rr.max_value'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())->method('execute')->with([2]); // right_id = 2

        // 2. Simulate the DB returning a 'range' right with a defined range
        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'type' => 'range',
                'min_value' => '0.00',
                'max_value' => '100.00'
            ]);

        // 3. Instantiate and call the method with the out-of-range value
        $rolesManager = new RoleManager_Roles($this->pdoMock, $this->loggerMock);
        $rolesManager->addRightToRole(1, 2, 150);
    }
}