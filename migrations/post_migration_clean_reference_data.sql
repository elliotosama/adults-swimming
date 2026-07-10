-- Post-migration cleanup for swimming_academy_migrated.
-- Pull clean reference data from swimming_academy and normalize stored file paths.

SET @SRC_DB = 'swimming_academy';
SET @DST_DB = 'swimming_academy_migrated';

USE `swimming_academy_migrated`;

SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

DROP TABLE IF EXISTS tmp_branch_map;
CREATE TABLE tmp_branch_map AS
SELECT mb.id AS old_branch_id, sb.id AS new_branch_id
FROM swimming_academy_migrated.branches mb
JOIN swimming_academy.branches sb
  ON sb.branch_name = mb.branch_name COLLATE utf8mb4_uca1400_ai_ci;

UPDATE swimming_academy_migrated.receipts r
JOIN tmp_branch_map bm ON bm.old_branch_id = r.branch_id
SET r.branch_id = bm.new_branch_id;

DROP TABLE IF EXISTS tmp_unmatched_branches;
CREATE TABLE tmp_unmatched_branches AS
SELECT
    mb.id AS old_branch_id,
    mb.branch_name,
    base.max_id + ROW_NUMBER() OVER (ORDER BY mb.id) AS new_branch_id
FROM swimming_academy_migrated.branches mb
CROSS JOIN (SELECT COALESCE(MAX(id), 0) AS max_id FROM swimming_academy.branches) base
WHERE EXISTS (
    SELECT 1 FROM swimming_academy_migrated.receipts r WHERE r.branch_id = mb.id
)
AND NOT EXISTS (
    SELECT 1
    FROM swimming_academy.branches sb
    WHERE sb.branch_name = mb.branch_name COLLATE utf8mb4_uca1400_ai_ci
);

UPDATE swimming_academy_migrated.receipts r
JOIN tmp_unmatched_branches ub ON ub.old_branch_id = r.branch_id
SET r.branch_id = ub.new_branch_id;

DELETE FROM swimming_academy_migrated.user_branch;
DELETE FROM swimming_academy_migrated.branches;
DELETE FROM swimming_academy_migrated.countries;

INSERT INTO swimming_academy_migrated.countries
SELECT * FROM swimming_academy.countries;

INSERT INTO swimming_academy_migrated.branches
SELECT * FROM swimming_academy.branches;

INSERT INTO swimming_academy_migrated.branches
    (id, branch_name, created_at, visible, working_days1, working_days2, working_days3, country_id, working_time_from, working_time_to)
SELECT
    new_branch_id,
    branch_name,
    CURRENT_DATE,
    1,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL
FROM tmp_unmatched_branches;

DELETE FROM swimming_academy_migrated.prices;
INSERT INTO swimming_academy_migrated.prices
SELECT * FROM swimming_academy.prices;

UPDATE swimming_academy_migrated.users mu
JOIN swimming_academy.users su
  ON su.username = mu.username COLLATE utf8mb4_uca1400_ai_ci
SET
    mu.email = su.email,
    mu.password_hash = su.password_hash,
    mu.role = su.role,
    mu.phone = su.phone,
    mu.visible = su.visible,
    mu.created_at = su.created_at,
    mu.last_login = su.last_login,
    mu.is_active = su.is_active;

DROP TABLE IF EXISTS tmp_source_only_users;
CREATE TABLE tmp_source_only_users AS
SELECT
    su.*,
    base.max_id + ROW_NUMBER() OVER (ORDER BY su.id) AS migrated_user_id
FROM swimming_academy.users su
CROSS JOIN (SELECT COALESCE(MAX(id), 0) AS max_id FROM swimming_academy_migrated.users) base
WHERE NOT EXISTS (
    SELECT 1
    FROM swimming_academy_migrated.users mu
    WHERE mu.username = su.username COLLATE utf8mb4_uca1400_ai_ci
);

