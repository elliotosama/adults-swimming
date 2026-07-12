ALTER TABLE audit_log
    MODIFY created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

UPDATE audit_log
SET created_at = NOW()
WHERE created_at IS NULL
  AND action IN ('created_captain', 'captain_added_to_branch', 'captain_removed_from_branch');
