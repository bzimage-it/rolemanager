# RoleManager Extended API Manual

## Introduction

Welcome to the extended API documentation for the **RoleManager PHP Library**. This document provides a comprehensive guide for developers looking to integrate and leverage the library's powerful features for role-based access control (RBAC).

RoleManager is designed to offer a flexible and robust solution for managing permissions in complex applications. It's built around a few core concepts: **Users**, **Groups**, **Rights**, **Roles**, and **Contexts**. Users can be organized into hierarchical groups. Rights (permissions) are bundled into Roles, which can then be assigned to users or groups. The assignment can be **global** (valid everywhere) or tied to a specific **context** (e.g., a project, a sub-site, a specific module), allowing for fine-grained control over user permissions.

The library features a clear permission precedence system to resolve conflicts, high-performance permission checks through a multi-level caching system, and a unique diagnostic tool, `explainRight()`, which provides full transparency into how a permission decision is reached.

---

## Getting Started

### 1. Database Setup

Before using the library, you need to set up the necessary database tables. The library package includes a `rolemanager-create.sql` file containing all the required `CREATE TABLE` statements for a MySQL database. Execute this script in your target database to prepare the schema. All tables created by the script are prefixed with `role_manager_`.

### 2. Library Initialization

The main entry point to the library is the `RoleManager\RoleManager` class. To get started, you need a connected `PDO` object.

```php
<?php

require_once 'vendor/autoload.php';

use RoleManager\RoleManager;

// Establish your database connection (PDO)
try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_db_name', 'your_user', 'your_password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create an instance of the main RoleManager class
// This object acts as a factory for all other managers.
$roleManager = new RoleManager($pdo);

?>
```

---

## Core Concepts in Detail

### Entities

*   **User**: An individual entity that can be authenticated and assigned roles. Identified by a unique login.
*   **Group**: A collection of users and/or other groups. Supports nesting to create hierarchies.
*   **Right**: A single, specific permission (e.g., `can_edit_post`). Rights can be boolean (`true`/`false`) or range-based (an integer value).
*   **Role**: A named collection of rights. A role acts as a template, defining what a user or group holding that role is allowed to do.
*   **Context**: A specific area or scope within your application where a role assignment is valid. Examples include "Project Alpha," "Admin Panel," or "Marketing Department Blog."

### The Global Context

A role can be assigned without a specific context. This is called a **Global Context** assignment. A right granted in the Global Context is valid everywhere unless it is overridden by an assignment in a more specific context.

### Permission Precedence Rules

When a user has multiple, potentially conflicting rights, the library uses a strict hierarchy to determine the outcome:

1.  **Context Specificity**: A right assigned in a specific context **always** overrides a right from the Global Context.
2.  **Assignee Specificity**: Within the same context, a right assigned directly to a user **always** overrides a right inherited from a group.
3.  **Group Specificity**: If a user is in multiple nested groups (e.g., `User` -> `Group A` -> `Group B`), the right from the "closest" group (`Group A`) wins.
4.  **Tie-Breaking**: In the rare case of a perfect tie (e.g., a user is a direct member of two separate groups with the same right), the highest value wins for `range` type rights. For boolean rights, the outcome is `true`.

---

## API Reference

### `RoleManager` (Main Class)

This class is the main factory and entry point for accessing all the library's functionalities.

#### `__construct(PDO $pdo, ...)`

Instantiates the RoleManager.

```php
$roleManager = new RoleManager($pdo);
```

#### Factory Methods

The `RoleManager` class provides a set of factory methods to get instances of the specialized managers for each entity.

```php
// Get the manager for Users
$userManager = $roleManager->users();

// Get the manager for Groups
$groupManager = $roleManager->groups();

// Get the manager for Rights
$rightManager = $roleManager->rights();

// Get the manager for Right Groups
$rightGroupManager = $roleManager->rightGroups();

// Get the manager for Roles
$roleService = $roleManager->roles();

// Get the manager for Contexts
$contextManager = $roleManager->contexts();

// Get the manager for Authentication and Authorization (Permission Checks)
$authManager = $roleManager->auth();
```

---

### `UserManager`

Handles all CRUD operations related to users.

#### `create(string $login, string $password, string $email, ?string $firstName = null, ?string $lastName = null): int`

Creates a new user and returns their unique ID. The password is automatically and securely hashed.

