<?php
// views/admin/prices/show.php
require ROOT . '/views/includes/layout_top.php';
?>
<style>
    .profile-header {
        display: flex; align-items: center; gap: 1.4rem;
        padding: 1.6rem; border-bottom: 1px solid var(--border);
    }
    .profile-avatar {
        width: 64px; height: 64px; border-radius: 18px; flex-shrink: 0;
        background: linear-gradient(135deg, var(--gold), var(--accent));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; font-weight: 900; color: #fff;
        box-shadow: 0 6px 20px #00b4d840;
    }
    .profile-meta { display:flex; flex-direction:column; gap:.35rem; }
    .profile-meta h2 { font-size: 1.2rem; font-weight: 900; }
    .profile-meta span { font-size: .85rem; color: var(--muted); }
    .price-big {
        font-size: 1.5rem; font-weight: 900;
        color: var(--gold); letter-spacing: .02em;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🏷️ <?= htmlspecialchars($price['description'] ?? 'تفاصيل السعر') ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/admin/price/edit?id=<?= (int)$price['id'] ?>" class="btn btn-warning">✏️ تعديل</a>
        <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary">← رجوع</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card">

    <!-- ── رأس البطاقة ── -->
    <div class="profile-header">
        <div class="profile-avatar">💰</div>
        <div class="profile-meta">
            <h2><?= htmlspecialchars($price['description'] ?? '—') ?></h2>
            <span class="price-big">
                <?= $price['price'] !== null ? number_format((float)$price['price'], 2) : '—' ?>
            </span>
            <span>
                <?php if ($price['visible']): ?>
                    <span class="badge badge-success">نشط</span>
                <?php else: ?>
                    <span class="badge badge-danger">معطّل</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- ── البيانات التفصيلية ── -->
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">الدولة</span>
            <span class="detail-value"><?= htmlspecialchars($price['country'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">عدد الجلسات</span>
            <span class="detail-value">
                <?= $price['number_of_sessions'] ? (int)$price['number_of_sessions'] . ' جلسة' : '—' ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ الإضافة</span>
            <span class="detail-value"><?= htmlspecialchars($price['created_at'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">آخر تحديث</span>
            <span class="detail-value"><?= htmlspecialchars($price['updated_at'] ?? '—') ?></span>
        </div>
    </div>

    <!-- ── منطقة الخطر ── -->
    <?php if ($price['visible']): ?>
        <div class="danger-zone">
            <p>⚠️ تعطيل هذا السعر سيُخفيه عن العملاء.</p>
            <form method="POST" action="<?= APP_URL ?>/admin/price/delete?id=<?= (int)$price['id'] ?>"
                  onsubmit="return confirm('هل أنت متأكد من تعطيل هذا السعر؟')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="btn btn-danger">🗑️ تعطيل السعر</button>
            </form>
        </div>
    <?php else: ?>
        <div class="danger-zone">
            <p>🔓 هذا السعر معطّل. يمكنك إعادة تفعيله من خلال التعديل.</p>
            <a href="<?= APP_URL ?>/admin/price/edit?id=<?= (int)$price['id'] ?>" class="btn btn-success">✅ إعادة تفعيل</a>
        </div>
    <?php endif; ?>

</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>