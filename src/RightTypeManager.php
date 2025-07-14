<?php

namespace RoleManager;

use PDO;
use Exception;

class RightTypeManager extends BaseManager
{
    /**
     * Creates a new right type range definition.
     *
     * @param string      $name        The name of the range (e.g., "Percentage", "Level").
     * @param string|null $description A description for the range.
     * @param float       $min_value   The minimum allowed value for this range.
     * @param float       $max_value   The maximum allowed value for this range.
     * @return int|false The ID of the newly created range, or false on failure.
     * @throws Exception if the name is empty or if max_value is less than min_value.
     */
    public function create(string $name, ?string $description, float $min_value, float $max_value)
    {
        $this->validateNotEmpty($name, "Right type range name");
        if ($max_value < $min_value) {
            throw new Exception("max_value must be greater than or equal to min_value");
        }
        $stmt = $this->db->prepare("INSERT INTO role_manager_righttype_ranges (name, description, min_value, max_value) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $min_value, $max_value]);
        return $this->db->lastInsertId();
    }

    /**
     * Retrieves a right type range by its ID.
     *
     * @param int $id The ID of the right type range.
     * @return array|false An associative array with range data, or false if not found.
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_righttype_ranges WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves all right type ranges.
     *
     * @return array An array of all right type ranges, ordered by name.
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM role_manager_righttype_ranges ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a right type range's data.
     *
     * @param int   $id   The ID of the right type range to update.
     * @param array $data An associative array of fields to update.
     * @return bool True on success, false on failure.
     * @throws Exception if no valid fields are provided.
     */
    public function update(int $id, array $data): bool
    {
        $allowed_fields = ['name', 'description', 'min_value', 'max_value'];
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
        $sql = "UPDATE role_manager_righttype_ranges SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Deletes a right type range.
     *
     * @param int $id The ID of the right type range to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the right type range is in use by any rights.
     */
    public function delete(int $id): bool
    {
        if ($this->hasDependencies($id)) {
            throw new Exception("Cannot delete right type range: it is in use by one or more rights.");
        }
        $stmt = $this->db->prepare("DELETE FROM role_manager_righttype_ranges WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Checks if a right type range has dependencies that prevent deletion.
     *
     * @param int $righttype_range_id The ID of the right type range.
     * @return bool True if dependencies exist, false otherwise.
     * @internal
     */
    private function hasDependencies(int $righttype_range_id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_rights WHERE righttype_range_id = ? LIMIT 1");
        $stmt->execute([$righttype_range_id]);
        return $stmt->fetchColumn() > 0;
    }
}