<?php
// views/admin/users/show.php
require ROOT . '/views/includes/layout_top.php';

$roleLabels = [
    'admin'            => ['label' => 'مدير النظام',  'color' => 'role-admin'],
    'branch_manager'   => ['label' => 'مدير فرع',     'color' => 'role-manager'],
    'area_manager'     => ['label' => 'مدير منطقة',   'color' => 'role-area'],
    'customer_service' => ['label' => 'خدمة العملاء', 'color' => 'role-cs'],
];
$role = $roleLabels[$user['role']] ?? ['label' => $user['role'], 'color' => 'badge'];
$initials = mb_strtoupper(mb_substr($user['username'], 0, 2));
?>
<style>
    .role-admin   { background:#7c3aed20; color:#a78bfa; border:1px solid #7c3aed40; }
    .role-manager { background:#00b4d820; color:var(--accent); border:1px solid #00b4d840; }
    .role-area    { background:#f4a62320; color:var(--gold); border:1px solid #f4a62340; }
    .role-cs      { background:#34c78920; color:var(--success); border:1px solid #34c78940; }

    .profile-header {
        display: flex; align-items: center; gap: 1.4rem;
        padding: 1.6rem; border-bottom: 1px solid var(--border);
    }
    .profile-avatar {
        width: 64px; height: 64px; border-radius: 18px; flex-shrink: 0;
        background: linear-gradient(135deg, var(--accent2), var(--accent));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 900; color: #fff;
        box-shadow: 0 6px 20px #00b4d840;
    }
    .profile-meta { display:flex; flex-direction:column; gap:.3rem; }
    .profile-meta h2 { font-size: 1.2rem; font-weight: 900; }
    .profile-meta span { font-size: .85rem; color: var(--muted); }

    .branch-tags { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; }
    .branch-tag {
        padding:.3rem .8rem; border-radius:8px; font-size:.8rem; font-weight:600;
        background:#00b4d815; color:var(--accent); border:1px solid #00b4d830;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">👤 <?= htmlspecialchars($user['username']) ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/admin/user/edit?id=<?= $user['id'] ?>" class="btn btn-warning">✏️ تعديل</a>
        <a href="<?= APP_URL ?>/admin/users" class="btn btn-secondary">← رجوع</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card">

    <!-- ── رأس الملف الشخصي ── -->
    <div class="profile-header">
        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="profile-meta">
            <h2><?= htmlspecialchars($user['username']) ?></h2>
            <span><?= htmlspecialchars($user['email'] ?? '—') ?></span>
            <span>
                <span class="badge <?= $role['color'] ?>"><?= $role['label'] ?></span>
            </span>
        </div>
    </div>

    <!-- ── البيانات التفصيلية ── -->
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">رقم الهاتف</span>
            <span class="detail-value"><?= htmlspecialchars($user['phone'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">الحالة</span>
            <span class="detail-value">
                <?php if ($user['is_active'] && $user['visible']): ?>
                    <span class="badge badge-success">نشط</span>
                <?php else: ?>
                    <span class="badge badge-danger">معطّل</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">تاريخ الإنشاء</span>
            <span class="detail-value"><?= htmlspecialchars($user['created_at'] ?? '—') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">آخر تسجيل دخول</span>
            <span class="detail-value"><?= htmlspecialchars($user['last_login'] ?? '—') ?></span>
        </div>
    </div>

    <!-- ── الفروع المرتبطة ── -->
    <div class="detail-section">
        <p class="detail-section-title">الفروع المرتبطة</p>
        <?php if (empty($branches)): ?>
            <p style="color:var(--muted);font-size:.85rem">لم يتم تعيين أي فرع لهذا المستخدم.</p>
        <?php else: ?>
            <div class="branch-tags">
                <?php foreach ($branches as $b): ?>
                    <span class="branch-tag">
                        🏢 <?= htmlspecialchars($b['branch_name']) ?>
                        <?php if (!empty($b['country'])): ?>
                            <span style="opacity:.6">— <?= htmlspecialchars($b['country']) ?></span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── منطقة الخطر ── -->
    <?php if ($user['id'] !== (int)(auth_user()['id'] ?? 0)): ?>
        <?php if ($user['is_active'] || $user['visible']): ?>
            <div class="danger-zone">
                <p>⚠️ تعطيل هذا المستخدم سيمنعه من تسجيل الدخول.</p>
                <form method="POST" action="<?= APP_URL ?>/admin/user/delete?id=<?= $user['id'] ?>"
                      onsubmit="return confirm('هل أنت متأكد من تعطيل هذا المستخدم؟')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">🗑️ تعطيل المستخدم</button>
                </form>
            </div>
        <?php else: ?>
            <div class="danger-zone">
                <p>🔓 هذا المستخدم معطّل. يمكنك إعادة تفعيله من خلال التعديل.</p>
                <a href="<?= APP_URL ?>/admin/user/edit?id=<?= $user['id'] ?>" class="btn btn-success">✅ إعادة تفعيل</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>