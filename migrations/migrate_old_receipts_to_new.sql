-- MariaDB 10.11 compatible migration from old swimming academy schema to the new schema.
--
-- Usage:
--   1. Import migrations/swimming_academy_schema_only.sql into your NEW database.
--   2. Import swimming_academy_old.sql into your OLD database.
--      If needed, convert the old UTF-16 dump first:
--        iconv -f UTF-16LE -t UTF-8 swimming_academy_old.sql > swimming_academy_old_utf8.sql
--   3. Replace the database names below, then run this file while connected to MariaDB.

SET @OLD_DB = 'swimmingAcademyOld';
SET @NEW_DB = 'swimming_academy_migrated';

SET @sql = CONCAT('USE `', @NEW_DB, '`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

TRUNCATE TABLE receipt_audit_log;
TRUNCATE TABLE transactions;
TRUNCATE TABLE receipts;
TRUNCATE TABLE clients;
TRUNCATE TABLE branches;
TRUNCATE TABLE captains;
TRUNCATE TABLE users;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`users`
    (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
 SELECT
    id,
    username,
    NULLIF(email, ''''),
    password,
    CASE
        WHEN role IN (''superAdmin'', ''admin'') THEN ''admin''
        WHEN role = ''areaManager'' THEN ''area_manager''
        WHEN role = ''manager'' THEN ''branch_manager''
        WHEN role = ''customerService'' THEN ''customer_service''
        WHEN role = ''inactive'' THEN ''customer_service''
        ELSE role
    END,
    phone,
    1,
    CURRENT_DATE,
    NULL,
    CASE WHEN role = ''inactive'' THEN 0 ELSE 1 END
 FROM `', @OLD_DB, '`.`users`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`branches`
    (id, branch_name, created_at, visible, country_id)
 SELECT id, name, CURRENT_DATE, 1, NULL
 FROM `', @OLD_DB, '`.`branches`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`branches`
    (id, branch_name, created_at, visible, country_id)
 SELECT
    base.max_id + ROW_NUMBER() OVER (ORDER BY missing.branch_name),
    missing.branch_name,
    CURRENT_DATE,
    1,
    NULL
 FROM (
    SELECT DISTINCT COALESCE(NULLIF(r.branch, ''''), ''Unknown Branch'') AS branch_name
    FROM `', @OLD_DB, '`.`receipts` r
 ) missing
 CROSS JOIN (SELECT COALESCE(MAX(id), 0) AS max_id FROM `', @NEW_DB, '`.`branches`) base
 LEFT JOIN `', @NEW_DB, '`.`branches` nb ON nb.branch_name = missing.branch_name COLLATE utf8mb4_uca1400_ai_ci
 WHERE nb.id IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`captains`
    (id, captain_name, phone_number, created_at, created_by, visible)
 SELECT
    id,
    name,
    phone,
    DATE(created_at),
    NULL,
    1
 FROM `', @OLD_DB, '`.`captains`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`captains`
    (id, captain_name, phone_number, created_at, created_by, visible)
 SELECT
    CONCAT(''m-'', ROW_NUMBER() OVER (ORDER BY missing.captain_name)),
    missing.captain_name,
    NULL,
    CURRENT_DATE,
    NULL,
    1
 FROM (
    SELECT DISTINCT COALESCE(NULLIF(r.coach, ''''), ''Unknown Captain'') AS captain_name
    FROM `', @OLD_DB, '`.`receipts` r
 ) missing
 LEFT JOIN `', @NEW_DB, '`.`captains` nc ON nc.captain_name = missing.captain_name COLLATE utf8mb4_uca1400_ai_ci
 WHERE nc.id IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`clients`
    (id, client_name, phone, created_by, age, gender, created_at, email, visible)
 SELECT
    c.id,
    c.name,
    CASE
        WHEN c.phone_rank = 1 THEN c.phone
        ELSE CONCAT(c.phone, ''#duplicate-'', c.id)
    END,
    u.id,
    c.age,
    NULL,
    DATE(c.created_at),
    CONCAT(''client.'', c.id, ''@migration.local''),
    1
 FROM (
    SELECT c.*, ROW_NUMBER() OVER (PARTITION BY c.phone ORDER BY c.id) AS phone_rank
    FROM `', @OLD_DB, '`.`clients` c
 ) c
 LEFT JOIN `', @OLD_DB, '`.`users` u ON u.username = c.created_by'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`users`
    (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
 SELECT DISTINCT
    r.creator_id,
    COALESCE(NULLIF(r.creator_username, ''''), CONCAT(''Migrated User '', r.creator_id)),
    CONCAT(''migrated.user.'', r.creator_id, ''@migration.local''),
    NULL,
    CASE
        WHEN r.creator_role = ''areaManager'' THEN ''area_manager''
        WHEN r.creator_role = ''manager'' THEN ''branch_manager''
        WHEN r.creator_role = ''customerService'' THEN ''customer_service''
        WHEN r.creator_role IN (''superAdmin'', ''admin'') THEN ''admin''
        ELSE ''customer_service''
    END,
    NULL,
    1,
    DATE(r.created_at),
    NULL,
    1
 FROM `', @OLD_DB, '`.`receipts` r
 LEFT JOIN `', @NEW_DB, '`.`users` nu ON nu.id = r.creator_id
 WHERE r.creator_id IS NOT NULL
   AND nu.id IS NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`clients`
    (id, client_name, phone, created_by, age, gender, created_at, email, visible)
 SELECT
    r.client_id,
    COALESCE(MAX(NULLIF(r.client_name, '''')), CONCAT(''Migrated Client '', r.client_id)),
    CONCAT(COALESCE(MAX(NULLIF(r.phone, '''')), ''missing-phone''), ''#missing-client-'', r.client_id),
    MIN(r.creator_id),
    MAX(r.age),
    NULL,
    MIN(DATE(r.created_at)),
    CONCAT(''missing.client.'', r.client_id, ''@migration.local''),
    1
 FROM `', @OLD_DB, '`.`receipts` r
 LEFT JOIN `', @NEW_DB, '`.`clients` nc ON nc.id = r.client_id
 WHERE r.client_id IS NOT NULL
   AND nc.id IS NULL
 GROUP BY r.client_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`receipts`
    (id, receipt_ref, client_id, creator_id, captain_id, branch_id,
     first_session, last_session, renewal_session, created_at,
     renewal_type, receipt_status, exercise_time, plan_id, level, pdf_path, is_refunded)
 SELECT
    r.id,
    CAST(r.id AS CHAR),
    r.client_id,
    r.creator_id,
    c.id AS captain_id,
    b.id AS branch_id,
    r.first_session,
    r.last_session,
    r.renewal_date,
    DATE(r.created_at),
    CASE
        WHEN r.type = ''fresh'' THEN ''new''
        WHEN r.type = ''renew'' THEN ''renew''
        ELSE COALESCE(r.renew_type, r.type)
    END,
    COALESCE(r.status, ''not_completed''),
    r.exerciseTime,
    NULL,
    r.clientLevel,
    r.attachment,
    0
 FROM `', @OLD_DB, '`.`receipts` r
 JOIN `', @NEW_DB, '`.`clients` nc ON nc.id = r.client_id
 JOIN `', @NEW_DB, '`.`users` nu ON nu.id = r.creator_id
 JOIN (
    SELECT branch_name, MIN(id) AS id
    FROM `', @NEW_DB, '`.`branches`
    GROUP BY branch_name
 ) b ON b.branch_name = COALESCE(NULLIF(r.branch, ''''), ''Unknown Branch'') COLLATE utf8mb4_uca1400_ai_ci
 JOIN (
    SELECT captain_name, MIN(id) AS id
    FROM `', @NEW_DB, '`.`captains`
    GROUP BY captain_name
 ) c ON c.captain_name = COALESCE(NULLIF(r.coach, ''''), ''Unknown Captain'') COLLATE utf8mb4_uca1400_ai_ci'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Initial receipt payments become transactions.
SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`transactions`
    (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
 SELECT
    r.payment_method,
    r.paid_amount,
    r.id,
    r.creator_id,
    DATE(r.created_at),
    r.attachment,
    COALESCE(NULLIF(r.notes, ''''), ''Migrated from old receipt payment''),
    ''payment''
 FROM `', @OLD_DB, '`.`receipts` r
 JOIN `', @NEW_DB, '`.`receipts` nr ON nr.id = r.id
 WHERE COALESCE(r.paid_amount, 0) <> 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Activity rows with action "دفع ايصال" become additional payment transactions.
-- The old dump may contain mojibake Arabic; the LIKE on details keeps the script useful after a clean import too.
SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`transactions`
    (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
 SELECT
    r.payment_method,
    NULL,
    a.receiptId,
    a.user_id,
    DATE(a.created_at),
    NULL,
    a.details,
    ''payment''
 FROM `', @OLD_DB, '`.`activities` a
 JOIN `', @NEW_DB, '`.`receipts` nr ON nr.id = a.receiptId
 LEFT JOIN `', @OLD_DB, '`.`receipts` r ON r.id = a.receiptId
 WHERE a.action IN (''دفع ايصال'', ''دفع إيصال'', ''╪»┘ü╪╣ ╪º┘è╪╡╪º┘ä'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Receipt edits from activities become audit rows.
SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`receipt_audit_log`
    (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
 SELECT
    a.receiptId,
    a.user_id,
    a.created_at,
    COALESCE(nu.role, ''admin''),
    ''receipt_edited'',
    NULL,
    a.details
 FROM `', @OLD_DB, '`.`activities` a
 LEFT JOIN `', @NEW_DB, '`.`users` nu ON nu.id = a.user_id
 JOIN `', @NEW_DB, '`.`receipts` nr ON nr.id = a.receiptId
 WHERE a.action IN (''تعديل ايصال'', ''تعديل إيصال'', ''╪¬╪╣╪»┘è┘ä ╪º┘è╪╡╪º┘ä'', ''╪¬╪╣╪»┘è┘ä ╪Ñ┘è╪╡╪º┘ä'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Old receipt edit_history JSON is kept as one audit record per edited receipt.
SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`receipt_audit_log`
    (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
 SELECT
    r.id,
    COALESCE(r.creator_id, 1),
    COALESCE(r.last_edit, r.created_at),
    COALESCE(nu.role, r.creator_role, ''admin''),
    ''edit_history'',
    NULL,
    r.edit_history
 FROM `', @OLD_DB, '`.`receipts` r
 LEFT JOIN `', @NEW_DB, '`.`users` nu ON nu.id = r.creator_id
 JOIN `', @NEW_DB, '`.`receipts` nr ON nr.id = r.id
 WHERE JSON_VALID(r.edit_history)
   AND JSON_LENGTH(r.edit_history) > 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create and renew activities are recorded as audit context for the receipt lifecycle.
SET @sql = CONCAT(
'INSERT INTO `', @NEW_DB, '`.`receipt_audit_log`
    (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
 SELECT
    a.receiptId,
    a.user_id,
    a.created_at,
    COALESCE(nu.role, ''admin''),
    CASE
        WHEN a.action IN (''اضافه عميل'', ''إضافة عميل'', ''╪Ñ╪╢╪º┘ü╪⌐ ╪╣┘à┘è┘ä'') THEN ''receipt_created''
        ELSE ''receipt_renewed''
    END,
    NULL,
    a.details
 FROM `', @OLD_DB, '`.`activities` a
 LEFT JOIN `', @NEW_DB, '`.`users` nu ON nu.id = a.user_id
 JOIN `', @NEW_DB, '`.`receipts` nr ON nr.id = a.receiptId
 WHERE a.action IN (
    ''اضافه عميل'', ''إضافة عميل'', ''╪Ñ╪╢╪º┘ü╪⌐ ╪╣┘à┘è┘ä'',
    ''تجديد عميل'', ''╪¬╪¼╪»┘è╪» ╪╣┘à┘è┘ä''
 )'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`users` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`branches` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`clients` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`receipts` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`transactions` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @NEW_DB, '`.`receipt_audit_log` AUTO_INCREMENT = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'users' AS table_name, COUNT(*) AS migrated_rows FROM users
UNION ALL SELECT 'branches', COUNT(*) FROM branches
UNION ALL SELECT 'captains', COUNT(*) FROM captains
UNION ALL SELECT 'clients', COUNT(*) FROM clients
UNION ALL SELECT 'receipts', COUNT(*) FROM receipts
UNION ALL SELECT 'transactions', COUNT(*) FROM transactions
UNION ALL SELECT 'receipt_audit_log', COUNT(*) FROM receipt_audit_log;
