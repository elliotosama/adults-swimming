-- Fix/support for admin receipt paid-amount updates.
--
-- The application stores receipt paid amount in `transactions`, not directly
-- in `receipts`. When an admin edits "total_paid", the app inserts an
-- adjustment transaction:
--   type = 'payment' for increases
--   type = 'refund'  for decreases
--
-- Run this file on the new database after importing the main schema/data.

START TRANSACTION;

CREATE OR REPLACE VIEW receipt_payment_totals AS
SELECT
    r.id AS receipt_id,
    COALESCE(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0) AS gross_paid,
    COALESCE(SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END), 0) AS total_refunded,
    COALESCE(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0)
      - COALESCE(SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END), 0) AS net_paid
FROM receipts r
LEFT JOIN transactions t ON t.receipt_id = r.id
GROUP BY r.id;

DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER //

CREATE PROCEDURE add_index_if_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64),
    IN create_sql_in TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
          AND index_name = index_name_in
    ) THEN
        SET @sql = create_sql_in;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

CALL add_index_if_missing(
    'transactions',
    'idx_transactions_receipt_type_created',
    'CREATE INDEX idx_transactions_receipt_type_created ON transactions (receipt_id, type, created_at)'
);

CALL add_index_if_missing(
    'receipt_audit_log',
    'idx_receipt_audit_log_receipt_changed',
    'CREATE INDEX idx_receipt_audit_log_receipt_changed ON receipt_audit_log (receipt_id, changed_at)'
);

DROP PROCEDURE add_index_if_missing;

COMMIT;
