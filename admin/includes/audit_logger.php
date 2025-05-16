<?php
// File: admin/includes/audit_logger.php

/**
 * Logs an action to the audit_log table.
 *
 * @param string $action Short description of the action (e.g., 'user_role_changed').
 * @param int|null $user_id ID of the user performing the action. Null for system actions.
 * @param string|null $target_type Type of entity affected (e.g., 'user', 'product').
 * @param int|null $target_id ID of the entity affected.
 * @param mixed|null $details Additional details (e.g., array with old/new values). Will be JSON encoded.
 * @return bool True on success, false on failure.
 */
function log_audit_action(string $action, ?int $user_id = null, ?string $target_type = null, ?int $target_id = null, $details = null): bool {
    global $pdo; // Assumes $pdo is globally available, or pass it as a parameter

    if (!$pdo) {
        // Optionally log this error to a file if $pdo is not available
        error_log("Audit Log Error: PDO connection not available.");
        return false;
    }

    // Try to get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    // Serialize details if it's an array or object
    $details_json = null;
    if (!is_null($details)) {
        if (is_array($details) || is_object($details)) {
            $details_json = json_encode($details, JSON_UNESCAPED_UNICODE);
        } else {
            $details_json = (string) $details;
        }
    }

    try {
        // Check if audit_log table exists to prevent errors if not yet created
        $table_check_stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
        if ($table_check_stmt->rowCount() == 0) {
            error_log("Audit Log Error: audit_log table does not exist.");
            return false; // Or handle appropriately, e.g., by attempting to create it
        }

        $sql = "INSERT INTO audit_log (user_id, action, target_type, target_id, details, ip_address)
                VALUES (:user_id, :action, :target_type, :target_id, :details, :ip_address)";
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':target_type', $target_type, PDO::PARAM_STR);
        $stmt->bindParam(':target_id', $target_id, PDO::PARAM_INT);
        $stmt->bindParam(':details', $details_json, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);

        return $stmt->execute();

    } catch (PDOException $e) {
        error_log("Audit Log PDOException: " . $e->getMessage());
        return false;
    }
}
?> 