ALTER TABLE captains
    ADD COLUMN age INT(2),
    ADD COLUMN ssn_card_path VARCHAR(255),
    ADD COLUMN email VARCHAR(255) UNIQUE;



ALTER TABLE captains
ADD COLUMN secondary_phone_number VARCHAR(50) DEFAULT NULL AFTER phone_number,
ADD COLUMN academic_qualification VARCHAR(255) DEFAULT NULL AFTER email,
ADD COLUMN certificate_image_path VARCHAR(255) DEFAULT NULL AFTER ssn_card_path;




ALTER TABLE audit_log
    MODIFY created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

UPDATE audit_log
SET created_at = NOW()
WHERE created_at IS NULL
  AND action IN ('created_captain', 'captain_added_to_branch', 'captain_removed_from_branch');