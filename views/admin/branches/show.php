<?php
// views/admin/branches/show.php
// Optional: $ajaxPartial — when true, renders without page chrome (SPA modal)

$ajaxPartial = $ajaxPartial ?? false;

if (!$ajaxPartial) {
    require ROOT . '/views/includes/layout_top.php';
}

$days = [
    'Sunday'    => 'الأحد',
    'Monday'    => 'الاثنين',
    'Tuesday'   => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday'  => 'الخميس',
    'Friday'    => 'الجمعة',
    'Saturday'  => 'السبت',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🏢 <?= htmlspecialchars($branch['branch_name']) ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/admin/branch/edit?id=<?= $branch['id'] ?>" class="btn btn-warning">✏️ تعديل</a>
        <?php if ($ajaxPartial): ?>
            <button type="button" class="btn btn-secondary js-modal-close">← رجوع</button>
        <?php else: ?>
            <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary">← رجوع</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card">

    <!-- ── البيانات الأساسية ── -->
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">اسم الفرع</span>
            <span class="detail-value"><?= htmlspecialchars($branch['branch_name']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الدولة</span>
            <span class="detail-value"><?= htmlspecialchars($branch['country'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الحالة</span>
            <span class="detail-value">
                <?php if ($branch['visible']): ?>
                    <span class="badge badge-success">نشط</span>
                <?php else: ?>
                    <span class="badge badge-danger">معطّل</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ الإنشاء</span>
            <span class="detail-value"><?= htmlspecialchars($branch['created_at'] ?? '—') ?></span>
        </div>
    </div>

    <!-- ── أيام العمل ── -->
    <div class="detail-section">
        <p class="detail-section-title">أيام العمل</p>

        <?php foreach ([1, 2, 3] as $shift):
            $raw      = $branch["working_days{$shift}"] ?? '';
            $selected = $raw ? explode(',', $raw) : [];
        ?>
            <div class="shift-row">
                <span class="shift-label">ايام العمل <?= $shift ?></span>
                <div class="day-pills">
                    <?php if (empty($selected)): ?>
                        <span style="color:var(--muted);font-size:.82rem">—</span>
                    <?php else: ?>
                        <?php foreach ($days as $en => $ar): ?>
                            <span class="day-pill <?= in_array($en, $selected, true) ? 'day-pill--active' : 'day-pill--off' ?>">
                                <?= $ar ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── منطقة الخطر ── -->
    <?php if ($branch['visible']): ?>
        <div class="danger-zone">
            <p>⚠️ حذف هذا الفرع سيُخفيه من جميع القوائم.</p>
            <?php if ($ajaxPartial): ?>
                <button type="button"
                        class="btn btn-danger js-delete-branch"
                        data-id="<?= (int) $branch['id'] ?>"
                        data-name="<?= htmlspecialchars($branch['branch_name']) ?>">
                    🗑️ حذف الفرع
                </button>
            <?php else: ?>
                <form method="POST" action="<?= APP_URL ?>/admin/branch/delete?id=<?= $branch['id'] ?>"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذا الفرع؟')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">🗑️ حذف الفرع</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="danger-zone">
            <p>🔓 هذا الفرع معطّل حالياً. يمكنك إعادة تفعيله من خلال التعديل.</p>
            <a href="<?= APP_URL ?>/admin/branch/edit?id=<?= $branch['id'] ?>" class="btn btn-success">✅ إعادة تفعيل</a>
        </div>
    <?php endif; ?>

</div>

<?php if (!$ajaxPartial): ?>
    <?php require ROOT . '/views/includes/layout_bottom.php'; ?>
<?php endif; ?>