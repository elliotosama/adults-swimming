<?php // views/receipts/_view_modal.php — no layout, injected into index.php's modal

function rmFormatAmPm(string $time): string {
    if (empty($time)) return '—';
    try { return (new DateTime($time))->format('g:i A'); }
    catch (\Exception $e) { return $time; }
}

function rmEvidenceUrl(string $raw): string {
    if (empty($raw)) return '';
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) return $raw;
    if (str_starts_with($raw, '/uploads/')) return APP_URL . $raw;
    return APP_URL . '/uploads/evidence/' . basename($raw);
}

$planPrice     = (float) ($receipt['plan_price'] ?? 0);
$netPaid       = (float) ($ns['netPaid']       ?? 0);
$remaining     = (float) ($ns['remaining']     ?? 0);
$grossPaid     = (float) ($ns['grossPaid']     ?? 0);
$totalRefunded = (float) ($ns['totalRefunded'] ?? 0);
$refundPct     = $grossPaid > 0 ? round(($totalRefunded / $grossPaid) * 100) : 0;

$clientPhone = preg_replace('/\s+/', '', ($receipt['country_code'] ?? '') . ($receipt['phone_number'] ?? $receipt['phone'] ?? ''));
$waMessage   = rawurlencode("شكراً لاشتراكك، يمكنك تحميل الإيصال من خلال هذا الرابط:\n") . APP_URL . '/receipt/pdf?id=' . $receipt['id'];
$waLink      = "https://wa.me/{$clientPhone}?text={$waMessage}";

$paymentMethodLabels = [
    'cash'          => 'نقداً',
    'instapay'      => 'InstaPay',
    'vodafone_cash' => 'Vodafone Cash',
    'bank_transfer' => 'تحويل بنكي',
];
$transactionMethods = [];
foreach (($transactions ?? []) as $t) {
    $method = trim((string)($t['payment_method'] ?? ''));
    if ($method !== '') {
        $transactionMethods[] = $paymentMethodLabels[$method] ?? $method;
    }
}
$transactionMethods = array_values(array_unique($transactionMethods));
$receiptMethod = trim((string)($receipt['payment_method'] ?? ''));
$paymentMethodText = $transactionMethods
    ? implode('، ', $transactionMethods)
    : ($paymentMethodLabels[$receiptMethod] ?? ($receiptMethod ?: '—'));