**Simple Example: Creating a user with minimal information**
```php
$userManager = $roleManager->users();
$newUserId = $userManager->create(
    'j.doe',
    'S3cuRe!pA$$w0rd',
    'john.doe@example.com'
);

echo "User created with ID: {$newUserId}";
```

**Complex Example: Creating a user with all optional fields**
```php
$adminId = $userManager->create(
    'superadmin',
    'AnotherVery-Secure-Password123!',
    'admin@mycompany.com',
    'Alice',
    'Wonderland'
);

echo "Admin user '{$adminId}' created.";
```

#### `getById(int $userId): ?array`

Retrieves a user's data by their ID. Returns `null` if not found.

```php
$user = $userManager->getById($newUserId);

if ($user) {
    print_r($user);
} else {
    echo "User not found.";
}
```

#### `getByLogin(string $login): ?array`

Retrieves a user's data by their login string. Returns `null` if not found.

```php
$user = $userManager->getByLogin('j.doe');

if ($user) {
    echo "User ID for j.doe is: " . $user['id'];
}
```

#### `update(int $userId, array $data): bool`

Updates a user's data. The `$data` array can contain any of the user fields (`login`, `password`, `email`, `first_name`, `last_name`). Returns `true` on success.

**Simple Example: Updating an email address**
```php
$userManager->update($newUserId, ['email' => 'john.doe.new@example.com']);
```

**Complex Example: Updating multiple fields, including the password**
```php
$updates = [
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'password'   => 'a_new_even_more_secure_password'
];
$userManager->update($newUserId, $updates);
```

#### `delete(int $userId): bool`

Deletes a user. The operation will fail if the user has dependencies (e.g., active role assignments) to maintain data integrity. Returns `true` on success.

```php
try {
    $wasDeleted = $userManager->delete($userId);
    if ($wasDeleted) {
        echo "User deleted successfully.";
    }
} catch (Exception $e) {
    // This will likely be a PDOException due to foreign key constraints
    echo "Could not delete user. They may have active roles or group memberships. Error: " . $e->getMessage();
}
```

#### `list(): array`

Returns an array of all users in the system.

```php
$allUsers = $userManager->list();
foreach ($allUsers as $user) {
    echo "Login: " . $user['login'] . "\n";
}
```

---

### `GroupManager`

Manages groups and their relationships with users and other groups.

#### `create(string $name, ?string $description = null): int`

Creates a new group and returns its ID.

```php
$groupManager = $roleManager->groups();
$editorsGroupId = $groupManager->create('Editors', 'Users who can create and edit content.');
$moderatorsGroupId = $groupManager->create('Moderators', 'Users who can approve/reject content.');
```

#### `addUserToGroup(int $userId, int $groupId): bool`

Adds a user to a group.

```php
$groupManager->addUserToGroup($newUserId, $editorsGroupId);
```

#### `removeUserFromGroup(int $userId, int $groupId): bool`

Removes a user from a group.

```php
$groupManager->removeUserFromGroup($newUserId, $editorsGroupId);
```

#### `addSubgroup(int $parentGroupId, int $childGroupId): bool`

Nests one group inside another. The library automatically prevents circular dependencies (e.g., adding a parent group as a child of one of its own descendants).

**Simple Example: Two-level hierarchy**
```php
// Let's say all Moderators are also Editors.
$groupManager->addSubgroup($editorsGroupId, $moderatorsGroupId);
```

**Complex Example: Preventing a circular dependency**
```php
try {
    // This should fail, because Moderators are already a subgroup of Editors.
    // You cannot make the parent a child of its own child.
    $groupManager->addSubgroup($moderatorsGroupId, $editorsGroupId);
} catch (Exception $e) {
    echo "Caught expected error: " . $e->getMessage();
}
```

#### `getUsers(int $groupId, bool $recursive = false): array`

Retrieves the users in a group.

**Simple Example: Get direct members only**
```php
$userId1 = $userManager->create('user1', 'pass', 'user1@ex.com');
$userId2 = $userManager->create('user2', 'pass', 'user2@ex.com');
$groupManager->addUserToGroup($userId1, $moderatorsGroupId);

$directModerators = $groupManager->getUsers($moderatorsGroupId, false);
echo "Direct moderators: " . count($directModerators); // Should be 1
```

