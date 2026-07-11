-- Remove duplicate captains and repoint dependent rows safely.
--
-- Duplicate detection normalizes common imported-data differences:
--   - leading/trailing spaces
--   - internal spaces and tabs
--   - Arabic tatweel
--   - أ / إ / آ -> ا
--
-- The kept captain in each duplicate group is chosen by:
--   1. highest receipt count
--   2. highest captain_branch count
--   3. visible captain first
--   4. captain with phone first
--   5. lowest id as a final stable tie-breaker
--
-- Foreign-key safety:
--   - receipts.captain_id is updated before deleting duplicates
--   - captain_branch rows are merged with INSERT IGNORE before deleting duplicates

START TRANSACTION;

CREATE TABLE IF NOT EXISTS captain_duplicate_cleanup_map (
    duplicate_id varchar(10) NOT NULL,
    keep_id varchar(10) NOT NULL,
    duplicate_name varchar(255) NOT NULL,
    keep_name varchar(255) NOT NULL,
    duplicate_key varchar(255) NOT NULL,
    duplicate_receipts_before int NOT NULL DEFAULT 0,
    keep_receipts_before int NOT NULL DEFAULT 0,
    duplicate_branches_before int NOT NULL DEFAULT 0,
    keep_branches_before int NOT NULL DEFAULT 0,
    cleaned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (duplicate_id),
    KEY idx_captain_duplicate_cleanup_keep_id (keep_id),
    KEY idx_captain_duplicate_cleanup_key (duplicate_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_captain_duplicate_ranked;
CREATE TEMPORARY TABLE tmp_captain_duplicate_ranked AS
SELECT
    ranked.*
FROM (
    SELECT
        c.id,
        c.captain_name,
        c.phone_number,
        c.visible,
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(LOWER(TRIM(c.captain_name)), ' ', ''),
                            CHAR(9),
                            ''
                        ),
                        'ـ',
                        ''
                    ),
                    'أ',
                    'ا'
                ),
                'إ',
                'ا'
            ),
            'آ',
            'ا'
        ) AS duplicate_key,
        COALESCE(r.receipt_count, 0) AS receipt_count,
        COALESCE(cb.branch_count, 0) AS branch_count,
        COUNT(*) OVER (
            PARTITION BY REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(LOWER(TRIM(c.captain_name)), ' ', ''),
                                CHAR(9),
                                ''
                            ),
                            'ـ',
                            ''
                        ),
                        'أ',
                        'ا'
                    ),
                    'إ',
                    'ا'
                ),
                'آ',
                'ا'
            )
        ) AS duplicate_count,
        ROW_NUMBER() OVER (
            PARTITION BY REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(LOWER(TRIM(c.captain_name)), ' ', ''),
                                CHAR(9),
                                ''
                            ),
                            'ـ',
                            ''
                        ),
                        'أ',
                        'ا'
                    ),
                    'إ',
                    'ا'
                ),
                'آ',
                'ا'
            )
            ORDER BY
                COALESCE(r.receipt_count, 0) DESC,
                COALESCE(cb.branch_count, 0) DESC,
                c.visible DESC,
                CASE WHEN c.phone_number IS NOT NULL AND TRIM(c.phone_number) <> '' THEN 1 ELSE 0 END DESC,
                c.id ASC
        ) AS keep_rank
    FROM captains c
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS receipt_count
        FROM receipts
        GROUP BY captain_id
    ) r ON r.captain_id = c.id
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS branch_count
        FROM captain_branch
        GROUP BY captain_id
    ) cb ON cb.captain_id = c.id
) ranked
WHERE ranked.duplicate_count > 1;

DROP TEMPORARY TABLE IF EXISTS tmp_captain_duplicate_map;
CREATE TEMPORARY TABLE tmp_captain_duplicate_map AS
SELECT
    duplicate_captain.id AS duplicate_id,
    keep_captain.id AS keep_id,
    duplicate_captain.captain_name AS duplicate_name,
    keep_captain.captain_name AS keep_name,
    duplicate_captain.duplicate_key,
    duplicate_captain.receipt_count AS duplicate_receipts_before,
    keep_captain.receipt_count AS keep_receipts_before,
    duplicate_captain.branch_count AS duplicate_branches_before,
    keep_captain.branch_count AS keep_branches_before
FROM tmp_captain_duplicate_ranked duplicate_captain
JOIN tmp_captain_duplicate_ranked keep_captain
  ON keep_captain.duplicate_key = duplicate_captain.duplicate_key
 AND keep_captain.keep_rank = 1
WHERE duplicate_captain.keep_rank > 1;

INSERT INTO captain_duplicate_cleanup_map (
    duplicate_id,
    keep_id,
    duplicate_name,
    keep_name,
    duplicate_key,
    duplicate_receipts_before,
    keep_receipts_before,
    duplicate_branches_before,
    keep_branches_before
)
SELECT
    duplicate_id,
    keep_id,
    duplicate_name,
    keep_name,
    duplicate_key,
    duplicate_receipts_before,
    keep_receipts_before,
    duplicate_branches_before,
    keep_branches_before
FROM tmp_captain_duplicate_map
ON DUPLICATE KEY UPDATE
    keep_id = VALUES(keep_id),
    duplicate_name = VALUES(duplicate_name),
    keep_name = VALUES(keep_name),
    duplicate_key = VALUES(duplicate_key),
    duplicate_receipts_before = VALUES(duplicate_receipts_before),
    keep_receipts_before = VALUES(keep_receipts_before),
    duplicate_branches_before = VALUES(duplicate_branches_before),
    keep_branches_before = VALUES(keep_branches_before),
    cleaned_at = CURRENT_TIMESTAMP;

INSERT IGNORE INTO captain_branch (branch_id, captain_id)
SELECT cb.branch_id, m.keep_id
FROM captain_branch cb
JOIN tmp_captain_duplicate_map m ON m.duplicate_id = cb.captain_id;

UPDATE receipts r
JOIN tmp_captain_duplicate_map m ON m.duplicate_id = r.captain_id
SET r.captain_id = m.keep_id;

DELETE cb
FROM captain_branch cb
JOIN tmp_captain_duplicate_map m ON m.duplicate_id = cb.captain_id;

DELETE c
FROM captains c
JOIN tmp_captain_duplicate_map m ON m.duplicate_id = c.id;

DROP TEMPORARY TABLE IF EXISTS tmp_captain_duplicate_map;
DROP TEMPORARY TABLE IF EXISTS tmp_captain_duplicate_ranked;

COMMIT;
