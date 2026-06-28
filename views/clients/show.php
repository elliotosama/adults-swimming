<?php // views/clients/show.php
require ROOT . '/views/includes/layout_top.php';

$genderLabel = match($client['gender'] ?? '') {
    'male'   => 'ذكر',
    'female' => 'أنثى',
    default  => '—',
};
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👤 <?= htmlspecialchars($client['client_name']) ?></h1>
        <p class="breadcrumb">لوحة التحكم · العملاء · عرض</p>
    </div>
    <div style="display:flex;gap:.6rem">
        <a href="<?= APP_URL ?>/client/edit?id=<?= $client['id'] ?>" class="btn btn-warning">تعديل</a>
        <a href="<?= APP_URL ?>/clients" class="btn btn-secondary">← العودة</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card">
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">الاسم</span>
            <span class="detail-value"><?= htmlspecialchars($client['client_name']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الهاتف</span>
            <span class="detail-value"><?= htmlspecialchars($client['phone']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">البريد الإلكتروني</span>
            <span class="detail-value"><?= htmlspecialchars($client['email'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">العمر</span>
            <span class="detail-value"><?= $client['age'] ? $client['age'] . ' سنة' : '—' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الجنس</span>
            <span class="detail-value"><?= $genderLabel ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">المنشئ</span>
            <span class="detail-value"><?= htmlspecialchars($client['creator_name'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ الإضافة</span>
            <span class="detail-value"><?= htmlspecialchars($client['created_at'] ?? '—') ?></span>
        </div>
    </div>

    <div class="danger-zone">
        <p>حذف العميل سيزيل جميع بياناته نهائياً.</p>
        <form method="POST" action="<?= APP_URL ?>/client/delete?id=<?= $client['id'] ?>"
              onsubmit="return confirm('هل أنت متأكد من حذف هذا العميل نهائياً؟')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-danger">🗑️ حذف العميل</button>
        </form>
    </div>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>