**Complex Example: Get all members recursively**
```php
$groupManager->addUserToGroup($userId2, $editorsGroupId);

// Since Moderators are a subgroup of Editors, a recursive search on Editors
// should find both user1 (from Moderators) and user2 (from Editors).
$allEditorsRecursive = $groupManager->getUsers($editorsGroupId, true);
echo "Total users in editor hierarchy: " . count($allEditorsRecursive); // Should be 2
```

#### `isUserInGroup(int $userId, int $groupId, bool $recursive = true): bool`

Checks if a user is a member of a group, recursively by default.

```php
// $userId1 was added to Moderators, which is a subgroup of Editors.
if ($groupManager->isUserInGroup($userId1, $editorsGroupId)) {
    echo "User 1 is considered an Editor through recursion.";
}

// Check for direct membership only
if (!$groupManager->isUserInGroup($userId1, $editorsGroupId, false)) {
    echo "User 1 is not a *direct* member of Editors.";
}
```

---

### `RightManager` & `RightGroupManager`

These managers handle the creation and management of individual permissions (Rights) and their categories (RightGroups).

#### `RightGroupManager::create(string $name, ?string $description = null): int`

Creates a new group for rights.

```php
$rightGroupManager = $roleManager->rightGroups();
$contentMgmtGroupId = $rightGroupManager->create('Content Management', 'Rights related to content creation and publishing.');
$userMgmtGroupId = $rightGroupManager->create('User Management', 'Rights for managing users and groups.');
```

#### `RightManager::create(string $name, string $description, int $rightGroupId, string $type, ?array $options = null): int`

Creates a new right.
*   `$type`: Can be `'boolean'` or `'range'`.
*   `$options`: For `range` types, this should be an array like `['min' => 0, 'max' => 100]`.

**Simple Example: Creating a boolean right**
```php
$rightManager = $roleManager->rights();
$editArticleRightId = $rightManager->create(
    'edit_article',
    'Allows editing an article',
    $contentMgmtGroupId,
    'boolean'
);
```

**Complex Example: Creating a range-based right**
```php
$moderationLevelRightId = $rightManager->create(
    'moderation_level',
    'Defines the user moderation power level',
    $userMgmtGroupId,
    'range',
    ['min' => 1, 'max' => 10]
);
```

---

### `RoleService`

Manages Roles, which are collections of rights.

#### `create(string $name, ?string $description = null): int`

Creates a new, empty role.

```php
$roleService = $roleManager->roles();
$editorRoleId = $roleService->create('Editor', 'Basic content editor role');
$superModeratorRoleId = $roleService->create('Super Moderator', 'Full moderation powers');
```

#### `addRightToRole(int $roleId, int $rightId, ?int $value = null): bool`

Adds a right to a role. For range-based rights, a specific value must be provided.

**Simple Example: Adding a boolean right**
```php
// The 'Editor' role gets the 'edit_article' right.
// For boolean rights, the value is omitted. Their presence implies 'true'.
$roleService->addRightToRole($editorRoleId, $editArticleRightId);
```

**Complex Example: Adding a range-based right**
```php
// The 'Super Moderator' role also gets to edit articles.
$roleService->addRightToRole($superModeratorRoleId, $editArticleRightId);

// And they get the highest moderation level.
$roleService->addRightToRole($superModeratorRoleId, $moderationLevelRightId, 10);
```

#### `getRightsForRole(int $roleId): array`

Retrieves all rights associated with a role.

```php
$rights = $roleService->getRightsForRole($superModeratorRoleId);
print_r($rights);
/*
Array
(
    [0] => Array
        (
            [name] => edit_article
            [value] => 1
        )
    [1] => Array
        (
            [name] => moderation_level
            [value] => 10
        )
)
*/
```

---

### `ContextManager`

Manages contexts and the assignment of roles to users and groups within those contexts.

#### `create(string $name, ?string $description = null): int`

Creates a new context.

```php
$contextManager = $roleManager->contexts();
$blogContextId = $contextManager->create('Main Blog', 'The company public blog.');
$forumContextId = $contextManager->create('Community Forum', 'Public discussion forum.');
```

#### `assignRoleToGroup(int $groupId, int $roleId, ?int $contextId = null): bool`

Assigns a role to a group. If `$contextId` is `null`, the assignment is made in the **Global Context**.

