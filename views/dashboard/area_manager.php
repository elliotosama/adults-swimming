<?php // views/dashboard/area_manager.php
require ROOT . '/views/includes/layout_top.php';
?>

<style>
<?php include ROOT . '/views/dashboard/shared_dashboard.css'; ?>

/* ── Branch chips strip ─────────────────────────────────────── */
.branches-strip {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    margin-bottom: 1.5rem;
}
.branch-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .4rem .95rem;
    font-size: .9rem;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
    transition: border-color .15s, color .15s, background .15s;
}
.branch-chip:hover {
    border-color: var(--primary, #00b4d8);
    color: var(--primary, #00b4d8);
    background: #00b4d80a;
}

/* ── Wide full-width card ───────────────────────────────────── */
.wide-card { margin-bottom: 1.2rem; }
</style>

<!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">📊 لوحة التحكم — مدير المنطقة</h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?> · <?= date('l، d F Y') ?></p>
    </div>
</div>

<?php if (empty($branches)): ?>
    <div class="dash-card"><div class="dash-empty">لا توجد فروع مرتبطة بحسابك. تواصل مع المسؤول.</div></div>
    <?php require ROOT . '/views/includes/layout_bottom.php'; return; ?>
<?php endif; ?>

<!-- Branch quick-links -->
<div class="branches-strip">
    <?php foreach ($branches as $b): ?>
        <a class="branch-chip" href="<?= APP_URL ?>/receipts?branch_ids[]=<?= $b['id'] ?>">
            🏢 <?= htmlspecialchars($b['branch_name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ══ SECTION: الإيصالات ══════════════════════════════════════════════ -->
<div class="section-title">🧾 إجمالي الإيصالات — كل الفروع</div>
<div class="stats-grid">

    <div class="stat-card accent-blue">
        <div class="stat-icon">🧾</div>
        <div class="stat-label">إجمالي الإيصالات</div>
        <div class="stat-value"><?= number_format($stats['receipts']['total'] ?? 0) ?></div>
        <div class="stat-sub">عبر <?= count($branches) ?> فرع</div>
    </div>

    <div class="stat-card accent-green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['completed'] ?? 0) ?></div>
        <?php $pct = ($stats['receipts']['total'] ?? 0) ? round($stats['receipts']['completed'] / $stats['receipts']['total'] * 100) : 0; ?>
        <div class="stat-sub"><?= $pct ?>% من الإجمالي</div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
    </div>

    <div class="stat-card accent-red">
        <div class="stat-icon">❌</div>
        <div class="stat-label">غير مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['not_completed'] ?? 0) ?></div>
        <?php $pct2 = ($stats['receipts']['total'] ?? 0) ? round($stats['receipts']['not_completed'] / $stats['receipts']['total'] * 100) : 0; ?>
        <div class="stat-sub"><?= $pct2 ?>% من الإجمالي</div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct2 ?>%;background:#ef4444"></div></div>
    </div>


    <div class="stat-card accent-teal">
        <div class="stat-icon">📅</div>
        <div class="stat-label">إيصالات اليوم</div>
        <div class="stat-value"><?= number_format($stats['receipts']['today'] ?? 0) ?></div>
        <div class="stat-sub">جديد اليوم</div>
    </div>

    <div class="stat-card accent-purple">
        <div class="stat-icon">🗓️</div>
        <div class="stat-label">إيصالات هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['receipts']['this_month'] ?? 0) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
    </div>

</div>
<!-- ══ Captains + Recent receipts ════════════════════════════════════ -->
<div class="dash-row">

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🏊 أداء الكابتنات</h3>
        </div>
        <?php if (!empty($captainStats)): ?>
        <table>
            <thead><tr><th>الكابتن</th><th>الفرع</th><th>الإيصالات</th><th>مكتمل %</th></tr></thead>
            <tbody>
            <?php foreach ($captainStats as $cap):
                $cPct = $cap['receipt_count'] ? round($cap['completed_count'] / $cap['receipt_count'] * 100) : 0;
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cap['captain_name']) ?></strong></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($cap['branch_name'] ?? '—') ?></td>
                    <td><?= number_format($cap['receipt_count']) ?></td>
                    <td><span class="badge badge-success"><?= $cPct ?>%</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد بيانات</div><?php endif; ?>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🧾 آخر الإيصالات</h3>
            <a href="<?= APP_URL ?>/receipts">عرض الكل ←</a>
        </div>
        <?php if (!empty($recentReceipts)): ?>
        <table>
            <thead><tr><th>#</th><th>العميل</th><th>الفرع</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php
            $statusMap = [
                'completed'     => ['badge-success', 'مكتمل'],
                'not_completed' => ['badge-danger',  'غير مكتمل'],
                'pending'       => ['badge-warning', 'معلّق'],
            ];
            foreach ($recentReceipts as $r):
                [$cls, $lbl] = $statusMap[$r['receipt_status']] ?? ['badge-secondary', $r['receipt_status']];
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:.85rem"><?= $r['id'] ?></td>
                    <td><strong><?= htmlspecialchars($r['client_name'] ?? '—') ?></strong></td>
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد إيصالات حديثة</div><?php endif; ?>
    </div>

</div>

<!-- ══ Top clients + Employees ════════════════════════════════════════ -->
<div class="dash-row">

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🥇 أعلى العملاء دفعاً</h3>
        </div>
        <?php if (!empty($topClients)): ?>
        <table>
            <thead><tr><th>#</th><th>العميل</th><th>إجمالي المدفوع</th><th>الإيصالات</th></tr></thead>
            <tbody>
            <?php foreach ($topClients as $i => $cl): ?>
                <tr>
                    <td style="color:var(--muted);font-size:.8rem;font-weight:700"><?= $i+1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($cl['client_name']) ?></strong>
                        <br><small style="color:var(--muted)"><?= htmlspecialchars($cl['phone'] ?? '') ?></small>
                    </td>
                    <td style="color:#22c55e;font-weight:800;font-size:1rem"><?= number_format($cl['total_paid'] ?? 0) ?> ج</td>
                    <td><?= number_format($cl['receipt_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد بيانات</div><?php endif; ?>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>👨‍💼 نشاط الموظفين</h3>
        </div>
        <?php if (!empty($userStats)): ?>
        <table>
            <thead><tr><th>الموظف</th><th>الإيصالات</th><th>هذا الشهر</th></tr></thead>
            <tbody>
            <?php foreach ($userStats as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= number_format($u['total_receipts']) ?></td>
                    <td>
                        <span class="badge <?= $u['month_receipts'] > 0 ? 'badge-success' : 'badge-secondary' ?>">
                            <?= number_format($u['month_receipts']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد بيانات</div><?php endif; ?>
    </div>

</div>
<!-- ══ Recent audit log ════════════════════════════════════════════════ -->
<?php if (!empty($recentAuditLog)): ?>
<div class="section-title">🕐 آخر التعديلات</div>
<div class="dash-card wide-card">
    <ul class="activity-list">
        <?php foreach ($recentAuditLog as $log): ?>
        <li>
            <div class="activity-icon">✏️</div>
            <div class="activity-text">
                <strong><?= htmlspecialchars($log['changed_by_name'] ?? 'مستخدم') ?></strong>
                عدّل إيصال #<?= $log['receipt_id'] ?>
                — <?= htmlspecialchars($log['field_name'] ?? '') ?>
                <?php if (!empty($log['old_value']) && !empty($log['new_value'])): ?>
                    من <em><?= htmlspecialchars($log['old_value']) ?></em>
                    إلى <em><?= htmlspecialchars($log['new_value']) ?></em>
                <?php endif; ?>
                <div class="activity-time"><?= htmlspecialchars($log['changed_at'] ?? '') ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>