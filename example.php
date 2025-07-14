<?php

/**
 * RoleManager - A Comprehensive Usage Example
 *
 * This script demonstrates the core functionalities of the RoleManager library.
 * It sets up an in-memory SQLite database, creates a schema, and then populates it
 * with a realistic scenario involving users, hierarchical groups, roles, and contexts.
 * Finally, it runs a series of permission checks to showcase the powerful precedence rules.
 */

require_once __DIR__ . '/vendor/autoload.php';

use RoleManager\RoleManager;

function print_header(string $title): void {
    echo "\n\n";
    echo "=====================================================================\n";
    echo "{$title}\n";
    echo "=====================================================================\n";
}

// =============================================================================
// 1. SETUP
// =============================================================================

print_header("1. SETUP");
echo "Initializing in-memory database and RoleManager...\n";

// For this example, we use an in-memory SQLite database.
// In a real application, you would connect to your persistent MySQL/PostgreSQL database.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create the database schema.
$schemaSql = file_get_contents(__DIR__ . '/tests/rolemanager-create.sqlite.sql');
$pdo->exec($schemaSql);

// Initialize the main RoleManager class.
$roleManager = new RoleManager($pdo);

// Get the specific managers we will use.
$userManager = $roleManager->users();
$groupManager = $roleManager->groups();
$rightManager = $roleManager->rights();
$roleManager_Roles = $roleManager->roles();
$contextManager = $roleManager->contexts();
$authManager = $roleManager->auth();

echo "Setup complete.";

// =============================================================================
// 2. POPULATE DATA
// =============================================================================

print_header("2. DATA POPULATION");
echo "Creating a scenario: a publishing house with staff, editors, and proofreaders.\n";

// --- 2.1. Create Users ---
echo " -> Creating users: Alice, Bob, Charlie...\n";
$aliceId = $userManager->create('alice', 'pass123', 'alice@example.com', 'Alice');     // Alice is an Editor
$bobId = $userManager->create('bob', 'pass123', 'bob@example.com', 'Bob');           // Bob is a Proofreader
$charlieId = $userManager->create('charlie', 'pass123', 'charlie@example.com', 'Charlie'); // Charlie is a generic Staff member and Manager
$davidId = $userManager->create('david', 'pass123', 'david@example.com', 'David');       // David is in Marketing

// --- 2.2. Create Groups ---
echo " -> Creating groups with hierarchy (Staff > Editors > Proofreaders) and a separate 'Marketing' group...\n";
$staffGroupId = $groupManager->create('Staff');
$editorsGroupId = $groupManager->create('Editors');
$proofreadersGroupId = $groupManager->create('Proofreaders');
$marketingGroupId = $groupManager->create('Marketing');
$groupManager->addSubgroup($staffGroupId, $editorsGroupId);
$groupManager->addSubgroup($editorsGroupId, $proofreadersGroupId);

// --- 2.3. Assign users to groups ---
$groupManager->addUserToGroup($aliceId, $editorsGroupId);
$groupManager->addUserToGroup($aliceId, $marketingGroupId); // Alice is also in Marketing
$groupManager->addUserToGroup($bobId, $proofreadersGroupId);
$groupManager->addUserToGroup($charlieId, $staffGroupId);
$groupManager->addUserToGroup($davidId, $marketingGroupId);

// --- 2.4. Define Rights and Roles ---
echo " -> Defining rights (view, edit, publish, delete, approve_budget)...\n";
$contentRightGroupId = $roleManager->rightGroups()->create('Content');
$financeRightGroupId = $roleManager->rightGroups()->create('Finance');
$budgetRangeId = $roleManager->rightTypes()->create('Budget', 'Budget approval limit', 0, 10000);