$receiptNotes = array_values(array_filter(array_map(function ($t) {
    return trim((string)($t['notes'] ?? ''));
}, $transactions ?? [])));
?>
<div class="rm-view">
<style>
.rm-view {
    --v-accent: #007ACC; --v-success: #98C379; --v-danger: #E06C75; --v-warning: #D19A66;
    --v-muted: #ffffffb3; --v-border: #3C3F58; --v-surface2: #1E1E2D;
    font-family: 'Cairo', sans-serif; color: #fff; font-size: 1.02rem;
}
.rm-view .rm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px,1fr)); gap: .9rem 1.2rem; margin-bottom: 1.1rem; }
.rm-view .rm-item { display: flex; flex-direction: column; gap: .25rem; }
.rm-view .rm-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--v-muted); font-weight: 700; }
.rm-view .rm-value { font-size: 1rem; line-height: 1.55; }
.rm-view .rm-section-title {
    font-size: .9rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
    color: var(--v-muted); margin: 1.4rem 0 .8rem; padding-bottom: .5rem; border-bottom: 1px solid var(--v-border);
}
.rm-view .rm-section-title:first-child { margin-top: 0; }
.rm-pay-strip { display: flex; gap: 1rem; flex-wrap: wrap; padding: .9rem 1rem; background: var(--v-surface2); border: 1px solid var(--v-border); border-radius: 10px; }
.rm-pay-item { display: flex; flex-direction: column; gap: .25rem; }
.rm-pay-item .rm-label { font-size: .72rem; color: var(--v-muted); }
.rm-pay-item .rm-num { font-size: 1.05rem; font-weight: 700; }
.rm-pay-item .rm-num.green  { color: var(--v-success); }
.rm-pay-item .rm-num.red    { color: var(--v-danger); }
.rm-pay-item .rm-num.yellow { color: var(--v-warning); }
.rm-notes-list { display: flex; flex-direction: column; gap: .55rem; padding: .9rem 1rem; background: var(--v-surface2); border: 1px solid var(--v-border); border-radius: 10px; }
.rm-note { margin: 0; color: #fff; line-height: 1.65; font-size: 1rem; }
.rm-evidence-list { display: flex; flex-wrap: wrap; gap: .5rem; }
.rm-evidence-thumb { width: 74px; height: 74px; border-radius: 8px; overflow: hidden; border: 1px solid var(--v-border); cursor: pointer; background: none; padding: 0; }
.rm-evidence-thumb img { width: 100%; height: 100%; object-fit: cover; }
.rm-evidence-link {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 74px; height: 74px; padding: 0 .7rem; border-radius: 8px;
    border: 1px solid var(--v-border); color: #fff; text-decoration: none;
    background: var(--v-surface2); font-size: .82rem;
}
.rm-actions { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1.4rem; padding-top: 1.2rem; border-top: 1px solid var(--v-border); }
.rm-actions .btn { display: inline-flex; align-items: center; gap: 6px; }
#rm-email-msg { margin-top: .6rem; font-size: .85rem; }
</style>

    <div class="rm-section-title">👤 بيانات العميل</div>
    <div class="rm-grid">
        <div class="rm-item"><span class="rm-label">الاسم</span><span class="rm-value"><?= htmlspecialchars($receipt['client_name'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">الهاتف</span><span class="rm-value"><?= htmlspecialchars(($receipt['country_code'] ?? '') . ' ' . ($receipt['phone_number'] ?? $receipt['phone'] ?? '—')) ?></span></div>
        <div class="rm-item"><span class="rm-label">السن</span><span class="rm-value"><?= htmlspecialchars((string)($receipt['client_age'] ?? '—')) ?></span></div>
        <?php if ($clientEmail): ?>
        <div class="rm-item"><span class="rm-label">البريد الإلكتروني</span><span class="rm-value"><?= htmlspecialchars($clientEmail) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="rm-section-title">🧾 بيانات الإيصال</div>
    <div class="rm-grid">
        <div class="rm-item"><span class="rm-label">رقم الإيصال</span><span class="rm-value"><?= htmlspecialchars((string)($receipt['receipt_ref'] ?? $receipt['id'] ?? '—')) ?></span></div>
        <div class="rm-item"><span class="rm-label">منشئ الإيصال</span><span class="rm-value"><?= htmlspecialchars($receipt['creator_name'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">تاريخ الإنشاء</span><span class="rm-value"><?= htmlspecialchars($receipt['created_at'] ?? '—') ?></span></div>
    </div>

    <div class="rm-section-title">📋 تفاصيل الاشتراك</div>
    <div class="rm-grid">
        <div class="rm-item"><span class="rm-label">الفرع</span><span class="rm-value"><?= htmlspecialchars($receipt['branch_name'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">الكابتن</span><span class="rm-value"><?= htmlspecialchars($receipt['captain_name'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">الاشتراك</span><span class="rm-value"><?= htmlspecialchars($receipt['plan_name'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">المستوى</span><span class="rm-value"><?= htmlspecialchars((string)($receipt['level'] ?? '—')) ?></span></div>
        <div class="rm-item"><span class="rm-label">وقت التمرين</span><span class="rm-value"><?= htmlspecialchars(rmFormatAmPm($receipt['exercise_time'] ?? '')) ?></span></div>
        <div class="rm-item"><span class="rm-label">الحالة</span><span class="rm-value"><?= $receipt['receipt_status'] === 'completed' ? 'مكتمل' : 'غير مكتمل' ?></span></div>
    </div>

    <div class="rm-section-title">📅 مواعيد التمرين</div>
    <div class="rm-grid">
        <div class="rm-item"><span class="rm-label">تاريخ البدايه</span><span class="rm-value"><?= htmlspecialchars($receipt['first_session'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">تاريخ النهايه</span><span class="rm-value"><?= htmlspecialchars($receipt['last_session'] ?? '—') ?></span></div>
        <div class="rm-item"><span class="rm-label">تاريخ التجديد</span><span class="rm-value"><?= htmlspecialchars($receipt['renewal_session'] ?? '—') ?></span></div>
    </div>

    <div class="rm-section-title">💳 الدفع</div>
    <div class="rm-pay-strip">
        <div class="rm-pay-item"><span class="rm-label">قيمة الاشتراك</span><span class="rm-num"><?= number_format($planPrice,0) ?></span></div>
        <div class="rm-pay-item"><span class="rm-label">إجمالي المدفوع</span><span class="rm-num green"><?= number_format($grossPaid,0) ?></span></div>
        <?php if ($totalRefunded > 0): ?>
        <div class="rm-pay-item"><span class="rm-label">المسترد</span><span class="rm-num red"><?= number_format($totalRefunded,0) ?> (<?= $refundPct ?>%)</span></div>
        <?php endif; ?>
        <div class="rm-pay-item"><span class="rm-label">المتبقي</span><span class="rm-num <?= $remaining > 0 ? 'yellow' : 'green' ?>"><?= number_format($remaining,0) ?></span></div>
        <div class="rm-pay-item"><span class="rm-label">طريقة الدفع</span><span class="rm-num" style="font-weight:600;font-size:1rem"><?= htmlspecialchars($paymentMethodText) ?></span></div>
    </div>

    <?php if (!empty($receiptNotes)): ?>
    <div class="rm-section-title">📝 ملاحظات الإيصال</div>
    <div class="rm-notes-list">
        <?php foreach ($receiptNotes as $note): ?>
            <p class="rm-note"><?= htmlspecialchars($note) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $evidences = array_values(array_filter(array_map(function ($t) {
        $raw = $t['attachment'] ?? $t['transaction_evidence'] ?? $t['evidence'] ?? null;
        if (!$raw) return null;
        $url = rmEvidenceUrl($raw);
        $ext = strtolower(pathinfo((string)$raw, PATHINFO_EXTENSION));
        return ['url' => $url, 'is_pdf' => $ext === 'pdf'];
    }, $transactions ?? [])));
    ?>
    <?php if (!empty($evidences)): ?>
    <div class="rm-section-title">📎 إثباتات الدفع</div>
    <div class="rm-evidence-list">
        <?php foreach ($evidences as $ev): ?>
            <?php if ($ev['is_pdf']): ?>
                <a href="<?= htmlspecialchars($ev['url']) ?>" target="_blank" class="rm-evidence-link">PDF</a>
            <?php else: ?>
                <button type="button" class="rm-evidence-thumb" data-rm-evidence="<?= htmlspecialchars($ev['url'], ENT_QUOTES) ?>">
                    <img src="<?= htmlspecialchars($ev['url']) ?>" alt="إثبات دفع" onerror="this.parentElement.style.display='none'">
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="rm-actions">
        <a href="<?= APP_URL ?>/receipt/pdf?id=<?= $receipt['id'] ?>" target="_blank" class="btn btn-sm btn-secondary">⬇️ PDF عربي</a>
        <a href="<?= APP_URL ?>/receipt/pdf?id=<?= $receipt['id'] ?>&lang=en" target="_blank" class="btn btn-sm btn-secondary">⬇️ PDF English</a>
        <?php if (!empty($receipt['is_refunded'])): ?>
        <a href="<?= APP_URL ?>/receipt/refund-pdf?id=<?= $receipt['id'] ?>" target="_blank" class="btn btn-sm btn-secondary">↩️ إيصال الاسترداد</a>
        <?php endif; ?>
        <a href="<?= $waLink ?>" target="_blank" class="btn btn-sm btn-primary">💬 واتساب</a>
        <button type="button" class="btn btn-sm btn-primary" id="rm-send-email-btn" data-receipt-id="<?= $receipt['id'] ?>" <?= $clientEmail ? '' : 'disabled title="لا يوجد بريد إلكتروني"' ?>>✉️ إرسال بريد</button>
        <button type="button" class="btn btn-sm btn-warning" onclick="loadEditModal(<?= $receipt['id'] ?>)">✏️ تحديث الإيصال</button>
        <?php if ($isAdmin): ?>
        <form method="POST" action="<?= APP_URL ?>/receipt/delete?id=<?= $receipt['id'] ?>" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الإيصال؟')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-danger">🗑 حذف</button>
        </form>
        <?php endif; ?>
    </div>
    <div id="rm-email-msg"></div>
</div>
