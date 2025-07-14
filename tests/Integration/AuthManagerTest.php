<?php

namespace RoleManager\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

class AuthManagerTest extends TestCase
{
    use TestSetupTrait;

    private static $pdo;
    private $roleManager;
    private $authManager;

    public static function setUpBeforeClass(): void {
        self::$pdo = self::createPdo();
    }

    protected function setUp(): void
    {
        // Start a transaction before each test
        self::$pdo->beginTransaction();
        self::createSchema(self::$pdo); // Create schema for each test for full isolation
        $this->roleManager = new \RoleManager\RoleManager(self::$pdo);
        $this->authManager = $this->roleManager->auth();
    }

    protected function tearDown(): void
    {
        // Rollback the transaction after each test to keep the DB clean
        self::$pdo->rollBack();
    }

    /**
     * Seeds the database with a complex set of data for testing all scenarios.
     */
    private function seedDatabase() {
        // Using direct SQL for speed and simplicity in tests
        // Users
        self::$pdo->exec("INSERT INTO role_manager_users (id, login, password_hash, email) VALUES (1, 'test_user', 'hash', 'user@test.com')");

        // Groups (with hierarchy: AllStaff -> Editors -> Proofreaders)
        self::$pdo->exec("INSERT INTO role_manager_groups (id, name) VALUES (10, 'AllStaff'), (11, 'Editors'), (12, 'Proofreaders')");
        self::$pdo->exec("INSERT INTO role_manager_group_subgroups (parent_group_id, child_group_id) VALUES (10, 11)"); // AllStaff -> Editors
        self::$pdo->exec("INSERT INTO role_manager_group_subgroups (parent_group_id, child_group_id) VALUES (11, 12)"); // Editors -> Proofreaders

        // User in the most specific group
        self::$pdo->exec("INSERT INTO role_manager_user_groups (user_id, group_id) VALUES (1, 12)"); // test_user is a Proofreader

        // Contexts
        self::$pdo->exec("INSERT INTO role_manager_contexts (id, name) VALUES (100, 'Blog'), (101, 'Forum')");

        // Rights
        self::$pdo->exec("INSERT INTO role_manager_rightgroups (id, name) VALUES (1, 'content')");
        self::$pdo->exec("INSERT INTO role_manager_rights (id, name, rightgroup_id, type) VALUES (1000, 'edit_article', 1, 'boolean')");
        self::$pdo->exec("INSERT INTO role_manager_rights (id, name, rightgroup_id, type) VALUES (1001, 'delete_article', 1, 'boolean')");
        self::$pdo->exec("INSERT INTO role_manager_righttype_ranges (id, name, min_value, max_value) VALUES (1, 'budget', 0, 5000)");
        self::$pdo->exec("INSERT INTO role_manager_rights (id, name, rightgroup_id, type, righttype_range_id) VALUES (1002, 'approve_budget', 1, 'range', 1)");

        // Roles and Role-Rights
        self::$pdo->exec("INSERT INTO role_manager_roles (id, name) VALUES (10, 'UserSpecificRole'), (11, 'ProofreaderRole'), (12, 'EditorRole'), (13, 'StaffRole'), (14, 'GlobalRole')");
        self::$pdo->exec("INSERT INTO role_manager_role_rights (role_id, right_id) VALUES (10, 1000)"); // UserSpecificRole -> edit_article
        self::$pdo->exec("INSERT INTO role_manager_role_rights (role_id, right_id) VALUES (11, 1001)"); // ProofreaderRole -> delete_article
        self::$pdo->exec("INSERT INTO role_manager_role_rights (role_id, right_id, range_value) VALUES (12, 1002, 1000.00)"); // EditorRole -> approve_budget=1000
        self::$pdo->exec("INSERT INTO role_manager_role_rights (role_id, right_id, range_value) VALUES (13, 1002, 500.00)");  // StaffRole -> approve_budget=500
        self::$pdo->exec("INSERT INTO role_manager_role_rights (role_id, right_id, range_value) VALUES (14, 1002, 100.00)");  // GlobalRole -> approve_budget=100
    }

    /**
     * @test
     * Rule: Direct user assignment wins over group assignment.
     */
    public function directUserAssignmentShouldWinOverGroupAssignment()
    {
        $this->seedDatabase();
        // Assign a role with 'edit_article' to the user directly
        self::$pdo->exec("INSERT INTO role_manager_user_context_roles (user_id, role_id, context_id) VALUES (1, 10, 100)");
        // Assign a different role to the group
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (12, 11, 100)");

        $explanation = $this->authManager->explainRight(1, 'edit_article', 100);

        $this->assertTrue($explanation['decision'], 'The final decision should be true.');
        $this->assertEquals('UserSpecificRole', $explanation['trace'][0]['role'], 'The winning role should be the one assigned to the user.');
    }