$viewArticleRightId    = $rightManager->create('view_article', 'Can view articles', $contentRightGroupId, 'boolean');
$editArticleRightId    = $rightManager->create('edit_article', 'Can edit articles', $contentRightGroupId, 'boolean');
$publishArticleRightId = $rightManager->create('publish_article', 'Can publish articles', $contentRightGroupId, 'boolean');
$deleteArticleRightId  = $rightManager->create('delete_article', 'Can delete articles', $contentRightGroupId, 'boolean');
$approveBudgetRightId  = $rightManager->create('approve_budget', 'Can approve budget', $financeRightGroupId, 'range', $budgetRangeId);

echo " -> Creating roles (Reader, Proofreader, Editor, Manager, Intern, etc.)...\n";
$readerRoleId = $roleManager_Roles->create('Reader');
$roleManager_Roles->addRightToRole($readerRoleId, $viewArticleRightId);

$proofreaderRoleId = $roleManager_Roles->create('Proofreader');
$roleManager_Roles->addRightToRole($proofreaderRoleId, $editArticleRightId);

$editorRoleId = $roleManager_Roles->create('Editor', 'Can publish and approve small budgets');
$roleManager_Roles->addRightToRole($editorRoleId, $publishArticleRightId);
$roleManager_Roles->addRightToRole($editorRoleId, $approveBudgetRightId, 2000.00);

$managerRoleId = $roleManager_Roles->create('Manager', 'Can delete articles and approve medium budgets');
$roleManager_Roles->addRightToRole($managerRoleId, $deleteArticleRightId);
$roleManager_Roles->addRightToRole($managerRoleId, $approveBudgetRightId, 5000.00);

$juniorManagerRoleId = $roleManager_Roles->create('Junior Manager', 'Approves small budgets');
$roleManager_Roles->addRightToRole($juniorManagerRoleId, $approveBudgetRightId, 1000.00);

$marketingRoleId = $roleManager_Roles->create('Marketing Role', 'Can approve marketing budgets');
$roleManager_Roles->addRightToRole($marketingRoleId, $approveBudgetRightId, 2500.00);

$internRoleId = $roleManager_Roles->create('Intern', 'Can only view, nothing else');
$roleManager_Roles->addRightToRole($internRoleId, $viewArticleRightId);

// --- 2.5. Create Contexts ---
echo " -> Creating contexts (Project Alpha, Project Beta, Project Omega)...\n";
$projectAlphaId = $contextManager->create('Project Alpha');
$projectBetaId = $contextManager->create('Project Beta');
$projectOmegaId = $contextManager->create('Project Omega');

// --- 2.6. Assign Roles ---
echo " -> Assigning roles to groups and users in different contexts...\n";
// GLOBAL roles (lowest precedence)
$contextManager->assignRoleToGroup($staffGroupId, $readerRoleId, null);
$contextManager->assignRoleToGroup($proofreadersGroupId, $proofreaderRoleId, null); // Proofreaders can edit globally

// CONTEXT-SPECIFIC group roles
$contextManager->assignRoleToGroup($proofreadersGroupId, $proofreaderRoleId, $projectAlphaId);
$contextManager->assignRoleToGroup($editorsGroupId, $editorRoleId, $projectAlphaId);
$contextManager->assignRoleToGroup($marketingGroupId, $marketingRoleId, $projectAlphaId);

// CONTEXT-SPECIFIC user roles (highest precedence)
$contextManager->assignRoleToUser($aliceId, $juniorManagerRoleId, $projectBetaId);
$contextManager->assignRoleToUser($charlieId, $managerRoleId, null);

// Bob is an Intern in Project Omega, which should override his global proofreader rights.
$contextManager->assignRoleToUser($bobId, $internRoleId, $projectOmegaId);

echo "Data population complete.";

// =============================================================================
// 3. CHECK PERMISSIONS
// =============================================================================

print_header("3. PERMISSION CHECKS");

function run_permission_check(string $description, bool $result, ?string $value_msg = null): void {
    $status = $result ? "YES" : "NO";
    echo "Q: {$description}\n";
    echo "A: {$status}" . ($value_msg ? " ({$value_msg})" : "") . "\n\n";
}

