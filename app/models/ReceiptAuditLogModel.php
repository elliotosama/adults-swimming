<?php
// app/models/ReceiptAuditLogModel.php

class ReceiptAuditLogModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── All logs for a receipt ────────────────────────────────────────────────

    public function findByReceipt(int $receiptId): array {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   u.username AS changer_name
            FROM receipt_audit_log l
            LEFT JOIN users u ON u.id = l.changed_by
            WHERE l.receipt_id = ?
            ORDER BY l.changed_at DESC
        ");
        $stmt->execute([$receiptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── All logs (admin overview) ─────────────────────────────────────────────

    public function findAll(int $limit = 200): array {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   CONCAT(u.first_name, ' ', u.last_name) AS changer_name
            FROM receipt_audit_log l
            LEFT JOIN users u ON u.id = l.changed_by
            ORDER BY l.changed_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Log a single field change ─────────────────────────────────────────────

    public function log(int $receiptId, int $changedBy, string $role,
                        string $fieldName, ?string $oldValue, ?string $newValue): void {
        $stmt = $this->db->prepare("
            INSERT INTO receipt_audit_log
                (receipt_id, changed_by, role, field_name, old_value, new_value)
            VALUES
                (:receipt_id, :changed_by, :role, :field_name, :old_value, :new_value)
        ");
        $stmt->execute([
            ':receipt_id' => $receiptId,
            ':changed_by' => $changedBy,
            ':role'       => $role,
            ':field_name' => $fieldName,
            ':old_value'  => $oldValue,
            ':new_value'  => $newValue,
        ]);
    }

    // ── Log multiple changed fields at once ───────────────────────────────────

    public function logChanges(int $receiptId, int $changedBy, string $role,
                                array $old, array $new): void {
        foreach ($new as $field => $newVal) {
            $oldVal = $old[$field] ?? null;
            $newValStr = is_array($newVal) ? implode(',', $newVal) : (string)($newVal ?? '');
            $oldValStr = is_array($oldVal) ? implode(',', $oldVal) : (string)($oldVal ?? '');

            if ($newValStr !== $oldValStr) {
                $this->log($receiptId, $changedBy, $role, $field, $oldValStr, $newValStr);
            }
        }
    }
}