INSERT INTO swimming_academy_migrated.users
    (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
SELECT
    migrated_user_id,
    username,
    email,
    password_hash,
    role,
    phone,
    visible,
    created_at,
    last_login,
    is_active
FROM tmp_source_only_users;

DROP TABLE IF EXISTS tmp_user_map;
CREATE TABLE tmp_user_map AS
SELECT su.id AS source_user_id, mu.id AS migrated_user_id
FROM swimming_academy.users su
JOIN swimming_academy_migrated.users mu
  ON mu.username = su.username COLLATE utf8mb4_uca1400_ai_ci
UNION
SELECT id AS source_user_id, migrated_user_id
FROM tmp_source_only_users;

INSERT INTO swimming_academy_migrated.user_branch (user_id, branch_id)
SELECT DISTINCT um.migrated_user_id, ub.branch_id
FROM swimming_academy.user_branch ub
JOIN tmp_user_map um ON um.source_user_id = ub.user_id
JOIN swimming_academy_migrated.branches b ON b.id = ub.branch_id;

INSERT INTO swimming_academy_migrated.captains
    (id, captain_name, phone_number, created_at, created_by, visible)
SELECT
    CAST(sc.id AS CHAR),
    sc.captain_name,
    sc.phone_number,
    sc.created_at,
    um.migrated_user_id,
    sc.visible
FROM swimming_academy.captains sc
LEFT JOIN tmp_user_map um ON um.source_user_id = sc.created_by
ON DUPLICATE KEY UPDATE
    captain_name = VALUES(captain_name),
    phone_number = VALUES(phone_number),
    created_at = VALUES(created_at),
    created_by = VALUES(created_by),
    visible = VALUES(visible);

DELETE FROM swimming_academy_migrated.captain_branch;
INSERT INTO swimming_academy_migrated.captain_branch (branch_id, captain_id)
SELECT DISTINCT cb.branch_id, CAST(cb.captain_id AS CHAR)
FROM swimming_academy.captain_branch cb
JOIN swimming_academy_migrated.branches b ON b.id = cb.branch_id
JOIN swimming_academy_migrated.captains c ON c.id = CAST(cb.captain_id AS CHAR);

UPDATE swimming_academy_migrated.receipts
SET pdf_path = NULLIF(
    SUBSTRING_INDEX(
        SUBSTRING_INDEX(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pdf_path, '\\\\', '/'), '["', ''), '"]', ''), '"', ''), '[null]', ''),
            ',',
            1
        ),
        '/',
        -1
    ),
    ''
)
WHERE pdf_path IS NOT NULL AND pdf_path <> '';

UPDATE swimming_academy_migrated.transactions
SET attachment = NULLIF(
    SUBSTRING_INDEX(
        SUBSTRING_INDEX(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(attachment, '\\\\', '/'), '["', ''), '"]', ''), '"', ''), '[null]', ''),
            ',',
            1
        ),
        '/',
        -1
    ),
    ''
)
WHERE attachment IS NOT NULL AND attachment <> '';

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'countries' AS table_name, COUNT(*) AS rows_count FROM swimming_academy_migrated.countries
UNION ALL SELECT 'branches', COUNT(*) FROM swimming_academy_migrated.branches
UNION ALL SELECT 'prices', COUNT(*) FROM swimming_academy_migrated.prices
UNION ALL SELECT 'users', COUNT(*) FROM swimming_academy_migrated.users
UNION ALL SELECT 'user_branch', COUNT(*) FROM swimming_academy_migrated.user_branch
UNION ALL SELECT 'captains', COUNT(*) FROM swimming_academy_migrated.captains
UNION ALL SELECT 'captain_branch', COUNT(*) FROM swimming_academy_migrated.captain_branch
UNION ALL SELECT 'receipts_with_dir_path', COUNT(*) FROM swimming_academy_migrated.receipts WHERE pdf_path LIKE '%/%' OR pdf_path LIKE '%\\\\%'
UNION ALL SELECT 'transactions_with_dir_path', COUNT(*) FROM swimming_academy_migrated.transactions WHERE attachment LIKE '%/%' OR attachment LIKE '%\\\\%';

DROP TABLE IF EXISTS tmp_branch_map;
DROP TABLE IF EXISTS tmp_unmatched_branches;
DROP TABLE IF EXISTS tmp_source_only_users;
DROP TABLE IF EXISTS tmp_user_map;
