<?php // views/receipts/refund.php
require ROOT . '/views/includes/layout_top.php';
?>
<style>
:root {
    --surface:    #111d2b;
    --surface-2:  #0d1821;
    --border:     #1a2e42;
    --text:       #e0eaf4;
    --text-muted: #5a7a96;
    --accent:     #00b4d8;
    --success:    #34c789;
    --danger:     #e05c5c;
    --radius:     14px;
}

.search-wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 32px 20px 0;
    position: relative;
    z-index: 10;
}

.receipt-pick {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
    position: relative;
    z-index: 10;
}

.receipt-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .2s;
    position: relative;
    z-index: 10;
}

.receipt-card:hover,
.receipt-card.selected {
    border-color: var(--danger);
}

.receipt-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px;
    padding: 12px 18px;
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
}

.receipt-card-body {
    padding: 16px 18px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

@media (max-width: 600px) {
    .receipt-card-body { grid-template-columns: 1fr 1fr; }
}

.rc-item label {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 2px;
}

.rc-item span {
    font-size: 13px;
    font-weight: 600;
}

.form-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
    position: relative;
    z-index: 10;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
}

.section-icon { font-size: 18px; }

.section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
}

.section-body { padding: 20px; }

.form-field { display: flex; flex-direction: column; gap: 6px; }

.form-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    background: #0a1520;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .25s, box-shadow .25s;
    direction: rtl;
    appearance: none;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px #e05c5c20;
}

