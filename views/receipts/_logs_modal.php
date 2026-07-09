<?php // views/receipts/_logs_modal.php — no layout, injected into index.php overlay

function rlmEvidenceUrl(string $raw): string {
    if ($raw === '') return '';
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) return $raw;
    if (str_starts_with($raw, '/uploads/')) return APP_URL . $raw;
    return APP_URL . '/uploads/evidence/' . basename($raw);
}

$typeMap = [
    'payment'  => ['rlm-badge-success', 'دفعة'],
    'refund'   => ['rlm-badge-danger',  'استرداد'],
    'discount' => ['rlm-badge-warning', 'خصم'],
];
$isAdmin = $isAdmin ?? false;
?>
<div class="rlm">
<style>
.rlm {
    --rlm-bg: #1E1E2D;
    --rlm-surface: #252736;
    --rlm-surface2: #2C2F38;
    --rlm-border: #3C3F58;
    --rlm-text: #FFFFFF;
    --rlm-muted: #ffffffb3;
    --rlm-primary: #007ACC;
    --rlm-success: #98C379;
    --rlm-danger: #E06C75;
    --rlm-warning: #D19A66;
    color: var(--rlm-text);
    font-family: 'Cairo', sans-serif;
}
.rlm-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.rlm-title {
    margin: 0;
    font-size: 1rem;
}
.rlm-subtitle {
    margin: 4px 0 0;
    color: var(--rlm-muted);
    font-size: .86rem;
}
.rlm-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.rlm-grid.transactions-only { grid-template-columns: minmax(0, 1fr); }
.rlm-box {
    min-height: 420px;
    max-height: min(64vh, 620px);
    display: flex;
    flex-direction: column;
    background: var(--rlm-surface);
    border: 1px solid var(--rlm-border);
    border-radius: 10px;
    overflow: hidden;
}
.rlm-box-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    background: var(--rlm-surface2);
    border-bottom: 1px solid var(--rlm-border);
}
.rlm-box-header h3 {
    margin: 0;
    font-size: .95rem;
}
.rlm-count {
    color: var(--rlm-muted);
    font-size: .8rem;
}
.rlm-scroll {
    overflow: auto;
    padding: 12px;
}
.rlm-empty {
    min-height: 220px;
    display: grid;
    place-items: center;
    color: var(--rlm-muted);
    text-align: center;
}
.rlm-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.rlm-card {
    border: 1px solid var(--rlm-border);
    border-radius: 8px;
    padding: 11px 12px;
    background: rgba(255,255,255,.025);
}
.rlm-card-top,
.rlm-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.rlm-card-top {
    margin-bottom: 8px;
}
.rlm-main {
    font-weight: 700;
}
.rlm-meta,
.rlm-label {
    color: var(--rlm-muted);
    font-size: .78rem;
}
.rlm-amount {
    font-weight: 700;
    white-space: nowrap;
}
.rlm-amount.refund { color: var(--rlm-danger); }
.rlm-row {
    padding-top: 7px;
    margin-top: 7px;
    border-top: 1px solid rgba(60,63,88,.7);
}
.rlm-value {
    text-align: left;
    word-break: break-word;
}
.rlm-change {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}
.rlm-old,
.rlm-new {
    min-width: 0;
    padding: 7px 9px;
    border-radius: 7px;
    background: var(--rlm-bg);
    font-size: .82rem;
    word-break: break-word;
}
.rlm-old { color: var(--rlm-danger); }
.rlm-new { color: var(--rlm-success); }
.rlm-arrow { color: var(--rlm-muted); }
.rlm-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: .76rem;
    font-weight: 700;
}
.rlm-badge-success { background: rgba(152,195,121,.15); color: var(--rlm-success); }
.rlm-badge-danger  { background: rgba(224,108,117,.15); color: var(--rlm-danger); }
.rlm-badge-warning { background: rgba(209,154,102,.16); color: var(--rlm-warning); }
.rlm-evidence {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    min-height: 34px;
    border: 1px solid var(--rlm-border);
    border-radius: 7px;
    color: var(--rlm-text);
    text-decoration: none;
    overflow: hidden;
}
.rlm-evidence img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    display: block;
}
@media (max-width: 820px) {
    .rlm-grid { grid-template-columns: 1fr; }
    .rlm-box { min-height: 320px; max-height: 58vh; }
}
@media (max-width: 520px) {
    .rlm-head,
    .rlm-card-top,
    .rlm-row { flex-direction: column; }
    .rlm-change { grid-template-columns: 1fr; }
    .rlm-arrow { display: none; }
    .rlm-value { text-align: right; }
}
</style>

    <div class="rlm-head">
        <div>
            <h2 class="rlm-title">إيصال #<?= htmlspecialchars($receipt['receipt_ref'] ?? $receipt['id'] ?? '') ?></h2>
            <p class="rlm-subtitle"><?= htmlspecialchars($receipt['client_name'] ?? '—') ?></p>
        </div>
    </div>

    <div class="rlm-grid <?= $isAdmin ? '' : 'transactions-only' ?>">
        <section class="rlm-box">
            <div class="rlm-box-header">
                <h3> المعاملات الماليه</h3>
                <span class="rlm-count"><?= count($transactions ?? []) ?> معاملة</span>
            </div>
            <div class="rlm-scroll">
                <?php if (empty($transactions)): ?>
                    <div class="rlm-empty">لا توجد معاملات لهذا الإيصال.</div>
                <?php else: ?>
                    <div class="rlm-list">
                        <?php foreach ($transactions as $t): ?>
                            <?php
                            [$badgeClass, $typeLabel] = $typeMap[$t['type'] ?? ''] ?? ['rlm-badge-warning', ($t['type'] ?? '—')];
                            $rawEvidence = $t['attachment'] ?? $t['transaction_evidence'] ?? $t['evidence'] ?? '';
                            $evidenceUrl = $rawEvidence ? rlmEvidenceUrl($rawEvidence) : '';
                            $ext = $rawEvidence ? strtolower(pathinfo($rawEvidence, PATHINFO_EXTENSION)) : '';
                            $isPdf = $ext === 'pdf';
                            ?>
                            <article class="rlm-card">
                                <div class="rlm-card-top">
                                    <div>
                                        <div class="rlm-main">
                                            <span class="rlm-badge <?= $badgeClass ?>"><?= htmlspecialchars($typeLabel) ?></span>
                                            #<?= (int)($t['id'] ?? 0) ?>
                                        </div>
                                        <div class="rlm-meta">
                                            <?= htmlspecialchars($t['creator_name'] ?? '—') ?> · <?= htmlspecialchars($t['created_at'] ?? '—') ?>
                                        </div>
                                    </div>
                                    <div class="rlm-amount <?= ($t['type'] ?? '') === 'refund' ? 'refund' : '' ?>">
                                        <?= ($t['type'] ?? '') === 'refund' ? '-' : '' ?><?= number_format((float)($t['amount'] ?? 0), 2) ?>
                                    </div>
                                </div>
                                <div class="rlm-row">
                                    <span class="rlm-label">طريقة الدفع</span>
                                    <span class="rlm-value"><?= htmlspecialchars($t['payment_method'] ?? '—') ?></span>
                                </div>
                                <?php if (!empty($t['notes'])): ?>
                                <div class="rlm-row">
                                    <span class="rlm-label">ملاحظات</span>
                                    <span class="rlm-value"><?= htmlspecialchars($t['notes']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($evidenceUrl): ?>
                                <div class="rlm-row">
                                    <span class="rlm-label">الإثبات</span>
                                    <a href="<?= htmlspecialchars($evidenceUrl) ?>" target="_blank" class="rlm-evidence">
                                        <?php if ($isPdf): ?>
                                            PDF
                                        <?php else: ?>
                                            <img src="<?= htmlspecialchars($evidenceUrl) ?>" alt="إثبات الدفع">
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isAdmin): ?>
        <section class="rlm-box">
            <div class="rlm-box-header">
                <h3>تحديثات الإيصال</h3>
                <span class="rlm-count"><?= count($auditLogs ?? []) ?> تحديث</span>
            </div>
            <div class="rlm-scroll">
                <?php if (empty($auditLogs)): ?>
                    <div class="rlm-empty">لا يوجد سجل تعديلات لهذا الإيصال.</div>
                <?php else: ?>
                    <div class="rlm-list">
                        <?php foreach ($auditLogs as $log): ?>
                            <article class="rlm-card">
                                <div class="rlm-card-top">
                                    <div>
                                        <div class="rlm-main"><code><?= htmlspecialchars($log['field_name'] ?? '—') ?></code></div>
                                        <div class="rlm-meta">
                                            <?= htmlspecialchars($log['changer_name'] ?? '—') ?> · <?= htmlspecialchars($log['role'] ?? '—') ?>
                                        </div>
                                    </div>
                                    <div class="rlm-meta"><?= htmlspecialchars($log['changed_at'] ?? '—') ?></div>
                                </div>
                                <div class="rlm-change">
                                    <div class="rlm-old"><?= htmlspecialchars($log['old_value'] ?? '—') ?></div>
                                    <span class="rlm-arrow">←</span>
                                    <div class="rlm-new"><?= htmlspecialchars($log['new_value'] ?? '—') ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
