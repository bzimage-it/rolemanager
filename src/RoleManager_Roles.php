<?php

namespace RoleManager;

use PDO;
use Exception;

class RoleManager_Roles extends BaseManager
{
    /**
     * Creates a new role.
     *
     * @param string      $name        The name of the role (must be unique).
     * @param string|null $description A description for the role.
     * @return int|false The ID of the newly created role, or false on failure.
     * @throws Exception if the role name is empty.
     */
    public function create(string $name, ?string $description = null) {
        try {
            $this->validateNotEmpty($name, "Role name");
            $stmt = $this->db->prepare("INSERT INTO role_manager_roles (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $role_id = $this->db->lastInsertId();
            $this->logger->info("Role created", ['role_id' => $role_id, 'name' => $name]);
            $this->incrementPermissionsVersion();
            return $role_id;
        } catch (Exception $e) {
            $this->logger->error("Error creating role: " . $e->getMessage(), ['name' => $name]);
            throw $e;
        }
    }

    /**
     * Retrieves a role by its ID.
     *
     * @param int $id The role's ID.
     * @return array|false An associative array with role data, or false if not found.
     */
    public function getById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a role's data.
     *
     * @param int   $id   The ID of the role to update.
     * @param array $data An associative array of fields to update (e.g., ['name' => 'new_name']).
     * @return bool True on success, false on failure.
     */
    public function update(int $id, array $data): bool {
        $allowed_fields = ['name', 'description'];
        $fields = [];
        $values = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) throw new Exception("No valid fields to update");

        $values[] = $id;
        $sql = "UPDATE role_manager_roles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        $this->incrementPermissionsVersion();
        return $result;
    }

    /**
     * Deletes a role.
     *
     * @param int $id The ID of the role to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the role is currently assigned to any users or groups.
     */
    public function delete(int $id): bool {
        if ($this->hasDependencies($id)) {
            throw new Exception("Cannot delete role: it is currently assigned to users or groups.");
        }
        $stmt = $this->db->prepare("DELETE FROM role_manager_roles WHERE id = ?");
        $result = $stmt->execute([$id]);
        $this->incrementPermissionsVersion();
        return $result;
    }

    /**
     * Checks if a role has dependencies that prevent deletion.
     *
     * @param int $role_id The role's ID.
     * @return bool True if dependencies exist, false otherwise.
     */
    private function hasDependencies(int $role_id): bool {
        $stmt_user = $this->db->prepare("SELECT 1 FROM role_manager_user_context_roles WHERE role_id = ? LIMIT 1");
        $stmt_user->execute([$role_id]);
        if ($stmt_user->fetchColumn()) return true;

        $stmt_group = $this->db->prepare("SELECT 1 FROM role_manager_group_context_roles WHERE role_id = ? LIMIT 1");
        $stmt_group->execute([$role_id]);
        if ($stmt_group->fetchColumn()) return true;

        return false;
    }

    /**
     * Adds a right to a role, optionally with a value for 'range' type rights.
     *
     * @param int      $role_id     The ID of the role.
     * @param int      $right_id    The ID of the right to add.
     * @param float|null $range_value The value for the right if it's a 'range' type.
     * @return bool True on success.
     * @throws Exception if the right is not found, or if validation fails (e.g., missing value for range type, or value out of bounds).
     */
    public function addRightToRole(int $role_id, int $right_id, ?float $range_value = null): bool
    {
        // Fetch right details to validate
        $right_stmt = $this->db->prepare("
            SELECT r.type, rr.min_value, rr.max_value
            FROM role_manager_rights r
            LEFT JOIN role_manager_righttype_ranges rr ON r.righttype_range_id = rr.id
            WHERE r.id = ?
        ");
        $right_stmt->execute([$right_id]);
        $right = $right_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$right) {
            throw new Exception("Right not found.");
        }

        // --- VALIDATION LOGIC ---
        if ($right['type'] === 'boolean') {
            if ($range_value !== null) {
                throw new Exception("A value must not be provided for a 'boolean' type right.");
            }
        } elseif ($right['type'] === 'range') {
            if ($range_value === null) {
                throw new Exception("A value is required for a 'range' type right.");
            }
            if ($right['min_value'] !== null && ($range_value < $right['min_value'] || $range_value > $right['max_value'])) {
                $min_formatted = number_format((float)$right['min_value'], 2, '.', '');
                $max_formatted = number_format((float)$right['max_value'], 2, '.', '');
                throw new Exception("Value {$range_value} is out of the allowed range ({$min_formatted} - {$max_formatted}) for this right type.");
            }
        }

        $stmt = $this->db->prepare("INSERT INTO role_manager_role_rights (role_id, right_id, range_value) VALUES (?, ?, ?)");
        $stmt->execute([$role_id, $right_id, $range_value]);
        $this->incrementPermissionsVersion();
        return true;
    }

    /**
     * Removes a right from a role.
     *
     * @param int $role_id  The ID of the role.
     * @param int $right_id The ID of the right to remove.
     * @return bool True on success, false on failure.
     */
    public function removeRightFromRole(int $role_id, int $right_id): bool {
        $stmt = $this->db->prepare("DELETE FROM role_manager_role_rights WHERE role_id = ? AND right_id = ?");
        $result = $stmt->execute([$role_id, $right_id]);
        $this->incrementPermissionsVersion();
        return $result;
    }

    /**
     * Retrieves all rights associated with a specific role.
     *
     * @param int $role_id The ID of the role.
     * @return array An array of rights, including their assigned values if applicable.
     */
    public function getRightsForRole(int $role_id): array {
        $stmt = $this->db->prepare("SELECT r.*, rr.range_value FROM role_manager_rights r JOIN role_manager_role_rights rr ON r.id = rr.right_id WHERE rr.role_id = ? ORDER BY r.name");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
