# RoleManager PHP Library

`RoleManager` is a comprehensive PHP library for managing users, groups, roles, and permissions with a high degree of flexibility. It supports hierarchical groups, contextual and global role assignments, and a transparent permission resolution system.

## Features

- **User and Group Management**: Full CRUD operations for users and groups.
- **Hierarchical Groups**: Groups can contain other groups, and permissions are inherited.
- **Contextual Permissions**: Assign roles globally or within specific contexts (e.g., a specific project, a forum, etc.).
- **Advanced Permission Resolution**: A clear precedence system resolves permission conflicts.
- **Transparent Debugging**: The `explainRight()` method shows exactly how a permission decision was made.
- **Performance-Oriented**: Built-in caching layers (in-memory and APCu) to ensure high performance for permission checks.

---

## Installation

This project uses Composer for dependency management. To add it to your project, update your `composer.json`:

```json
{
    "require": {
        "sebastiani/rolemanager": "dev-main"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-username/rolemanager"
        }
    ]
}
```

Then run:

```bash
composer install
```

---

## Setup

### 1. Database Schema

The library requires a specific database schema. You can use the provided SQL files to set it up:

- **MySQL**: `rolemanager-create.sql`
- **SQLite (for testing)**: `tests/rolemanager-create.sqlite.sql`

Execute the appropriate SQL file on your database.

### 2. Initializing the Library

All you need to get started is a PDO database connection.

```php
<?php

require_once 'vendor/autoload.php';

use RoleManager\RoleManager;

// 1. Establish your database connection (PDO)
try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 2. Create an instance of the main RoleManager class
$roleManager = new RoleManager($pdo);

?>
```

---

## Basic Usage Examples

The `RoleManager` class acts as a factory to access specific managers for each entity (users, groups, etc.).

### Managing Users

```php
$userManager = $roleManager->users();

// Create a new user
$userId = $userManager->create('john.doe', 'a-secure-password', 'john.doe@example.com', 'John', 'Doe');

// Get a user by ID
$user = $userManager->getById($userId);
echo "Created user: " . $user['login'];
```

### Managing Groups and Hierarchy

```php
$groupManager = $roleManager->groups();

// Create groups
$editorsGroupId = $groupManager->create('Editors');
$proofreadersGroupId = $groupManager->create('Proofreaders');

// Create a hierarchy: Proofreaders are part of Editors
$groupManager->addSubgroup($editorsGroupId, $proofreadersGroupId);

// Add a user to a group
$groupManager->addUserToGroup($userId, $proofreadersGroupId);
```

### Managing Rights and Roles

```php
$rightManager = $roleManager->rights();
$roleManager_Roles = $roleManager->roles();

// Create a right group and a right
$contentRightGroupId = $roleManager->rightGroups()->create('Content Management');
$editArticleRightId = $rightManager->create('edit_article', 'Can edit an article', $contentRightGroupId, 'boolean');

// Create a role
$editorRoleId = $roleManager_Roles->create('Editor Role');

// Add the right to the role
$roleManager_Roles->addRightToRole($editorRoleId, $editArticleRightId);
```

### Managing Contexts and Assigning Roles

```php
$contextManager = $roleManager->contexts();

// Create a context (e.g., a specific blog)
$blogContextId = $contextManager->create('Main Blog');

// Assign the "Editor Role" to the "Editors" group within the "Main Blog" context
$contextManager->assignRoleToGroup($editorsGroupId, $editorRoleId, $blogContextId);
```

### Checking Permissions

Use the `auth()` manager to check permissions. This is a high-performance check that uses caching.

```php
$authManager = $roleManager->auth();

if ($authManager->hasRight($userId, 'edit_article', $blogContextId)) {
    echo "User {$userId} can edit articles in the blog.";
} else {
    echo "User {$userId} CANNOT edit articles in the blog.";
}
```

### Explaining Permissions

For debugging or admin panels, `explainRight()` gives you a full trace of the decision.

```php
$explanation = $authManager->explainRight($userId, 'edit_article', $blogContextId);

print_r($explanation);
/*
Array
(
    [decision] => 1
    [value] => 1
    [reason] => Right granted by role 'Editor Role' from source 'Editors' in context 'Main Blog'.
    [trace] => Array
        (
            [0] => Array
                (
                    ...
                    [status] => APPLIED
                )
        )
)
*/
```

---

## Testing

The library comes with a full suite of unit and integration tests using PHPUnit. A helper script is provided to simplify execution.

### 1. Install Dependencies

Ensure you have installed the development dependencies:

```bash
composer install
```

### 2. Run Tests

Use the `run-tests.sh` script (remember to make it executable with `chmod +x run-tests.sh`):

```bash
# Run the full test suite
./run-tests.sh

# Run tests and generate an HTML code coverage report
./run-tests.sh --coverage
```

The coverage report will be created in the `coverage-report/` directory.

### 3. TODO

* more unit tests
* complete coverage 
* more integration tests

