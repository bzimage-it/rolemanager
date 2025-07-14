<?php

namespace RoleManager;

use PDO;
use Exception;

class RightGroupManager extends BaseManager
{
    /**
     * Creates a new right group.
     *
     * @param string      $name        The name of the right group.
     * @param string|null $description A description for the group.
     * @return int|false The ID of the newly created group, or false on failure.
     */
    public function create(string $name, ?string $description = null)
    {
        $this->validateNotEmpty($name, "Right group name");
        $stmt = $this->db->prepare("INSERT INTO role_manager_rightgroups (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        return $this->db->lastInsertId();
    }

    /**
     * Retrieves a right group by its ID.
     *
     * @param int $id The right group's ID.
     * @return array|false An associative array with group data, or false if not found.
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_rightgroups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves all right groups.
     *
     * @return array An array of all right groups, ordered by name.
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM role_manager_rightgroups ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a right group's data.
     *
     * @param int   $id   The ID of the right group to update.
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
        $sql = "UPDATE role_manager_rightgroups SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Deletes a right group.
     *
     * @param int $id The ID of the right group to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the right group is in use by any rights.
     */
    public function delete(int $id): bool
    {
        if ($this->hasDependencies($id)) {
            throw new Exception("Cannot delete right group: it is in use by one or more rights.");
        }
        $stmt = $this->db->prepare("DELETE FROM role_manager_rightgroups WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Checks if a right group has dependencies that prevent deletion.
     *
     * @param int $rightgroup_id The right group's ID.
     * @return bool True if dependencies exist, false otherwise.
     * @internal
     */
    private function hasDependencies(int $rightgroup_id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_rights WHERE rightgroup_id = ? LIMIT 1");
        $stmt->execute([$rightgroup_id]);
        return $stmt->fetchColumn() > 0;
    }
}