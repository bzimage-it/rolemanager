<?php

namespace RoleManager;

use PDO;
use Exception;


// ============================================
// USER MANAGEMENT CLASS
// ============================================

class UserManager extends BaseManager {
    
    /**
     * Creates a new user.
     *
     * @param string      $login      The user's login name (must be unique).
     * @param string      $password   The user's plain-text password.
     * @param string      $email      The user's email address (must be unique).
     * @param string|null $first_name The user's first name.
     * @param string|null $last_name  The user's last name.
     * @return int|false The ID of the newly created user, or false on failure.
     * @throws Exception if login, password, or email are empty, or if email is invalid.
     */
    public function create(string $login, string $password, string $email, ?string $first_name = null, ?string $last_name = null) {
        try {
            $this->validateNotEmpty($login, "Login");
            $this->validateNotEmpty($password, "Password");
            $this->validateNotEmpty($email, "Email");
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("INSERT INTO role_manager_users (login, password_hash, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$login, $password_hash, $email, $first_name, $last_name]);
            
            if ($result) {
                $user_id = $this->db->lastInsertId();
                $this->logger->info("User created", ['user_id' => $user_id, 'login' => $login]);
                return $user_id;
            }
            return false;
        } catch (Exception $e) {
            $this->logger->error("Error creating user: " . $e->getMessage(), ['login' => $login]);
            throw $e;
        }
    }
    
    /**
     * Retrieves a user by their ID.
     *
     * @param int $id The user's ID.
     * @return array|false An associative array with user data, or false if not found.
     */
    public function getById(int $id) {
        $stmt = $this->db->prepare("SELECT id, login, email, first_name, last_name, created_at, updated_at FROM role_manager_users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retrieves a user by their login name.
     *
     * @param string $login The user's login name.
     * @return array|false An associative array with user data, or false if not found.
     */
    public function getByLogin(string $login) {
        $stmt = $this->db->prepare("SELECT id, login, email, first_name, last_name, created_at, updated_at FROM role_manager_users WHERE login = ?");
        $stmt->execute([$login]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retrieves all users, with optional pagination.
     *
     * @param int|null $limit  The maximum number of users to retrieve.
     * @param int      $offset The starting offset for retrieval.
     * @return array An array of associative arrays, each representing a user.
     */
    public function getAll(?int $limit = null, int $offset = 0): array {
        $sql = "SELECT id, login, email, first_name, last_name, created_at, updated_at FROM role_manager_users ORDER BY login";
        if ($limit !== null) {
            $sql .= " LIMIT ?, ?";
        }
        
        $stmt = $this->db->prepare($sql);
        if ($limit !== null) {
            // Note: PDO binding for LIMIT/OFFSET can be tricky.
            // This works for SQLite and MySQL when emulated prepares are off.
            // For full compatibility, it's safer to cast to int and embed directly,
            // but this is generally safe as we control the types.
            $stmt->bindValue(1, $offset, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // UPDATE
    /**
     * Updates a user's data.
     *
     * @param int   $id   The ID of the user to update.
     * @param array $data An associative array of fields to update (e.g., ['login' => 'new_login']).
     * @return bool True on success, false on failure.
     * @throws Exception if no valid fields are provided or email is invalid.
     */
    public function update(int $id, array $data): bool {
        try {
            $allowed_fields = ['login', 'email', 'first_name', 'last_name'];
            $fields = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowed_fields)) {
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                throw new Exception("No valid fields to update");
            }
            
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            $values[] = $id;
            $sql = "UPDATE role_manager_users SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->logger->info("User updated", ['user_id' => $id, 'fields' => array_keys($data)]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error updating user: " . $e->getMessage(), ['user_id' => $id]);
            throw $e;
        }
    }
    
    /**
     * Updates a user's password.
     *
     * @param int    $id           The ID of the user.
     * @param string $new_password The new plain-text password.
     * @return bool True on success, false on failure.
     */
    public function updatePassword(int $id, string $new_password): bool {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE role_manager_users SET password_hash = ? WHERE id = ?");
            $result = $stmt->execute([$password_hash, $id]);
            
            if ($result) {
                $this->logger->info("User password updated", ['user_id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error updating password: " . $e->getMessage(), ['user_id' => $id]);
            throw $e;
        }
    }
    
    // DELETE
    /**
     * Deletes a user.
     *
     * @param int $id The ID of the user to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the user has dependencies (e.g., assigned roles).
     */
    public function delete(int $id): bool {
        try {
            if ($this->hasDependencies($id)) {
                throw new Exception("Cannot delete user: dependencies exist");
            }
            
            $stmt = $this->db->prepare("DELETE FROM role_manager_users WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->logger->info("User deleted", ['user_id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error deleting user: " . $e->getMessage(), ['user_id' => $id]);
            throw $e;
        }
    }
    
    /**
     * Checks if a user has dependencies that prevent deletion.
     *
     * @param int $user_id The user's ID.
     * @return bool True if dependencies exist, false otherwise.
     */
    private function hasDependencies(int $user_id): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM role_manager_user_context_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Searches for users by login, email, or name.
     *
     * @param string $query The search term.
     * @param int    $limit The maximum number of results to return.
     * @return array An array of users matching the query.
     */
    public function search(string $query, int $limit = 10): array {
        $search = "%$query%";
        $stmt = $this->db->prepare("
            SELECT id, login, email, first_name, last_name 
            FROM role_manager_users 
            WHERE login LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?
            ORDER BY login LIMIT ?
        ");
        $stmt->bindValue(1, $search, PDO::PARAM_STR);
        $stmt->bindValue(2, $search, PDO::PARAM_STR);
        $stmt->bindValue(3, $search, PDO::PARAM_STR);
        $stmt->bindValue(4, $search, PDO::PARAM_STR);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