    /**
     * @test
     * Rule: A right in a specific context wins over a global one.
     */
    public function specificContextShouldWinOverGlobalContext()
    {
        $this->seedDatabase();
        // Assign a role with approve_budget=1000 in the 'Blog' context
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (11, 12, 100)");
        // Assign a role with approve_budget=100 in the global context
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (10, 14, NULL)");

        $explanation = $this->authManager->explainRight(1, 'approve_budget', 100);

        $this->assertEquals('1000.00', $explanation['value']);
        $this->assertEquals('Blog', $explanation['trace'][0]['context']);
    }

    /**
     * @test
     * Rule: A right from a closer group in the hierarchy wins.
     */
    public function closerGroupInHierarchyShouldWin()
    {
        $this->seedDatabase();
        // Assign a role with approve_budget=1000 to the 'Editors' group (distance 1)
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (11, 12, 100)");
        // Assign a role with approve_budget=500 to the 'AllStaff' group (distance 2)
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (10, 13, 100)");

        // The user is in 'Proofreaders', which is a child of 'Editors', which is a child of 'AllStaff'.
        // 'Editors' is closer than 'AllStaff'.
        $explanation = $this->authManager->explainRight(1, 'approve_budget', 100);

        $this->assertEquals('1000.00', $explanation['value']);
        $this->assertEquals('Editors', $explanation['trace'][0]['source']);
    }

    /**
     * @test
     * Rule: In a tie, the highest range value wins.
     */
    public function tieBreakingRuleShouldSelectHighestRangeValue()
    {
        $this->seedDatabase();
        // Add user to a second group at the same level as 'Proofreaders'
        self::$pdo->exec("INSERT INTO role_manager_groups (id, name) VALUES (13, 'Contributers')");
        self::$pdo->exec("INSERT INTO role_manager_group_subgroups (parent_group_id, child_group_id) VALUES (11, 13)"); // Editors -> Contributers
        self::$pdo->exec("INSERT INTO role_manager_user_groups (user_id, group_id) VALUES (1, 13)"); // user is also a Contributer

        // Assign a role with approve_budget=500 to 'Proofreaders'
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (12, 13, 100)");
        // Assign a role with approve_budget=1000 to 'Contributers'
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (13, 12, 100)");

        // Both groups have the same specificity (distance 1 from user's perspective via 'Editors' parent)
        $explanation = $this->authManager->explainRight(1, 'approve_budget', 100);

        $this->assertEquals('1000.00', $explanation['value']);
        $this->assertEquals('Contributers', $explanation['trace'][0]['source']);
    }

    /**
     * @test
     */
    public function hasRightShouldReturnCorrectValueByReference()
    {
        $this->seedDatabase();
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (11, 12, 100)"); // approve_budget=1000

        $value = null;
        $hasRight = $this->authManager->hasRight(1, 'approve_budget', 100, $value);

        $this->assertTrue($hasRight);
        $this->assertEquals('1000.00', $value);
    }

    /**
     * @test
     */
    public function explainRightShouldShowAllTraces()
    {
        $this->seedDatabase();
        // Specific context assignment
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (11, 12, 100)"); // approve_budget=1000
        // Global context assignment
        self::$pdo->exec("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (10, 14, NULL)"); // approve_budget=100

        $explanation = $this->authManager->explainRight(1, 'approve_budget', 100);

        $this->assertCount(2, $explanation['trace']);
        $appliedRule = array_filter($explanation['trace'], fn($r) => $r['status'] === 'APPLIED');
        $overriddenRule = array_filter($explanation['trace'], fn($r) => $r['status'] === 'OVERRIDDEN');

        $this->assertCount(1, $appliedRule, 'There should be exactly one applied rule.');
        $this->assertCount(1, $overriddenRule, 'There should be exactly one overridden rule.');
    }

    /**
     * @test
     */
    public function authenticateReturnsUserDataOnSuccessAndFalseOnFailure()
    {
        // We need a real user created via the manager to get a valid password hash
        $userManager = $this->roleManager->users();
        $userManager->create('gooduser', 'password123', 'good@test.com');

        // 1. Test successful login
        $userData = $this->authManager->authenticate('gooduser', 'password123');
        $this->assertIsArray($userData, 'Should return user data array on success.');
        $this->assertEquals('gooduser', $userData['login']);

        // 2. Test failed login (wrong password)
        $loginResultWrongPass = $this->authManager->authenticate('gooduser', 'wrongpassword');
        $this->assertFalse($loginResultWrongPass, 'Should return false on wrong password.');

        // 3. Test failed login (non-existent user)
        $loginResultWrongUser = $this->authManager->authenticate('baduser', 'password123');
        $this->assertFalse($loginResultWrongUser, 'Should return false for non-existent user.');
    }
}
