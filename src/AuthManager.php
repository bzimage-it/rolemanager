<?php

namespace RoleManager;

use PDO;
use Exception;


// ============================================
// AUTHENTICATION CLASS
// ============================================

class AuthManager extends BaseManager {

    // In-memory cache for the current request
    private array $permission_cache = [];

    // In-memory cache for the permissions version to avoid multiple DB lookups per request
    private ?string $version_cache = null;

    /**
     * Authenticates a user with their login and password.
     *
     * @param string $login    The user's login name.
     * @param string $password The user's plain-text password.
     * @return array|false An array with user data on success, false on failure.
     */
    public function authenticate(string $login, string $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, login, password_hash FROM role_manager_users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logger->warning("Login attempt with non-existent user", ['login' => $login]);
                return false;
            }
            
            if (password_verify($password, $user['password_hash'])) {
                $this->logger->info("Login successful", ['user_id' => $user['id'], 'login' => $login]);
                return $this->getUserDataForSession($user['id']);
            } else {
                $this->logger->warning("Login failed - wrong password", ['login' => $login]);
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Authentication error: " . $e->getMessage(), ['login' => $login]);
            return false;
        }
    }
    
    /**
     * Fetches the essential user data to be used in a session or token payload.
     * @param int $user_id
     * @return array|false
     * @internal
     */
    private function getUserDataForSession(int $user_id) {
        $stmt = $this->db->prepare("SELECT id, login, email, first_name, last_name FROM role_manager_users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifies a user's password without performing a full login.
     *
     * @param int    $user_id  The ID of the user.
     * @param string $password The plain-text password to verify.
     * @return bool True if the password is correct, false otherwise.
     */
    public function verifyPassword(int $user_id, string $password): bool {
        try {
            $stmt = $this->db->prepare("SELECT password_hash FROM role_manager_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();
            
            if (!$hash) return false;
            
            return password_verify($password, $hash);
        } catch (Exception $e) {
            $this->logger->error("Password verification error: " . $e->getMessage(), ['user_id' => $user_id]);
            return false;
        }
    }

    /**
     * Checks if a user has a specific right in a given context.
     * This is the high-performance method that uses multiple caching layers.
     *
     * @param int      $user_id    The ID of the user to check.
     * @param string   $right_name The name of the right to check.
     * @param int|null $context_id The context ID, or null for the global context.
     * @param mixed    &$value     If the right is of type 'range', its resolved value is returned here by reference.
     * @return bool True if the user has the right, false otherwise.
     */
    public function hasRight(int $user_id, string $right_name, ?int $context_id = null, &$value = null): bool {
        $request_cache_key = "{$user_id}-" . ($context_id ?? 'global');

        // 1. Check if permissions for this request are already calculated (in-memory cache)
        if (!isset($this->permission_cache[$request_cache_key])) {
            $current_version = $this->getPermissionsVersion();
            $apcu_enabled = function_exists('apcu_fetch');
            $apcu_key = "rolemanager_perms_{$request_cache_key}";
            $all_rights = false;

            // 2. Try to fetch from persistent cache (APCu)
            if ($apcu_enabled) {
                $cached_item = apcu_fetch($apcu_key, $success);
                if ($success && isset($cached_item['version']) && $cached_item['version'] == $current_version) {
                    $this->logger->debug("Persistent cache hit.", ['key' => $apcu_key]);
                    $all_rights = $cached_item['data'];
                }
            }

            // 3. If no valid persistent cache, calculate from DB
            if ($all_rights === false) {
                $this->logger->debug("Cache miss. Calculating from DB.", ['key' => $apcu_key, 'reason' => $apcu_enabled ? 'stale or not found' : 'apcu disabled']);
                $all_rights = $this->calculateUserRights($user_id, $context_id);
                if ($apcu_enabled) {
                    apcu_store($apcu_key, ['version' => $current_version, 'data' => $all_rights]);
                }
            }
            
            // 4. Populate the in-memory request cache
            $this->permission_cache[$request_cache_key] = $all_rights;
        }

        // 5. Now, check the populated cache for the specific right
        if (isset($this->permission_cache[$request_cache_key][$right_name])) {
            $right_data = $this->permission_cache[$request_cache_key][$right_name];
            $value = $right_data['value'];
            return true;
        }

        return false;
    }

    /**
     * Explains in detail how a permission for a user is calculated.
     * This method is for debugging and administration, not for high-performance checks.
     * It returns the full trace of all considered rules and the final decision.
     *
     * @param int      $user_id    The ID of the user.
     * @param string   $right_name The name of the right to explain.
     * @param int|null $context_id The context ID, or null for the global context.
     * @return array An array explaining the permission resolution.
     */
    public function explainRight(int $user_id, string $right_name, ?int $context_id = null): array {
        $this->logger->debug("Explaining right.", ['user_id' => $user_id, 'right' => $right_name, 'context_id' => $context_id]);
        $all_potential_rights = $this->calculateAllPotentialRights($user_id, $right_name, $context_id);

        if (empty($all_potential_rights)) {
            return [
                'decision' => false,
                'value' => null,
                'reason' => 'No rule found granting this right.',
                'trace' => []
            ];
        }

        // Logic to apply precedence rules and find the winner
        $winner = null;
        foreach ($all_potential_rights as $right) {
            if ($winner === null || $right['specificity'] < $winner['specificity']) {
                $winner = $right;
            } elseif ($right['specificity'] === $winner['specificity']) {
                // Tie-breaking rule for range types: highest value wins
                if (is_numeric($right['value']) && is_numeric($winner['value']) && $right['value'] > $winner['value']) {
                    $winner = $right;
                }
            }
        }

        // Build the final explanation
        $trace = [];
        foreach ($all_potential_rights as $right) {
            $status = ($right === $winner) ? 'APPLIED' : 'OVERRIDDEN';
            $trace[] = [
                'source' => $right['source'],
                'role' => $right['role'],
                'context' => $right['context'],
                'value' => $right['value'],
                'specificity' => $right['specificity'],
                'status' => $status
            ];
        }

        // Sort the trace to have the APPLIED rule first for predictable output.
        usort($trace, function ($a, $b) {
            if ($a['status'] === 'APPLIED') {
                return -1;
            }
            if ($b['status'] === 'APPLIED') {
                return 1;
            }
            // For two OVERRIDDEN rules, keep their original specificity order
            return $a['specificity'] <=> $b['specificity'];
        });

        return [
            'decision' => true,
            'value' => $winner['value'],
            'reason' => "Right granted by role '{$winner['role']}' from source '{$winner['source']}' in context '{$winner['context']}'.",
            'trace' => $trace
        ];
    }

    /**
     * Gets the current global permissions version from the database.
     * The result is cached in memory for the duration of the request.
     * @return string|false
     * @internal
     */
    private function getPermissionsVersion(): string|false {
        if ($this->version_cache !== null) {
            return $this->version_cache;
        }

        $stmt = $this->db->prepare("SELECT config_value FROM role_manager_config WHERE config_key = 'permissions_version'");
        $stmt->execute();
        $this->version_cache = $stmt->fetchColumn();
        
        return $this->version_cache;
    }

    /**
     * Calculates the final, resolved rights for a user in a context.
     * This method resolves all potential rights down to the final set of permissions.
     *
     * @param int      $user_id    The user's ID.
     * @param int|null $context_id The context ID.
     * @return array Associative array of rights ['right_name' => ['value' => ...]]
     * @internal
     */
    private function calculateUserRights(int $user_id, ?int $context_id): array {
        $potential_rights = $this->calculateAllPotentialRights($user_id, null, $context_id);
        $resolved_rights = [];

        foreach ($potential_rights as $right) {
            $right_name = $right['right_name'];
            if (!isset($resolved_rights[$right_name])) {
                $resolved_rights[$right_name] = $right;
            } else {
                $current_winner = $resolved_rights[$right_name];
                if ($right['specificity'] < $current_winner['specificity']) {
                    $resolved_rights[$right_name] = $right; // More specific wins
                } elseif ($right['specificity'] === $current_winner['specificity']) {
                    // Tie-breaking for range
                    if (is_numeric($right['value']) && is_numeric($current_winner['value']) && $right['value'] > $current_winner['value']) {
                        $resolved_rights[$right_name] = $right;
                    }
                }
            }
        }

        // Format for hasRight cache
        $final_rights = [];
        foreach ($resolved_rights as $name => $data) {
            $final_rights[$name] = ['value' => $data['value']];
        }

        return $final_rights;
    }

    /**
     * Fetches all potential rights for a user from all sources (direct, groups, hierarchy).
     * This is the core query engine for permissions.
     *
     * @param int      $user_id      The user's ID.
     * @param string|null $right_name If specified, fetches only this right. Otherwise, all rights.
     * @param int|null $context_id The context ID.
     * @return array A list of all potential rules that could apply.
     * @internal
     */
    private function calculateAllPotentialRights(int $user_id, ?string $right_name, ?int $context_id): array {
        $sql = "
            WITH RECURSIVE UserAllGroups (group_id, distance) AS (
                -- Direct groups
                SELECT group_id, 0 FROM role_manager_user_groups WHERE user_id = :user_id
                UNION ALL
                -- Inherited groups
                SELECT gsg.parent_group_id, uag.distance + 1
                FROM UserAllGroups uag
                -- The JOIN condition finds the parent of the current group in the hierarchy.
                -- By recursively joining, we traverse up the group hierarchy from the user's initial groups.
                -- Example: User in 'Proofreaders' -> finds 'Editors' (distance 1) -> finds 'AllStaff' (distance 2)
                JOIN role_manager_group_subgroups gsg ON uag.group_id = gsg.child_group_id
                WHERE uag.distance < 10 -- Safety break for deep recursion
            )
            -- 1. Rights from direct user assignments
            SELECT
                'user' AS source_type,
                u.id AS source_id,
                u.login AS source,
                r.name AS role,
                c.name AS context,
                rt.name AS right_name,
                rt.type AS right_type,
                rrr.range_value AS value,
                -- Specificity Calculation: Lower is better.
                -- A specific context (context_id IS NOT NULL) gets a base score of 0. A global context gets 100.
                -- We add a constant (10) to ensure direct user assignments always win over group assignments (which start at 20).
                -- Specific Context: 0 + 10 = 10
                -- Global Context: 100 + 10 = 110
                (CASE WHEN ucr.context_id IS NOT NULL THEN 0 ELSE 100 END) + 10 AS specificity
            FROM role_manager_user_context_roles ucr
            JOIN role_manager_users u ON ucr.user_id = u.id
            JOIN role_manager_roles r ON ucr.role_id = r.id
            JOIN role_manager_role_rights rrr ON r.id = rrr.role_id
            JOIN role_manager_rights rt ON rrr.right_id = rt.id
            LEFT JOIN role_manager_contexts c ON ucr.context_id = c.id
            WHERE ucr.user_id = :user_id
              AND (ucr.context_id = :context_id OR ucr.context_id IS NULL)
              AND (:right_name IS NULL OR rt.name = :right_name)

            UNION ALL

            -- 2. Rights from group assignments
            SELECT
                'group' AS source_type,
                g.id AS source_id,
                g.name AS source,
                r.name AS role,
                c.name AS context,
                rt.name AS right_name,
                rt.type AS right_type,
                rrr.range_value AS value,
                -- Specificity Calculation: Lower is better.
                -- A specific context gets a base score of 0, global gets 100.
                -- We add a base of 20 to lose to direct user assignments.
                -- We add the group distance: a closer group (lower distance) results in a better (lower) score.
                -- Specific Context, distance 0: 0 + 20 + 0 = 20
                -- Global Context, distance 1: 100 + 20 + 1 = 121
                (CASE WHEN gcr.context_id IS NOT NULL THEN 0 ELSE 100 END) + (20 + uag.distance) AS specificity
            FROM UserAllGroups uag
            JOIN role_manager_groups g ON uag.group_id = g.id
            JOIN role_manager_group_context_roles gcr ON uag.group_id = gcr.group_id
            JOIN role_manager_roles r ON gcr.role_id = r.id
            JOIN role_manager_role_rights rrr ON r.id = rrr.role_id
            JOIN role_manager_rights rt ON rrr.right_id = rt.id
            LEFT JOIN role_manager_contexts c ON gcr.context_id = c.id
            WHERE (gcr.context_id = :context_id OR gcr.context_id IS NULL)
              AND (:right_name IS NULL OR rt.name = :right_name)

            ORDER BY specificity ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':context_id', $context_id, PDO::PARAM_INT);
        $stmt->bindValue(':right_name', $right_name, PDO::PARAM_STR);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For boolean rights, the value is implicitly true.
        foreach ($results as &$row) {
            if ($row['right_type'] === 'boolean') {
                $row['value'] = true;
            }
            if ($row['context'] === null) {
                $row['context'] = 'Global';
            }
        }

        return $results;
    }
}