**Simple Example: Assigning a role in a specific context**
```php
// The 'Editors' group gets the 'Editor' role, but only within the 'Main Blog' context.
$contextManager->assignRoleToGroup($editorsGroupId, $editorRoleId, $blogContextId);
```

**Complex Example: Assigning a role globally**
```php
$adminUserId = $userManager->create('global_admin', 'pass', 'ga@ex.com');
$adminRoleId = $roleService->create('Site Administrator');
// ... add rights to admin role ...

// Assign the 'Site Administrator' role to the admin user globally.
// This user will have these permissions in the blog, the forum, and everywhere else.
$contextManager->assignRoleToUser($adminUserId, $adminRoleId, null); // null for Global Context
```

#### `assignRoleToUser(int $userId, int $roleId, ?int $contextId = null): bool`

Assigns a role directly to a user. This is useful for granting permissions that override group-based permissions.

```php
$powerUserRoleId = $roleService->create('Power User');
// ... add rights to power user role ...

$powerUserId = $userManager->create('p.user', 'pass', 'pu@ex.com');

// Grant this user 'Power User' permissions, but only in the forums.
$contextManager->assignRoleToUser($powerUserId, $powerUserRoleId, $forumContextId);
```

---

### `AuthManager` (Authentication & Permissions)

This is the high-performance engine for checking user credentials and permissions. All permission checks are heavily cached to minimize database queries.

#### `authenticate(string $login, string $password): ?array`

Verifies a user's login and password. On success, it returns an array of the user's data (`id`, `login`, `email`, etc.), which can be used as a payload for a JWT or session. On failure, it returns `null`. This method **does not** handle session management.

**Simple Example: Successful Authentication**
```php
$authManager = $roleManager->auth();
$userData = $authManager->authenticate('j.doe', 'S3cuRe!pA$$w0rd');

if ($userData) {
    echo "Authentication successful for user: " . $userData['login'];
    // Now, the calling application can create a session or JWT with $userData
    // For example: $_SESSION['user'] = $userData;
} else {
    echo "Authentication failed.";
}
```

**Complex Example: Handling Failed Authentication**
```php
$invalidUserData = $authManager->authenticate('j.doe', 'wrong_password');

if ($invalidUserData === null) {
    echo "Invalid credentials, access denied.";
    // Log the failed attempt, etc.
}
```

#### `hasRight(int $userId, string $rightName, ?int $contextId = null): bool|int`

Checks if a user has a specific right. This is the primary method for permission checking.

*   For **boolean** rights, returns `true` or `false`.
*   For **range** rights, returns the resolved integer value or `false` if the user doesn't have the right.
*   If `$contextId` is `null`, the check is performed for the Global Context only.

**Simple Example: Checking a boolean right**
```php
// Does the user with ID $newUserId have the 'edit_article' right in the blog?
// Let's assume this user is in the 'Editors' group.
$canEdit = $authManager->hasRight($newUserId, 'edit_article', $blogContextId);

if ($canEdit) {
    echo "User can edit articles in the blog."; // This should be true.
}
```

**Complex Example: Checking a range-based right**
```php
$modLevel = $authManager->hasRight($superAdminId, 'moderation_level', $forumContextId);

if ($modLevel !== false) {
    echo "User's moderation level is: {$modLevel}"; // Should be 10 if they have the Super Moderator role.
} else {
    echo "User does not have a moderation level.";
}
```

**More Examples: Context Precedence**
```php
// Scenario: A user is in a 'Global Readers' group with 'max_posts_per_day' = 5 (range right).
// But they are also in a 'Premium Members' group within the 'Forum' context with 'max_posts_per_day' = 50.

// Check globally (will not see the forum-specific role)
$globalLimit = $authManager->hasRight($userId, 'max_posts_per_day', null);
echo "Global post limit: {$globalLimit}"; // Expected: 5

// Check within the Forum context
$forumLimit = $authManager->hasRight($userId, 'max_posts_per_day', $forumContextId);
echo "Forum post limit: {$forumLimit}"; // Expected: 50 (Context-specific wins)
```

#### `explainRight(int $userId, string $rightName, ?int $contextId = null): array`

Provides a detailed explanation of how a permission decision was reached. It returns a data structure containing the final decision, the winning rule, and a full trace of all considered assignments. This is invaluable for debugging and admin interfaces.

