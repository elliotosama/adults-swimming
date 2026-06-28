<?php // views/transactions/create.php  (also used as edit.php)
require ROOT . '/views/includes/layout_top.php';

$formTitle = $isEdit ? 'تعديل المعاملة' : 'معاملة جديدة';
$action    = $isEdit
    ? APP_URL . '/transaction/edit?id=' . $transaction['id']
    : APP_URL . '/transaction/create';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $formTitle ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary">← رجوع</a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="padding: 30px">
    <form method="POST" action="<?= $action ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-grid">

            <!-- رقم الإيصال -->
            <div class="form-group">
                <label class="form-label">رقم الإيصال <span class="required">*</span></label>
                <input type="number" name="receipt_id" class="form-control" min="1" required
                       value="<?= htmlspecialchars((string)($transaction['receipt_id'] ?? '')) ?>"
                       placeholder="أدخل رقم الإيصال...">
            </div>

            <!-- المبلغ -->
            <div class="form-group">
                <label class="form-label">المبلغ <span class="required">*</span></label>
                <input type="text" name="amount" class="form-control" required
                       value="<?= htmlspecialchars((string)($transaction['amount'] ?? '')) ?>"
                       placeholder="0.00">
            </div>

            <!-- طريقة الدفع -->
            <div class="form-group">
                <label class="form-label">طريقة الدفع <span class="required">*</span></label>
                <select name="payment_method" id="paymentMethod" class="form-control" required
                        onchange="handlePaymentMethod(this.value)">
                    <option value="">— اختر —</option>
                    <option value="نقداً"       <?= ($transaction['payment_method'] ?? '') === 'نقداً'       ? 'selected' : '' ?>>نقداً</option>
                    <option value="فودافون كاش" <?= ($transaction['payment_method'] ?? '') === 'فودافون كاش' ? 'selected' : '' ?>>فودافون كاش</option>
                    <option value="إنستاباي"    <?= ($transaction['payment_method'] ?? '') === 'إنستاباي'    ? 'selected' : '' ?>>إنستاباي</option>
                </select>
            </div>

            <!-- المرفق (hidden when cash) -->
            <div class="form-group" id="attachmentGroup" style="display:none">
                <label class="form-label">صورة الإيصال <span class="required">*</span></label>
                <input type="file" name="attachment" id="attachmentInput" class="form-control"
                       accept="image/jpeg,image/png,image/webp,application/pdf">
                <p style="font-size:.8rem;color:var(--text-muted);margin-top:.25rem">
                    الصيغ المقبولة: JPG، PNG، WEBP، PDF — الحد الأقصى 5MB
                </p>
            </div>

            <!-- ملاحظات -->
            <div class="form-group form-group--full">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3"
                          placeholder="أي ملاحظات إضافية..."><?= htmlspecialchars($transaction['notes'] ?? '') ?></textarea>
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? '💾 حفظ التعديلات' : '➕ إضافة المعاملة' ?>
            </button>
            <?php if (!empty($transaction['receipt_id'])): ?>
                <a href="<?= APP_URL ?>/receipt/show?id=<?= $transaction['receipt_id'] ?>" class="btn btn-secondary">إلغاء</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary">إلغاء</a>
            <?php endif; ?>
        </div>

    </form>
</div>

<script>
function handlePaymentMethod(val) {
    const group = document.getElementById('attachmentGroup');
    const input = document.getElementById('attachmentInput');
    const isCash = (val === 'نقداً');

    if (isCash || val === '') {
        group.style.display = 'none';
        input.required      = false;
        input.value         = '';
    } else {
        group.style.display = '';
        input.required      = true;
    }
}

// Run on load in case of re-render with a pre-selected value
(function () {
    const pm = document.getElementById('paymentMethod');
    if (pm && pm.value) handlePaymentMethod(pm.value);
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>