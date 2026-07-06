<?php // views/admin/receipts/show.php
require ROOT . '/views/includes/layout_top.php';

function formatAmPm(string $time): string {
    if (empty($time)) return '—';
    try {
        $dt = new DateTime($time);
        return $dt->format('g:i A');
    } catch (\Exception $e) {
        return $time;
    }
}

$statusMap = [
    'completed'     => ['badge-success', 'مكتمل'],
    'not_completed' => ['badge-danger',  'غير مكتمل'],
    'pending'       => ['badge-warning', 'معلّق'],
];
[$sCls, $sLabel] = $statusMap[$receipt['receipt_status']] ?? ['badge-secondary', $receipt['receipt_status']];

function evidenceUrl(string $raw): string {
    if (empty($raw)) return '';
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
        return $raw;
    }
    $filename = basename($raw);
    return APP_URL . '/uploads/evidence/' . $filename;
}

// ── Role gate: transaction history + audit log are admin-only ───────────
$isAdmin = (auth_user()['role'] === 'admin');

// ── Payment summary (paid / remaining / refunded) ────────────────────────
$ns            = $ns ?? [];
$netPaid       = (float) ($ns['netPaid']       ?? ($totalPaid ?? 0));
$remaining     = (float) ($ns['remaining']     ?? 0);
$totalRefunded = (float) ($ns['totalRefunded'] ?? 0);
$hasRefund     = $totalRefunded > 0;
?>

<style>
/* ── Match index.php's dark theme: bg, surface, border, accent, Cairo font ── */
:root {
    --bg:      #1E1E2D;
    --surface: #252736;
    --border:  #3C3F58;
    --primary: #007ACC;
    --text:    #FFFFFF;
    --muted:   #ffffffb3;
    --success: #98C379;
    --danger:  #E06C75;
}
body {
    background: var(--bg);
    font-family: 'Cairo', sans-serif;
    font-size: 16px;
    font-weight: bold;
    color: var(--text);
}
html,
body,
.page,
.page--full {
    background: var(--bg) !important;
}
.card {
    background: var(--surface) !important;
    color: var(--text) !important;
    border-color: var(--border) !important;
}

/* ── Page header ─────────────────────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    margin-bottom: 1.5rem;
}
.page-header-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    flex-shrink: 0;
}

/* ── Detail grid ─────────────────────────────────────────────────────────── */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
}
.detail-item {
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.detail-label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted);
    font-weight: 600;
}
.detail-value {
    font-size: .92rem;
    color: var(--text);
}

/* ── Payment summary strip ───────────────────────────────────────────────── */
.payment-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border);
}
.payment-summary-item {
    display: flex;
    flex-direction: column;
    gap: .3rem;
    padding: .85rem 1rem;
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
}
.payment-summary-label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted);
    font-weight: 600;
}
.payment-summary-value {
    font-size: 1.15rem;
    font-weight: 700;
}
.payment-summary-value.paid      { color: var(--success); }
.payment-summary-value.remaining-due   { color: var(--danger); }
.payment-summary-value.remaining-clear { color: var(--success); }
.payment-summary-value.refunded  { color: var(--danger); }

/* ── Section header ──────────────────────────────────────────────────────── */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
}
.section-header h2 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}
.section-header-meta {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.section-header-meta span {
    font-size: .9rem;
    color: var(--muted);
    white-space: nowrap;
}

/* ── Table wrapper: horizontal scroll on small screens ───────────────────── */
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.table-wrap table {
    min-width: 640px; /* prevents columns from collapsing too narrow */
    width: 100%;
}

/* ── Evidence cell ───────────────────────────────────────────────────────── */
.evidence-cell {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}
.evidence-thumb-wrap {
    display: block;
    width: 52px;
    height: 52px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--border, #e2e8f0);
    flex-shrink: 0;
    cursor: pointer;
    background: none;
    padding: 0;
}
.evidence-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: opacity .15s;
}
.evidence-thumb:hover { opacity: .8; }
.evidence-remove-btn {
    padding: .15rem .4rem !important;
    font-size: .75rem !important;
    line-height: 1.2 !important;
}

