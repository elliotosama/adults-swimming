<?php // views/admin/transactions/show.php
require ROOT . '/views/includes/layout_top.php';

$typeMap = [
    'payment'  => ['badge-success', 'دفعة'],
    'refund'   => ['badge-danger',  'استرداد'],
    'discount' => ['badge-warning', 'خصم'],
];
[$tCls, $tLabel] = $typeMap[$transaction['type']] ?? ['badge-secondary', $transaction['type']];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">💳 معاملة #<?= $transaction['id'] ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="<?= APP_URL ?>/transaction/edit?id=<?= $transaction['id'] ?>" class="btn btn-warning">تعديل</a>
        <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary">← رجوع</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text)">تفاصيل المعاملة</h2>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">النوع</span>
            <span class="detail-value"><span class="badge <?= $tCls ?>"><?= $tLabel ?></span></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">طريقة الدفع</span>
            <span class="detail-value"><?= htmlspecialchars($transaction['payment_method'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">المبلغ</span>
            <span class="detail-value" style="font-size:1.15rem;font-weight:700">
                <?= number_format($transaction['amount'], 2) ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الإيصال المرتبط</span>
            <span class="detail-value">
                <?php if ($receipt): ?>
                    <a href="<?= APP_URL ?>/receipt/show?id=<?= $receipt['id'] ?>"
                       style="color:var(--primary);text-decoration:none">
                        #<?= $receipt['id'] ?> — <?= htmlspecialchars($receipt['client_name'] ?? '') ?>
                    </a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">أنشأ بواسطة</span>
            <span class="detail-value"><?= htmlspecialchars($transaction['creator_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ المعامله</span>
            <span class="detail-value"><?= htmlspecialchars($transaction['created_at'] ?? '—') ?></span>
        </div>
        <?php if (!empty($transaction['attachment'])): ?>
        <div class="detail-item">
            <span class="detail-label">المرفق</span>
            <span class="detail-value">
                <a href="<?= htmlspecialchars($transaction['attachment']) ?>" target="_blank" class="btn btn-sm btn-secondary">📎 فتح الملف</a>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($transaction['notes'])): ?>
        <div class="detail-item" style="grid-column: 1 / -1">
            <span class="detail-label">ملاحظات</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($transaction['notes'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);display:flex;gap:.75rem">
        <a href="<?= APP_URL ?>/transaction/edit?id=<?= $transaction['id'] ?>" class="btn btn-warning">تعديل</a>
        <form method="POST" action="<?= APP_URL ?>/transaction/delete?id=<?= $transaction['id'] ?>"
              onsubmit="return confirm('هل أنت متأكد من حذف هذه المعاملة؟')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-danger">حذف</button>
        </form>
    </div>
</div>

<style>
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}
.detail-item { display:flex;flex-direction:column;gap:.25rem; }
.detail-label { font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);font-weight:600; }
.detail-value { font-size:.92rem;color:var(--text); }
</style>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>