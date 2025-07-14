<?php

namespace RoleManager;

use PDO;
use Exception;


// Placeholder for other classes to be implemented...
class GroupManager extends BaseManager
{
    /**
     * Creates a new group.
     *
     * @param string      $name        The name of the group (must be unique).
     * @param string|null $description A description for the group.
     * @return int|false The ID of the newly created group, or false on failure.
     * @throws Exception if the group name is empty.
     */
    public function create(string $name, ?string $description = null)
    {
        try {
            $this->validateNotEmpty($name, "Group name");
            $stmt = $this->db->prepare("INSERT INTO role_manager_groups (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $group_id = $this->db->lastInsertId();
            $this->logger->info("Group created", ['group_id' => $group_id, 'name' => $name]);
            $this->incrementPermissionsVersion();
            return $group_id;
        } catch (Exception $e) {
            $this->logger->error("Error creating group: " . $e->getMessage(), ['name' => $name]);
            throw $e;
        }
    }

    /**
     * Retrieves a group by its ID.
     *
     * @param int $id The group's ID.
     * @return array|false An associative array with group data, or false if not found.
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves a group by its name.
     *
     * @param string $name The group's name.
     * @return array|false An associative array with group data, or false if not found.
     */
    public function getByName(string $name)
    {
        $stmt = $this->db->prepare("SELECT * FROM role_manager_groups WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates a group's data.
     *
     * @param int   $id   The ID of the group to update.
     * @param array $data An associative array of fields to update (e.g., ['name' => 'new_name']).
     * @return bool True on success, false on failure.
     * @throws Exception if no valid fields are provided or the name is empty.
     */
    public function update(int $id, array $data): bool
    {
        try {
            $allowed_fields = ['name', 'description'];
            $fields = [];
            $values = [];

            foreach ($data as $field => $value) {
                if (in_array($field, $allowed_fields)) {
                    if ($field === 'name') $this->validateNotEmpty($value, "Group name");
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }

            if (empty($fields)) {
                throw new Exception("No valid fields to update");
            }

            $values[] = $id;
            $sql = "UPDATE role_manager_groups SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->logger->info("Group updated", ['group_id' => $id, 'fields' => array_keys($data)]);
                $this->incrementPermissionsVersion();
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error updating group: " . $e->getMessage(), ['group_id' => $id]);
            throw $e;
        }
    }

    /**
     * Deletes a group.
     *
     * @param int $id The ID of the group to delete.
     * @return bool True on success, false on failure.
     * @throws Exception if the group has dependencies (members, roles, or hierarchy).
     */
    public function delete(int $id): bool
    {
        try {
            if ($this->hasDependencies($id)) {
                throw new Exception("Cannot delete group: it has roles assigned, user memberships, or is part of a hierarchy.");
            }
            
            $stmt = $this->db->prepare("DELETE FROM role_manager_groups WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->logger->info("Group deleted", ['group_id' => $id]);
                $this->incrementPermissionsVersion();
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error deleting group: " . $e->getMessage(), ['group_id' => $id]);
            throw $e;
        }
    }

    /**
     * Checks if a group has dependencies that prevent deletion.
     *
     * @param int $group_id The group's ID.
     * @return bool True if dependencies exist, false otherwise.
     */
    private function hasDependencies(int $group_id): bool {
        // Check for user memberships
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_user_groups WHERE group_id = ? LIMIT 1");
        $stmt->execute([$group_id]);
        if ($stmt->fetchColumn()) return true;

        // Check for role assignments
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_group_context_roles WHERE group_id = ? LIMIT 1");
        $stmt->execute([$group_id]);
        if ($stmt->fetchColumn()) return true;

        // Check for hierarchy (if it's a parent or a child)
        $stmt = $this->db->prepare("SELECT 1 FROM role_manager_group_subgroups WHERE parent_group_id = ? OR child_group_id = ? LIMIT 1");
        $stmt->execute([$group_id, $group_id]);
        if ($stmt->fetchColumn()) return true;

        return false;
    }

    /**
     * Removes a user from a group.
     *
     * @param int $user_id  The ID of the user.
     * @param int $group_id The ID of the group.
     * @return bool True on success, false on failure.
     */
    public function removeUserFromGroup(int $user_id, int $group_id): bool {
        $stmt = $this->db->prepare("DELETE FROM role_manager_user_groups WHERE user_id = ? AND group_id = ?");
        $result = $stmt->execute([$user_id, $group_id]);
        if ($result && $stmt->rowCount() > 0) {
            $this->incrementPermissionsVersion();
        }
        return $result;
    }

    /**
     * Retrieves all users in a specific group.
     *
     * @param int  $group_id  The ID of the group.
     * @param bool $recursive If true, includes users from all nested subgroups.
     * @return array An array of users.
     */
    public function getUsersInGroup(int $group_id, bool $recursive = false): array
    {
        if (!$recursive) {
            $sql = "
                SELECT u.* FROM role_manager_users u
                JOIN role_manager_user_groups ug ON u.id = ug.user_id
                WHERE ug.group_id = ?
                ORDER BY u.login
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$group_id]);
        } else {
            $sql = "
                WITH RECURSIVE GroupHierarchy (id) AS (
                    SELECT ?
                    UNION ALL
                    SELECT gsg.child_group_id
                    FROM GroupHierarchy gh
                    JOIN role_manager_group_subgroups gsg ON gh.id = gsg.parent_group_id
                )
                SELECT DISTINCT u.*
                FROM role_manager_users u
                JOIN role_manager_user_groups ug ON u.id = ug.user_id
                WHERE ug.group_id IN (SELECT id FROM GroupHierarchy)
                ORDER BY u.login
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$group_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Adds a group as a subgroup of another group.
     *
     * @param int $parent_group_id The ID of the parent group.
     * @param int $child_group_id  The ID of the child group to add.
     * @return bool True on success.
     * @throws Exception if the groups are the same or if it creates a circular dependency.
     */
    public function addSubgroup(int $parent_group_id, int $child_group_id): bool
    {
        if ($parent_group_id == $child_group_id) {
            throw new Exception("A group cannot be a subgroup of itself.");
        }
        if ($this->isCircularDependency($parent_group_id, $child_group_id)) {
            throw new Exception("Circular dependency detected. Cannot add subgroup.");
        }
        $stmt = $this->db->prepare("INSERT INTO role_manager_group_subgroups (parent_group_id, child_group_id) VALUES (?, ?)");
        $stmt->execute([$parent_group_id, $child_group_id]);
        $this->incrementPermissionsVersion();
        return true;
    }

    /**
     * Checks if adding a subgroup would create a circular dependency.
     * It verifies if the intended parent is already a descendant of the intended child.
     *
     * @param int $parent_id The ID of the potential parent group.
     * @param int $child_id  The ID of the potential child group.
     * @return bool True if a circular dependency would be created, false otherwise.
     */
    public function isCircularDependency(int $parent_id, int $child_id): bool
    {
        // Check if the new parent is already a child of the new child (creates a direct loop)
        $stmt = $this->db->prepare("
            WITH RECURSIVE SubgroupHierarchy (group_id) AS (
                SELECT child_group_id FROM role_manager_group_subgroups WHERE parent_group_id = :child_id
                UNION ALL
                SELECT gsg.child_group_id
                FROM SubgroupHierarchy sh
                JOIN role_manager_group_subgroups gsg ON sh.group_id = gsg.parent_group_id
            )
            SELECT 1 FROM SubgroupHierarchy WHERE group_id = :parent_id
        ");
        $stmt->execute([':child_id' => $child_id, ':parent_id' => $parent_id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Adds a user to a group.
     *
     * @param int $user_id  The ID of the user to add.
     * @param int $group_id The ID of the group.
     * @return bool True on success.
     */
    public function addUserToGroup(int $user_id, int $group_id): bool
    {
        $stmt = $this->db->prepare("INSERT INTO role_manager_user_groups (user_id, group_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $group_id]);
        $this->incrementPermissionsVersion();
        return true;
    }

    /**
     * Removes a subgroup relationship.
     *
     * @param int $parent_group_id The ID of the parent group.
     * @param int $child_group_id  The ID of the child group.
     * @return bool True on success, false on failure.
     */
    public function removeSubgroup(int $parent_group_id, int $child_group_id): bool {
        $stmt = $this->db->prepare("DELETE FROM role_manager_group_subgroups WHERE parent_group_id = ? AND child_group_id = ?");
        $result = $stmt->execute([$parent_group_id, $child_group_id]);
        if ($result && $stmt->rowCount() > 0) {
            $this->incrementPermissionsVersion();
        }
        return $result;
    }

    /**
     * Retrieves all direct parent groups for a given group.
     *
     * @param int $child_group_id The ID of the child group.
     * @return array An array of parent groups.
     */
    public function getParentGroups(int $child_group_id): array {
        $sql = "
            SELECT g.* FROM role_manager_groups g
            JOIN role_manager_group_subgroups gsg ON g.id = gsg.parent_group_id
            WHERE gsg.child_group_id = ?
            ORDER BY g.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$child_group_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves all direct child groups for a given group.
     *
     * @param int $parent_group_id The ID of the parent group.
     * @return array An array of child groups.
     */
    public function getChildGroups(int $parent_group_id): array {
        $sql = "
            SELECT g.* FROM role_manager_groups g
            JOIN role_manager_group_subgroups gsg ON g.id = gsg.child_group_id
            WHERE gsg.parent_group_id = ?
            ORDER BY g.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$parent_group_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