/* ── Row actions ─────────────────────────────────────────────────────────── */
.td-actions {
    display: flex;
    gap: .35rem;
    flex-wrap: wrap;
}

/* ── Audit log table: narrower min so it fits sooner ────────────────────── */
.audit-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.audit-table-wrap table {
    min-width: 520px;
    width: 100%;
}

/* ── Evidence lightbox modal ─────────────────────────────────────────────── */
.evidence-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.82);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.evidence-modal-overlay.visible {
    display: flex;
}
.evidence-modal-box {
    position: relative;
    max-width: min(90vw, 800px);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.evidence-modal-box img {
    max-width: 100%;
    max-height: 78vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    background: #111;
}
.evidence-modal-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}
.evidence-modal-close {
    position: absolute;
    top: -14px;
    left: -14px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    color: #111;
    border: none;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.4);
}
.evidence-modal-close:hover { background: #f1f1f1; }
.evidence-modal-link {
    color: #fff;
    font-size: .82rem;
    text-decoration: underline;
    opacity: .85;
}
.evidence-modal-link:hover { opacity: 1; }

/* ── Mobile card view for transactions (≤ 600px) ────────────────────────── */
@media (max-width: 600px) {
    /* Hide the transactions table, show cards instead */
    .tx-table-wrap { display: none; }
    .tx-cards      { display: flex; flex-direction: column; gap: .75rem; }

    .tx-card {
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        padding: .85rem 1rem;
        background: var(--surface, #fff);
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .tx-card-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .5rem;
        font-size: .87rem;
    }
    .tx-card-label {
        color: var(--muted);
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        flex-shrink: 0;
    }
    .tx-card-value {
        color: var(--text);
        text-align: left;
        word-break: break-word;
    }
    .tx-card-actions {
        display: flex;
        gap: .4rem;
        padding-top: .35rem;
        border-top: 1px solid var(--border, #e2e8f0);
        flex-wrap: wrap;
    }

    /* Audit: also hide table, show simple stacked list */
    .audit-table-wrap { display: none; }
    .audit-cards      { display: flex; flex-direction: column; gap: .65rem; }
    .audit-card {
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        padding: .75rem .9rem;
        font-size: .84rem;
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }
    .audit-card-field {
        font-weight: 700;
        color: var(--text);
    }
    .audit-card-change {
        display: flex;
        gap: .4rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .audit-card-meta {
        font-size: .78rem;
        color: var(--muted);
    }

    .payment-summary {
        grid-template-columns: 1fr 1fr;
    }
}

/* ── On larger screens hide the card fallbacks ───────────────────────────── */
@media (min-width: 601px) {
    .tx-cards   { display: none; }
    .audit-cards { display: none; }
}

/* ── Small phone tweaks ──────────────────────────────────────────────────── */
@media (max-width: 400px) {
    .detail-grid {
        grid-template-columns: 1fr 1fr;
    }
    .payment-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🧾 إيصال #<?= $receipt['id'] ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= APP_URL ?>/receipt/edit?id=<?= $receipt['id'] ?>" class="btn btn-warning">تعديل</a>
        <a href="<?= APP_URL ?>/receipts" class="btn btn-secondary">← رجوع</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- ─── تفاصيل الإيصال ────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem; padding: 14px;">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text)">تفاصيل الإيصال</h2>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">العميل</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['client_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الفرع</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['branch_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الكابتن</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['captain_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">المنشئ</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['creator_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الخطة</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['plan_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">المستوى</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['level'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">وقت التمرين</span>
            <span class="detail-value"><?php
                $et = $receipt['exercise_time'] ?? '';
                echo $et ? htmlspecialchars(formatAmPm($et)) : '—';
            ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">أول جلسة</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['first_session'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">آخر جلسة</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['last_session'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">جلسة التجديد</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['renewal_session'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">نوع التجديد</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['renewal_type'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ الإنشاء</span>
            <span class="detail-value"><?= htmlspecialchars($receipt['created_at'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الحالة</span>
            <span class="detail-value"><span class="badge <?= $sCls ?>"><?= $sLabel ?></span></span>
        </div>
        <?php if (!empty($receipt['pdf_path'])): ?>
        <div class="detail-item">
            <span class="detail-label">ملف PDF</span>
            <span class="detail-value">
                <a href="<?= htmlspecialchars('/uploads/receipts/' . $receipt['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-secondary">📄 عرض الملف</a>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── ملخص الدفع: المدفوع / المتبقي / المسترد ─────────────────────── -->
    <div class="payment-summary">
        <div class="payment-summary-item">
            <span class="payment-summary-label">المبلغ المدفوع</span>
            <span class="payment-summary-value paid"><?= number_format($netPaid, 2) ?></span>
        </div>
        <div class="payment-summary-item">
            <span class="payment-summary-label">المتبقي</span>
            <span class="payment-summary-value <?= $remaining > 0 ? 'remaining-due' : 'remaining-clear' ?>">
                <?= number_format($remaining, 2) ?>
            </span>
        </div>
        <?php if ($hasRefund): ?>
        <div class="payment-summary-item">
            <span class="payment-summary-label">المبلغ المسترد</span>
            <span class="payment-summary-value refunded">↩️ <?= number_format($totalRefunded, 2) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ─── المعاملات المالية (Admin only) ─────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem; padding: 14px;">
    <div class="section-header">
        <h2>💳 المعاملات المالية</h2>
        <div class="section-header-meta">
            <span>إجمالي المدفوع: <strong style="color:var(--text)"><?= number_format($netPaid, 2) ?></strong></span>
            <?php if ($hasRefund): ?>
                <span>إجمالي المسترد: <strong style="color:var(--danger)"><?= number_format($totalRefunded, 2) ?></strong></span>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/transaction/create?receipt_id=<?= $receipt['id'] ?>" class="btn btn-sm btn-primary">+ إضافة معاملة</a>
        </div>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="empty-state" style="padding:1.5rem 0">
            <p style="color:var(--muted)">لا توجد معاملات مالية لهذا الإيصال بعد.</p>
        </div>
    <?php else: ?>

        <!-- Desktop table (hidden on mobile via CSS) -->
        <div class="table-wrap tx-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>طريقة الدفع</th>
                        <th>المبلغ</th>
                        <th>المنشئ</th>
                        <th>التاريخ</th>
                        <th>ملاحظات</th>
                        <th>الإثبات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                        $typeMap = [
                            'payment'  => ['badge-success', 'دفعة'],
                            'refund'   => ['badge-danger',  'استرداد'],
                            'discount' => ['badge-warning', 'خصم'],
                        ];
                        [$tCls, $tLabel] = $typeMap[$t['type']] ?? ['badge-secondary', $t['type']];
                        $rawEvidence = $t['attachment'] ?? $t['transaction_evidence'] ?? $t['evidence'] ?? null;
                        $evidenceUrl = $rawEvidence ? evidenceUrl($rawEvidence) : '';
                        $ext         = $rawEvidence ? strtolower(pathinfo($rawEvidence, PATHINFO_EXTENSION)) : '';
                        $isPdf       = ($ext === 'pdf');
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.82rem"><?= $t['id'] ?></td>
                            <td><span class="badge <?= $tCls ?>"><?= $tLabel ?></span></td>
                            <td><?= htmlspecialchars($t['payment_method'] ?? '—') ?></td>
                            <td><strong style="<?= $t['type'] === 'refund' ? 'color:var(--danger)' : '' ?>">
                                <?= $t['type'] === 'refund' ? '−' : '' ?><?= number_format($t['amount'], 2) ?>
                            </strong></td>
                            <td style="font-size:.85rem"><?= htmlspecialchars($t['creator_name'] ?? '—') ?></td>
                            <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
                            <td style="font-size:.82rem;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($t['notes'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!empty($evidenceUrl)): ?>
                                    <div class="evidence-cell">
                                        <?php if (!$isPdf): ?>
                                            <button type="button" class="evidence-thumb-wrap"
                                                    onclick="openEvidenceModal('<?= htmlspecialchars($evidenceUrl, ENT_QUOTES) ?>')">
                                                <img src="<?= htmlspecialchars($evidenceUrl) ?>"
                                                     class="evidence-thumb"
                                                     alt="إثبات الدفع"
                                                     onerror="this.closest('.evidence-thumb-wrap').innerHTML='<span style=\'font-size:.7rem;color:var(--muted);padding:.2rem\'>خطأ</span>'">
                                            </button>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($evidenceUrl) ?>" target="_blank" class="btn btn-sm btn-secondary">📄 PDF</a>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST"
                                                  action="<?= APP_URL ?>/transaction/remove-evidence?id=<?= $t['id'] ?>"
                                                  style="display:inline"
                                                  onsubmit="return confirm('هل أنت متأكد من حذف هذه الصورة؟')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                <button type="submit" class="btn btn-sm btn-danger evidence-remove-btn" title="حذف الصورة">🗑</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="td-actions">
                                    <a href="<?= APP_URL ?>/transaction/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                    <form method="POST" action="<?= APP_URL ?>/transaction/delete?id=<?= $t['id'] ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذه المعاملة؟')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards (hidden on desktop via CSS) -->
        <div class="tx-cards">
            <?php foreach ($transactions as $t): ?>
                <?php
                $typeMap = [
                    'payment'  => ['badge-success', 'دفعة'],
                    'refund'   => ['badge-danger',  'استرداد'],
                    'discount' => ['badge-warning', 'خصم'],
                ];
                [$tCls, $tLabel] = $typeMap[$t['type']] ?? ['badge-secondary', $t['type']];
                $rawEvidence = $t['attachment'] ?? $t['transaction_evidence'] ?? $t['evidence'] ?? null;
                $evidenceUrl = $rawEvidence ? evidenceUrl($rawEvidence) : '';
                $ext         = $rawEvidence ? strtolower(pathinfo($rawEvidence, PATHINFO_EXTENSION)) : '';
                $isPdf       = ($ext === 'pdf');
                ?>
                <div class="tx-card">
                    <div class="tx-card-row">
                        <span class="tx-card-label">#<?= $t['id'] ?> &nbsp; <span class="badge <?= $tCls ?>"><?= $tLabel ?></span></span>
                        <strong class="tx-card-value" style="<?= $t['type'] === 'refund' ? 'color:var(--danger)' : '' ?>">
                            <?= $t['type'] === 'refund' ? '−' : '' ?><?= number_format($t['amount'], 2) ?>
                        </strong>
                    </div>
                    <div class="tx-card-row">
                        <span class="tx-card-label">طريقة الدفع</span>
                        <span class="tx-card-value"><?= htmlspecialchars($t['payment_method'] ?? '—') ?></span>
                    </div>
                    <div class="tx-card-row">
                        <span class="tx-card-label">المنشئ</span>
                        <span class="tx-card-value"><?= htmlspecialchars($t['creator_name'] ?? '—') ?></span>
                    </div>
                    <div class="tx-card-row">
                        <span class="tx-card-label">التاريخ</span>
                        <span class="tx-card-value" style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($t['created_at'] ?? '—') ?></span>
                    </div>
                    <?php if (!empty($t['notes'])): ?>
                    <div class="tx-card-row">
                        <span class="tx-card-label">ملاحظات</span>
                        <span class="tx-card-value" style="color:var(--muted)"><?= htmlspecialchars($t['notes']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($evidenceUrl)): ?>
                    <div class="tx-card-row">
                        <span class="tx-card-label">الإثبات</span>
                        <span class="tx-card-value">
                            <?php if (!$isPdf): ?>
                                <button type="button" class="evidence-thumb-wrap" style="width:44px;height:44px"
                                        onclick="openEvidenceModal('<?= htmlspecialchars($evidenceUrl, ENT_QUOTES) ?>')">
                                    <img src="<?= htmlspecialchars($evidenceUrl) ?>" class="evidence-thumb" alt="إثبات الدفع">
                                </button>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($evidenceUrl) ?>" target="_blank" class="btn btn-sm btn-secondary">📄 PDF</a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="tx-card-actions">
                        <a href="<?= APP_URL ?>/transaction/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                        <form method="POST" action="<?= APP_URL ?>/transaction/delete?id=<?= $t['id'] ?>"
                              style="display:inline"
                              onsubmit="return confirm('هل أنت متأكد من حذف هذه المعاملة؟')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                        </form>
                        <?php if (!empty($evidenceUrl)): ?>
                            <form method="POST"
                                  action="<?= APP_URL ?>/transaction/remove-evidence?id=<?= $t['id'] ?>"
                                  style="display:inline"
                                  onsubmit="return confirm('هل أنت متأكد من حذف هذه الصورة؟')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm btn-danger evidence-remove-btn">🗑 حذف الإثبات</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<!-- ─── سجل التدقيق (Admin only) ─────────────────────────────────────── -->
<div class="card" style="padding: 14px;">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text)">📋 سجل التعديلات</h2>

    <?php if (empty($auditLogs)): ?>
        <div class="empty-state" style="padding:1.5rem 0">
            <p style="color:var(--muted)">لا يوجد سجل تعديلات لهذا الإيصال بعد.</p>
        </div>
    <?php else: ?>

        <!-- Desktop audit table -->
        <div class="audit-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>الحقل</th>
                        <th>القيمة القديمة</th>
                        <th>القيمة الجديدة</th>
                        <th>بواسطة</th>
                        <th>الدور</th>
                        <th>التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><code style="font-size:.8rem"><?= htmlspecialchars($log['field_name']) ?></code></td>
                            <td style="color:var(--error);font-size:.82rem"><?= htmlspecialchars($log['old_value'] ?? '—') ?></td>
                            <td style="color:var(--success);font-size:.82rem"><?= htmlspecialchars($log['new_value'] ?? '—') ?></td>
                            <td style="font-size:.85rem"><?= htmlspecialchars($log['changer_name'] ?? '—') ?></td>
                            <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($log['role']) ?></td>
                            <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($log['changed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile audit cards -->
        <div class="audit-cards">
            <?php foreach ($auditLogs as $log): ?>
                <div class="audit-card">
                    <div class="audit-card-field">
                        <code style="font-size:.8rem"><?= htmlspecialchars($log['field_name']) ?></code>
                    </div>
                    <div class="audit-card-change">
                        <span style="color:var(--error)"><?= htmlspecialchars($log['old_value'] ?? '—') ?></span>
                        <span style="color:var(--muted)">←</span>
                        <span style="color:var(--success)"><?= htmlspecialchars($log['new_value'] ?? '—') ?></span>
                    </div>
                    <div class="audit-card-meta">
                        <?= htmlspecialchars($log['changer_name'] ?? '—') ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($log['role']) ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($log['changed_at']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>
<?php endif; // $isAdmin ?>

<!-- ─── Evidence lightbox modal ───────────────────────────────────────── -->
<div class="evidence-modal-overlay" id="evidenceModalOverlay" onclick="if(event.target===this) closeEvidenceModal()">
    <div class="evidence-modal-box">
        <button type="button" class="evidence-modal-close" onclick="closeEvidenceModal()" aria-label="إغلاق">✕</button>
        <img id="evidenceModalImg" src="" alt="إثبات الدفع">
        <div class="evidence-modal-actions">
            <a id="evidenceModalOpenNewTab" href="#" target="_blank" class="evidence-modal-link">فتح في تبويب جديد ↗</a>
        </div>
    </div>
</div>

<script>
function openEvidenceModal(url) {
    document.getElementById('evidenceModalImg').src = url;
    document.getElementById('evidenceModalOpenNewTab').href = url;
    document.getElementById('evidenceModalOverlay').classList.add('visible');
    document.addEventListener('keydown', evidenceModalEscHandler);
}
function closeEvidenceModal() {
    document.getElementById('evidenceModalOverlay').classList.remove('visible');
    document.getElementById('evidenceModalImg').src = '';
    document.removeEventListener('keydown', evidenceModalEscHandler);
}
function evidenceModalEscHandler(e) {
    if (e.key === 'Escape') closeEvidenceModal();
}
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>