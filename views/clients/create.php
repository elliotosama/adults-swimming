<?php // views/clients/create.php
require ROOT . '/views/includes/layout_top.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👤 عميل جديد</h1>
        <p class="breadcrumb">لوحة التحكم · العملاء · جديد</p>
    </div>
    <a href="<?= APP_URL ?>/clients" class="btn btn-secondary">← العودة</a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= APP_URL ?>/client/create">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <?php require __DIR__ . '/_form.php'; ?>
    </form>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>