echo "--- SCENARIO 1: Global and Hierarchical Inheritance ---\n";
run_permission_check(
    "Can Bob view articles? (YES: He is a Proofreader -> Editor -> Staff, and Staff has global 'Reader' role)",
    $authManager->hasRight($bobId, 'view_article', $projectAlphaId)
);
run_permission_check(
    "Can Alice publish in Project Alpha? (YES: She is in 'Editors' group, which has 'Editor' role in this context)",
    $authManager->hasRight($aliceId, 'publish_article', $projectAlphaId)
);
run_permission_check(
    "Can Bob publish in Project Alpha? (YES: He is a 'Proofreader', child of 'Editors', so he inherits the 'Editor' role in this context)",
    $authManager->hasRight($bobId, 'publish_article', $projectAlphaId)
);

echo "--- SCENARIO 2: Context-Specific Permissions ---\n";
run_permission_check(
    "Can Alice publish in Project Beta? (NO: Her 'Editor' role is only for Project Alpha)",
    $authManager->hasRight($aliceId, 'publish_article', $projectBetaId)
);

echo "--- SCENARIO 3: Precedence of Direct User Assignment ---\n";
$budget = null;
$hasRight = $authManager->hasRight($aliceId, 'approve_budget', $projectBetaId, $budget);
run_permission_check(
    "What is Alice's budget in Project Beta? (1000: Her direct 'Junior Manager' role wins over any group roles)",
    $hasRight,
    "Value: " . ($budget ?? 'N/A')
);

echo "--- SCENARIO 4: Implicit Denial by Specific Role ---\n";
run_permission_check(
    "Can Bob edit articles globally? (YES: His 'Proofreaders' group has a global role with this right)",
    $authManager->hasRight($bobId, 'edit_article')
);
run_permission_check(
    "Can Bob edit articles in Project Omega? (NO: His specific 'Intern' role in this context has no edit right, overriding his global right)",
    $authManager->hasRight($bobId, 'edit_article', $projectOmegaId)
);

echo "--- SCENARIO 5: Precedence Among Multiple Groups ---\n";
$budget = null;
$hasRight = $authManager->hasRight($aliceId, 'approve_budget', $projectAlphaId, $budget);
run_permission_check(
    "What is Alice's budget in Project Alpha? (2500: She is in 'Editors' (budget 2000) and 'Marketing' (budget 2500). Both are context-specific group roles with same specificity, so the highest value wins)",
    $hasRight,
    "Value: " . ($budget ?? 'N/A')
);

// =============================================================================
// 4. EXPLAIN PERMISSION
// =============================================================================

print_header("4. EXPLAINING A PERMISSION");

echo "Let's find out exactly why Alice has a budget of 2500 in Project Alpha.\n";
echo "She inherits from 'Editors' (2000) and 'Marketing' (2500).\n\n";
$explanation = $authManager->explainRight($aliceId, 'approve_budget', $projectAlphaId);

if ($explanation['decision']) {
    echo "--> DECISION: GRANTED\n";
    echo "--> FINAL VALUE: {$explanation['value']}\n";
    echo "--> WINNING RULE: {$explanation['reason']}\n\n";
    echo "Full Trace (all rules considered):\n";
    foreach ($explanation['trace'] as $trace) {
        $line = sprintf(
            "    - [Specificity: %-3d] Source: %-12s | Role: %-16s | Context: %-15s | Value: %-7s => %s",
            $trace['specificity'],
            $trace['source'],
            $trace['role'],
            $trace['context'],
            $trace['value'] ?? 'N/A',
            $trace['status']
        );
        echo $line . "\n";
    }
} else {
    echo "--> DECISION: DENIED\n";
    echo "--> REASON: {$explanation['reason']}\n";
}

print_header("Example script finished.");

?>