**Simple Example: Explaining a direct, simple right**
```php
// Assign a 'Blogger' role directly to a user in the blog context.
$bloggerRoleId = $roleService->create('Blogger');
$postCommentRightId = $rightManager->create('post_comment', 'Can post comments', $contentMgmtGroupId, 'boolean');
$roleService->addRightToRole($bloggerRoleId, $postCommentRightId);
$contextManager->assignRoleToUser($someUserId, $bloggerRoleId, $blogContextId);

$explanation = $authManager->explainRight($someUserId, 'post_comment', $blogContextId);
print_r($explanation);
```
*Expected Output:*
```json
{
    "decision": true,
    "value": 1,
    "reason": "Right 'post_comment' granted by direct user assignment of role 'Blogger' in context 'Main Blog'.",
    "winning_rule": {
        "source_type": "user",
        "source_name": "some_user_login",
        "role_name": "Blogger",
        "context_name": "Main Blog",
        "precedence": 30
    },
    "trace": [
        {
            "status": "APPLIED",
            "source_type": "user",
            /* ... more details ... */
        }
    ]
}
```

**Complex Example: Explaining an inherited and overridden right**
```php
// Scenario:
// 1. A 'Basic User' role is assigned Globally to the 'Everyone' group, with 'max_file_upload_kb' = 1024.
// 2. A 'Pro User' role is assigned to the 'Subscribers' group in the 'Main Blog' context, with 'max_file_upload_kb' = 10240.
// 3. User 'bob' is a member of 'Everyone' and 'Subscribers'.

$explanation = $authManager->explainRight($bobId, 'max_file_upload_kb', $blogContextId);
print_r($explanation);
```
*Expected Output:*
```json
{
    "decision": true,
    "value": 10240,
    "reason": "Right 'max_file_upload_kb' granted by role 'Pro User' from group 'Subscribers' in context 'Main Blog'.",
    "winning_rule": {
        "source_type": "group",
        "source_name": "Subscribers",
        "role_name": "Pro User",
        "context_name": "Main Blog",
        "precedence": 20
    },
    "trace": [
        {
            "status": "APPLIED",
            "reason": "This rule won due to higher context specificity.",
            "source_type": "group",
            "source_name": "Subscribers",
            "role_name": "Pro User",
            "context_name": "Main Blog",
            "value": 10240
        },
        {
            "status": "IGNORED",
            "reason": "Overridden by a rule with higher context specificity.",
            "source_type": "group",
            "source_name": "Everyone",
            "role_name": "Basic User",
            "context_name": "Global",
            "value": 1024
        }
    ]
}
```

---

## Advanced Topics

### Caching and Performance

The `AuthManager` is designed for high performance. It uses a two-level cache to avoid redundant database queries during permission checks.

1.  **In-Memory Cache**: A user's complete permission set for a given context is calculated from the database only once per PHP script execution. Subsequent checks within the same request hit this memory cache.
2.  **Persistent Cache**: The calculated permissions are stored in a fast, persistent cache (like APCu, if available) to be shared across different HTTP requests.

This system is completely transparent to the developer. The cache is automatically invalidated whenever a CRUD operation that could affect permissions occurs (e.g., changing a role, adding a user to a group). This is managed by a global `permissions_version` counter in the database.

### Logging

The library includes a simple, self-contained logging class. A logger instance can be passed to the `RoleManager` constructor. It supports standard log levels (debug, info, error, etc.) and can be configured to write to the console, a database table (`role_manager_logs`), or both.

**Example: Initializing with a Logger**

```php
use RoleManager\Logger\DatabaseLogger;
use RoleManager\RoleManager;

// Assume DatabaseLogger is a class implementing a LoggerInterface
// It would take the PDO connection to write to the log table.
$logger = new DatabaseLogger($pdo, 'info'); // Log 'info' level and above to DB

$roleManager = new RoleManager($pdo, $logger);

// Now, all internal operations within the library will be logged
// according to the logger's configuration.
```

**Example: Using the Logger Directly**

While the primary use is for internal library logging, you could potentially expose the logger for application use if needed.

```php
// This is a conceptual example, assuming you can get the logger instance.
$logger = $roleManager->getLogger();

// Log a message to the configured channels (e.g., console, DB)
$logger->info('User authentication process started for {login}', ['login' => 'j.doe']);

// Log a critical error only to the database
$logger->critical(
    'Failed to connect to external payment service',
    ['exception' => $e],
    true // Direct to DB only
);
```

---