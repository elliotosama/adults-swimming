<?php // views/receipts/payment.php
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
    padding: 32px 20px 60px;
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
    border-color: var(--accent);
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

.payment-form {
    margin-top: 24px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    z-index: 10;
}

.payment-form-header {
    padding: 14px 20px;
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}

.payment-form-body {
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
    border-color: var(--accent);
    box-shadow: 0 0 0 3px #00b4d820;
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

.alert-info {
    background: #00b4d810;
    border: 1px solid #00b4d840;
    color: var(--accent);
}

/* Badge for renewal type */
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

/* Evidence field */
#pay-evidence-field {
    display: none;
    flex-direction: column;
    gap: 6px;
}
#pay-evidence-field.visible {
    display: flex;
}

input[type="file"].form-control {
    padding: 8px 14px;
    cursor: pointer;
}
</style>

<div class="search-wrap">
    <div class="page-header">
        <div>
            <h1 class="page-title">💳 إضافة دفعة</h1>
            <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
        </div>
        <!-- FIX: back button uses browser history -->
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
            <form method="GET" action="<?= APP_URL ?>/receipt/payment"
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
            <?php endif; ?>

            <?php if (!empty($client) && empty($receipts)): ?>
                <div class="alert alert-info" style="margin-top:12px;">
                    ℹ️ لا توجد إيصالات غير مكتملة لهذا العميل.
                </div>
            <?php endif; ?>

            <?php if (!empty($client)): ?>
                <div style="margin-top:16px; padding:14px;
                            background:var(--surface-2);
                            border:1px solid var(--border);
                            border-radius:var(--radius);
                            display:flex; gap:24px; flex-wrap:wrap; align-items:center;
                            position:relative; z-index:10;">
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);">الاسم</div>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($client['client_name']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);">الهاتف</div>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($client['phone'] ?? '—') ?></div>
                    </div>
                    <?php if (!empty($client['age'])): ?>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);">العمر</div>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($client['age']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($receipts)): ?>

    <!-- Receipt picker — only not_completed -->
    <div style="font-size:13px; color:var(--text-muted); margin-bottom:8px;
                position:relative; z-index:10;">
        اختر الإيصال الذي تريد إضافة دفعة عليه: (يُعرض فقط الإيصالات غير المكتملة)
    </div>

    <div class="receipt-pick" id="receiptPick">
        <?php foreach ($receipts as $r):
            $planPrice = (float) ($r['plan_price'] ?? 0);
            $totalPaid = (float) ($r['total_paid'] ?? 0);
            $remaining = max(0, $planPrice - $totalPaid);

            // Renewal type badge
            $rtMap = [
                'new'              => ['label' => 'جديد',        'class' => 'new'],
                'current_renewal'  => ['label' => 'تجديد حالي', 'class' => 'current'],
                'previous_renewal' => ['label' => 'تجديد سابق', 'class' => 'previous'],
            ];
            $rtKey  = $r['renewal_type'] ?? 'new';
            $rtMeta = $rtMap[$rtKey] ?? ['label' => $rtKey, 'class' => 'new'];
        ?>
        <div class="receipt-card"
             data-id="<?= $r['id'] ?>"
             data-remaining="<?= $remaining ?>"
             data-plan-price="<?= $planPrice ?>"
             onclick="selectReceipt(<?= $r['id'] ?>, <?= $remaining ?>, <?= $planPrice ?>)">

            <div class="receipt-card-header">
                <span style="font-weight:700;color:var(--text);">
                    #<?= $r['id'] ?> — <?= htmlspecialchars($r['plan_name'] ?? '—') ?>
                </span>
                <span style="font-size:12px;color:var(--text-muted);">
                    <?= htmlspecialchars($r['branch_name'] ?? '—') ?>
                </span>
                <!-- Renewal type badge -->
                <span class="renewal-badge <?= $rtMeta['class'] ?>">
                    <?= $rtMeta['label'] ?>
                </span>
                <span style="font-size:11px;font-weight:700;color:#fbbf24;">غير مكتمل</span>
            </div>

            <div class="receipt-card-body">
                <div class="rc-item">
                    <label>أول جلسة</label>
                    <span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span>
                </div>
                <div class="rc-item">
                    <label>آخر جلسة</label>
                    <span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span>
                </div>
                <div class="rc-item">
                    <label>سعر الخطة</label>
                    <span><?= number_format($planPrice, 0) ?></span>
                </div>
                <div class="rc-item">
                    <label>إجمالي المدفوع (صافي)</label>
                    <span style="color:var(--success);"><?= number_format($totalPaid, 0) ?></span>
                </div>
                <div class="rc-item">
                    <label>المتبقي</label>
                    <span style="color:<?= $remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= number_format($remaining, 0) ?>
                    </span>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment form -->
    <form method="POST" action="<?= APP_URL ?>/receipt/payment"
          id="paymentForm" style="display:none;" enctype="multipart/form-data">
        <input type="hidden" name="receipt_id" id="selectedReceiptId">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search ?? '') ?>">

        <div class="payment-form">
            <div class="payment-form-header">💳 تفاصيل الدفعة</div>
            <div class="payment-form-body">
                <div class="form-grid-3">

                    <div class="form-field">
                        <label class="form-label">
                            المبلغ <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="amount" id="payAmount"
                               class="form-control" placeholder="0" min="1" step="0.01" required>
                        <span class="field-hint">
                            المتبقي الحالي: <strong id="currentRemaining">—</strong>
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            طريقة الدفع <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="payment_method" id="paymentMethodSelect"
                                class="form-control" required
                                onchange="togglePayEvidence(this.value)">
                            <option value="">— اختر —</option>
                            <option value="cash">نقداً</option>
                            <option value="instapay">InstaPay</option>
                            <option value="vodafone_cash">Vodafone Cash</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">ملاحظات</label>
                        <input type="text" name="notes" class="form-control"
                               placeholder="اختياري...">
                    </div>

                </div>

                <!-- Evidence upload -->
                <div style="margin-top:16px;">
                    <div class="form-field" id="pay-evidence-field">
                        <label class="form-label">
                            إثبات الدفع <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="file" name="transaction_evidence" id="payEvidence"
                               class="form-control" accept="image/*,application/pdf">
                        <span class="field-hint">صورة أو ملف PDF (مطلوب للدفع الإلكتروني)</span>
                    </div>
                </div>

                <div style="margin-top:18px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">💾 تسجيل الدفعة</button>
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('paymentForm').style.display='none'">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
function selectReceipt(id, remaining, planPrice) {
    document.querySelectorAll('.receipt-card').forEach(function(c) {
        c.classList.remove('selected');
    });
    document.querySelector('.receipt-card[data-id="' + id + '"]').classList.add('selected');

    document.getElementById('selectedReceiptId').value = id;

    var formattedRemaining = parseFloat(remaining).toLocaleString('ar-EG');
    document.getElementById('currentRemaining').textContent = formattedRemaining;

    var amt = parseFloat(remaining);
    document.getElementById('payAmount').value = amt > 0 ? amt : '';

    var form = document.getElementById('paymentForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth' });
}

function togglePayEvidence(method) {
    var field = document.getElementById('pay-evidence-field');
    var input = document.getElementById('payEvidence');
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