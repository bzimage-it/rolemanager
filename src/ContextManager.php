<?php

namespace RoleManager;

use PDO;
use Exception;



class ContextManager extends BaseManager {
    /**
     * Creates a new context.
     *
     * @param string      $name        The name of the context (must be unique).
     * @param string|null $description A description for the context.
     * @return int The ID of the newly created context.
     * @throws Exception if the context name is empty.
     */
    public function create(string $name, ?string $description = null): int
    {
        try {
            $this->validateNotEmpty($name, "Context name");
            $stmt = $this->db->prepare("INSERT INTO role_manager_contexts (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $context_id = $this->db->lastInsertId();
            $this->logger->info("Context created", ['context_id' => $context_id, 'name' => $name]);
            // Creating a context does not change permissions, so no version increment.
            return $context_id;
        } catch (Exception $e) {
            $this->logger->error("Error creating context: " . $e->getMessage(), ['name' => $name]);
            throw $e;
        }
    }

    /**
     * Retrieves a context by its ID.
     *
     * @param int $id The context's ID.
     * @return array|false An associative array with context data, or false if not found.
     */
    public function getById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_contexts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a context's data.
     *
     * @param int   $id   The ID of the context to update.
     * @param array $data An associative array of fields to update (e.g., ['name' => 'new_name']).
     * @return bool True on success, false on failure.
     * @throws Exception if no valid fields are provided.
     */
    public function update(int $id, array $data): bool
    {
        $allowed_fields = ['name', 'description'];
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

        $values[] = $id;
        $sql = "UPDATE role_manager_contexts SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->logger->info("Context updated", ['context_id' => $id, 'fields' => array_keys($data)]);
        }
        
        return $result;
    }

    /**
     * Deletes a context.
     *
     * @param int $id The ID of the context to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the context is currently used in any role assignments.
     */
    public function delete(int $id): bool
    {
        if ($this->hasDependencies($id)) {
            throw new Exception("Cannot delete context: it is currently in use.");
        }
        $stmt = $this->db->prepare("DELETE FROM role_manager_contexts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function hasDependencies(int $context_id): bool {
        $stmt_user = $this->db->prepare("SELECT 1 FROM role_manager_user_context_roles WHERE context_id = ? LIMIT 1");
        $stmt_user->execute([$context_id]);
        if ($stmt_user->fetchColumn()) return true;

        $stmt_group = $this->db->prepare("SELECT 1 FROM role_manager_group_context_roles WHERE context_id = ? LIMIT 1");
        $stmt_group->execute([$context_id]);
        return $stmt_group->fetchColumn() > 0;
    }

    /**
     * Assigns a role to a user, either globally or in a specific context.
     *
     * @param int      $user_id    The ID of the user.
     * @param int      $role_id    The ID of the role.
     * @param int|null $context_id The ID of the context, or null for a global assignment.
     * @return bool True on success.
     */
    public function assignRoleToUser(int $user_id, int $role_id, ?int $context_id = null): bool {
        $stmt = $this->db->prepare("INSERT INTO role_manager_user_context_roles (user_id, role_id, context_id) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $role_id, $context_id]);
        $this->incrementPermissionsVersion();
        return true;
    }

    /**
     * Removes a role from a user, either from a specific context or globally.
     *
     * @param int      $user_id    The ID of the user.
     * @param int      $role_id    The ID of the role.
     * @param int|null $context_id The ID of the context, or null to remove a global assignment.
     * @return bool True on success, false on failure.
     */
    public function removeRoleFromUser(int $user_id, int $role_id, ?int $context_id = null): bool {
        $sql = "DELETE FROM role_manager_user_context_roles WHERE user_id = ? AND role_id = ? AND " . ($context_id === null ? "context_id IS NULL" : "context_id = ?");
        $params = $context_id === null ? [$user_id, $role_id] : [$user_id, $role_id, $context_id];
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        if ($result && $stmt->rowCount() > 0) {
            $this->incrementPermissionsVersion();
        }
        return $result;
    }

    /**
     * Assigns a role to a group, either globally or in a specific context.
     *
     * @param int      $group_id   The ID of the group.
     * @param int      $role_id    The ID of the role.
     * @param int|null $context_id The ID of the context, or null for a global assignment.
     * @return bool True on success.
     */
    public function assignRoleToGroup(int $group_id, int $role_id, ?int $context_id = null): bool {
        $stmt = $this->db->prepare("INSERT INTO role_manager_group_context_roles (group_id, role_id, context_id) VALUES (?, ?, ?)");
        $stmt->execute([$group_id, $role_id, $context_id]);
        $this->incrementPermissionsVersion();
        return true;
    }

    /**
     * Removes a role from a group, either from a specific context or globally.
     *
     * @param int      $group_id   The ID of the group.
     * @param int      $role_id    The ID of the role.
     * @param int|null $context_id The ID of the context, or null to remove a global assignment.
     * @return bool True on success, false on failure.
     */
    public function removeRoleFromGroup(int $group_id, int $role_id, ?int $context_id = null): bool {
        $sql = "DELETE FROM role_manager_group_context_roles WHERE group_id = ? AND role_id = ? AND " . ($context_id === null ? "context_id IS NULL" : "context_id = ?");
        $params = $context_id === null ? [$group_id, $role_id] : [$group_id, $role_id, $context_id];
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        if ($result && $stmt->rowCount() > 0) {
            $this->incrementPermissionsVersion();
        }
        return $result;
    }
}