.form-control::placeholder { color: #2a4a64; }

select.form-control option { background: var(--surface); }

.field-hint {
    font-size: 11px;
    color: var(--text-muted);
}

.alert {
    border-radius: var(--radius);
    padding: .85rem 1.1rem;
    font-size: .88rem;
    margin-bottom: 1.4rem;
    position: relative;
    z-index: 10;
}

.alert-error {
    background: #e05c5c18;
    border: 1px solid #e05c5c50;
    color: var(--danger);
}

/* Renewal type badges */
.renewal-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.renewal-badge.new           { background: #0f2a1a; color: #34c789; border: 1px solid #1a5c30; }
.renewal-badge.current       { background: #0a1a2a; color: #00b4d8; border: 1px solid #1a3a4a; }
.renewal-badge.previous      { background: #1a1a00; color: #fbbf24; border: 1px solid #3a3a00; }

/* Refund form — sits in its own stacking context */
.refund-form-outer {
    max-width: 860px;
    margin: 0 auto;
    padding: 24px 20px 60px;
    position: relative;
    z-index: 20;
}

.refund-form-inner {
    background: var(--surface);
    border: 1px solid #5a2020;
    border-radius: 12px;
    overflow: hidden;
}

.refund-form-header {
    padding: 14px 20px;
    background: #2a1515;
    border-bottom: 1px solid #5a2020;
    font-size: 14px;
    font-weight: 600;
    color: #fca5a5;
}

.refund-form-body {
    padding: 22px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

@media (max-width: 600px) {
    .form-grid-3 { grid-template-columns: 1fr; }
}

/* Evidence field */
#refund-evidence-field {
    display: none;
    flex-direction: column;
    gap: 6px;
}
#refund-evidence-field.visible {
    display: flex;
}

input[type="file"].form-control {
    padding: 8px 14px;
    cursor: pointer;
}

/* FIX #7: fully-paid-but-not-completed badge */
.badge-fully-paid {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: #0a2a1a;
    color: #34c789;
    border: 1px solid #1a5c30;
}
</style>

<div class="search-wrap">
    <div class="page-header">
        <div>
            <h1 class="page-title">↩️ استرداد مبلغ</h1>
            <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
        </div>
        <button onclick="history.back()" class="btn btn-secondary" type="button">→ رجوع</button>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Client search -->
    <div class="form-section">
        <div class="section-header">
            <div class="section-icon">🔍</div>
            <span class="section-title">البحث عن العميل</span>
        </div>
        <div class="section-body">
            <form method="GET" action="<?= APP_URL ?>/receipt/refund"
                  style="display:flex;gap:10px;align-items:flex-end;">
                <div class="form-field" style="flex:1;">
                    <label class="form-label">ابحث بالاسم، رقم الهاتف (مع أو بدون كود الدولة)، أو رقم الإيصال</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="مثال: أحمد أو 01012345678 أو 1012345678 أو #1234"
                           value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
            </form>

            <?php if (!empty($search) && empty($client)): ?>
                <div class="alert alert-error" style="margin-top:12px;">
                    ⚠️ لم يتم العثور على عميل.
                </div>
            <?php elseif (!empty($client) && empty($receipts)): ?>
                <div class="alert alert-error" style="margin-top:12px;">
                    ⚠️ لا توجد إيصالات مرتبطة بهذا العميل.
                </div>
            <?php endif; ?>

            <?php if (!empty($client)): ?>
                <div style="margin-top:16px; padding:14px;
                            background:var(--surface-2);
                            border:1px solid var(--border);
                            border-radius:var(--radius);
                            display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);">الاسم</div>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($client['client_name']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);">الهاتف</div>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($client['phone'] ?? '—') ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($receipts)): ?>

    <!-- Receipt picker -->
    <div style="font-size:13px; color:var(--text-muted); margin-bottom:8px;">
        اختر الإيصال الذي تريد استرداد مبلغ منه:
    </div>

    <div class="receipt-pick" id="receiptPick">
        <?php foreach ($receipts as $r):
            $planPrice  = (float)($r['plan_price'] ?? 0);
            $grossPaid  = (float)($r['gross_paid']  ?? $r['total_paid'] ?? 0);
            $refunded   = (float)($r['total_refunded'] ?? 0);
            $netPaid    = $grossPaid - $refunded;
            $rem        = $planPrice > 0 ? max(0, $planPrice - $netPaid) : 0;
            $maxRefund  = max(0, $grossPaid - $refunded); // what can still be refunded

            // FIX #7: a not_completed receipt that was fully paid IS eligible for refund
            $isFullyPaidNotCompleted = (
                ($r['receipt_status'] ?? '') === 'not_completed'
                && $planPrice > 0
                && $grossPaid >= $planPrice
            );

            // Renewal type meta
            $rtMap = [
                'new'              => ['label' => 'جديد',        'class' => 'new'],
                'current_renewal'  => ['label' => 'تجديد حالي', 'class' => 'current'],
                'previous_renewal' => ['label' => 'تجديد سابق', 'class' => 'previous'],
            ];
            $rtKey  = $r['renewal_type'] ?? 'new';
            $rtMeta = $rtMap[$rtKey] ?? ['label' => $rtKey, 'class' => 'new'];

            // Status meta
            $st = $r['receipt_status'] ?? '';
            $stColors = ['completed' => '#22c55e', 'not_completed' => '#fbbf24', 'pending' => '#818cf8'];
            $stLabels = ['completed' => 'مكتمل', 'not_completed' => 'غير مكتمل', 'pending' => 'معلّق'];
        ?>
        <div class="receipt-card" data-id="<?= $r['id'] ?>"
             data-max-refund="<?= $maxRefund ?>"
             onclick="selectReceipt(<?= $r['id'] ?>, <?= $netPaid ?>, <?= $maxRefund ?>)">

            <div class="receipt-card-header">
                <span style="font-weight:700;color:var(--text);">
                    #<?= htmlspecialchars($r['receipt_ref'] ?? $r['id']) ?>
                    — <?= htmlspecialchars($r['client_name'] ?? '—') ?>
                </span>
                <span style="font-size:12px;color:var(--text-muted);">
                    <?= htmlspecialchars($r['branch_name'] ?? '—') ?>
                </span>
                <span class="renewal-badge <?= $rtMeta['class'] ?>">
                    <?= $rtMeta['label'] ?>
                </span>
                <span style="font-size:11px;font-weight:700;color:<?= $stColors[$st] ?? 'var(--text-muted)' ?>;">
                    <?= $stLabels[$st] ?? $st ?>
                </span>
                <?php if ($isFullyPaidNotCompleted): ?>
                    <!-- FIX #7: indicate this not_completed receipt was fully paid -->
                    <span class="badge-fully-paid" title="الإيصال غير مكتمل لكن تم سداده بالكامل">
                        💰 مدفوع بالكامل
                    </span>
                <?php endif; ?>
            </div>

            <div class="receipt-card-body">
                <div class="rc-item">
                    <label>الخطة</label>
                    <span><?= htmlspecialchars($r['plan_name'] ?? '—') ?></span>
                </div>
                <div class="rc-item">
                    <label>أول جلسة</label>
                    <span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span>
                </div>
                <div class="rc-item">
                    <label>آخر جلسة</label>
                    <span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span>
                </div>
                <div class="rc-item">
                    <label>إجمالي المدفوع (صافي)</label>
                    <span style="color:var(--success);"><?= number_format($netPaid, 0) ?></span>
                </div>
                <div class="rc-item">
                    <label>الحد الأقصى للاسترداد</label>
                    <span style="color:var(--danger);"><?= number_format($maxRefund, 0) ?></span>
                </div>
                <div class="rc-item">
                    <label>المتبقي للسداد</label>
                    <span style="color:<?= $rem > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= number_format($rem, 0) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div><!-- /.search-wrap -->

<?php if (!empty($receipts)): ?>
<div class="refund-form-outer" id="refundFormOuter" style="display:none;">
    <form method="POST" action="<?= APP_URL ?>/receipt/refund"
          id="refundForm" enctype="multipart/form-data">
        <input type="hidden" name="receipt_id" id="selectedReceiptId">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search ?? '') ?>">

        <div class="refund-form-inner">
            <div class="refund-form-header">↩️ تفاصيل الاسترداد</div>
            <div class="refund-form-body">
                <div class="form-grid-3">

                    <div class="form-field">
                        <label class="form-label">
                            المبلغ المُسترَد <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="amount" id="refundAmount"
                               class="form-control" placeholder="0" min="1" step="0.01" required>
                        <span class="field-hint">
                            الحد الأقصى للاسترداد: <strong id="currentMaxRefund">—</strong>
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            طريقة الاسترداد <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="payment_method" id="refundMethodSelect"
                                class="form-control" required
                                onchange="toggleRefundEvidence(this.value)">
                            <option value="">— اختر —</option>
                            <option value="cash">نقداً</option>
                            <option value="instapay">InstaPay</option>
                            <option value="vodafone_cash">Vodafone Cash</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">سبب الاسترداد</label>
                        <input type="text" name="notes" class="form-control"
                               placeholder="اختياري...">
                    </div>

                </div>

                <!-- Evidence — shown for non-cash -->
                <div style="margin-top:16px;">
                    <div class="form-field" id="refund-evidence-field">
                        <label class="form-label">
                            إثبات الاسترداد <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="file" name="transaction_evidence" id="refundEvidence"
                               class="form-control" accept="image/*,application/pdf">
                        <span class="field-hint">صورة أو ملف PDF (مطلوب للاسترداد الإلكتروني)</span>
                    </div>
                </div>

                <div style="margin-top:18px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary"
                            style="background:var(--danger);box-shadow:0 4px 20px rgba(239,68,68,.35);">
                        ↩️ تأكيد الاسترداد
                    </button>
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('refundFormOuter').style.display='none'">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
function selectReceipt(id, netPaid, maxRefund) {
    document.querySelectorAll('.receipt-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.receipt-card[data-id="${id}"]`).classList.add('selected');

    document.getElementById('selectedReceiptId').value = id;

    // FIX #7: show max-refund (not just net paid) as the ceiling hint
    document.getElementById('currentMaxRefund').textContent =
        parseFloat(maxRefund).toLocaleString('ar-EG');

    // Set the max attribute on the amount field so the browser validates too
    const amountInput = document.getElementById('refundAmount');
    amountInput.max   = maxRefund;
    amountInput.value = '';

    document.getElementById('refundMethodSelect').value = '';
    toggleRefundEvidence('');

    const outer = document.getElementById('refundFormOuter');
    outer.style.display = 'block';
    outer.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleRefundEvidence(method) {
    const field = document.getElementById('refund-evidence-field');
    const input = document.getElementById('refundEvidence');
    if (method && method !== 'cash') {
        field.classList.add('visible');
        input.required = true;
    } else {
        field.classList.remove('visible');
        input.required = false;
        input.value = '';
    }
}
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>