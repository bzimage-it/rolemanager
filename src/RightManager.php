<?php

namespace RoleManager;

use PDO;
use Exception;



class RightManager extends BaseManager {
    /**
     * Creates a new right.
     *
     * @param string      $name               The name of the right (must be unique).
     * @param string|null $description        A description for the right.
     * @param int         $rightgroup_id      The ID of the right group this right belongs to.
     * @param string      $type               The type of the right ('boolean' or 'range').
     * @param int|null    $righttype_range_id The ID of the range definition if type is 'range'.
     * @return int|false The ID of the newly created right, or false on failure.
     * @throws Exception if validation fails (e.g., a range right is missing a range_id).
     */
    public function create(string $name, ?string $description, int $rightgroup_id, string $type, ?int $righttype_range_id = null)
    {
        try {
            $this->validateNotEmpty($name, "Right name");
            if ($type === 'range' && $righttype_range_id === null) {
                throw new Exception("A 'range' type right requires a 'righttype_range_id'.");
            }
            if ($type === 'boolean' && $righttype_range_id !== null) {
                throw new Exception("A 'boolean' type right must not have a 'righttype_range_id'.");
            }

            $stmt = $this->db->prepare(
                "INSERT INTO role_manager_rights (name, description, rightgroup_id, type, righttype_range_id) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $description, $rightgroup_id, $type, $righttype_range_id]);
            $right_id = $this->db->lastInsertId();
            
            $this->logger->info("Right created", ['right_id' => $right_id, 'name' => $name]);
            $this->incrementPermissionsVersion();
            
            return $right_id;
        } catch (Exception $e) {
            $this->logger->error("Error creating right: " . $e->getMessage(), ['name' => $name]);
            throw $e;
        }
    }

    /**
     * Retrieves a right by its ID.
     *
     * @param int $id The right's ID.
     * @return array|false An associative array with right data, or false if not found.
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_rights WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a right's data.
     *
     * @param int   $id   The ID of the right to update.
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
        $sql = "UPDATE role_manager_rights SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        $this->incrementPermissionsVersion();
        return $result;
    }

    /**
     * Deletes a right.
     *
     * @param int $id The ID of the right to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the right is currently used in any roles.
     */
    public function delete(int $id): bool {
        if ($this->hasDependencies($id)) {
            throw new Exception("Cannot delete right: it is used in one or more roles.");
        }
        $stmt = $this->db->prepare("DELETE FROM role_manager_rights WHERE id = ?");
        $result = $stmt->execute([$id]);
        $this->incrementPermissionsVersion();
        return $result;
    }

    /**
     * Checks if a right has dependencies that prevent deletion.
     *
     * @param int $right_id The right's ID.
     * @return bool True if dependencies exist, false otherwise.
     */
    private function hasDependencies(int $right_id): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_role_rights WHERE right_id = ? LIMIT 1");
        $stmt->execute([$right_id]);
        return $stmt->fetchColumn() > 0;
    }
}
