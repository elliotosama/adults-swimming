<?php // views/dashboard/branch_manager.php
require ROOT . '/views/includes/layout_top.php';
?>

<style>
<?php include ROOT . '/views/dashboard/shared_dashboard.css'; ?>

/* ── Branch identity badge ──────────────────────────────────── */
.branch-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .45rem .95rem;
    font-size: .95rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 1.4rem;
}
</style>

<!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">📊 لوحة التحكم</h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?> · <?= date('l، d F Y') ?></p>
    </div>
</div>

<div class="branch-badge">🏢 <?= htmlspecialchars($branchName) ?></div>

<!-- ══ SECTION: الإيصالات ══════════════════════════════════════════════ -->
<div class="section-title">🧾 إيصالات الفرع</div>
<div class="stats-grid">

    <div class="stat-card accent-blue">
        <div class="stat-icon">🧾</div>
        <div class="stat-label">إجمالي الإيصالات</div>
        <div class="stat-value"><?= number_format($stats['receipts']['total'] ?? 0) ?></div>
        <div class="stat-sub">منذ بداية النظام</div>
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

    <div class="stat-card accent-yellow">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">معلّقة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['pending'] ?? 0) ?></div>
        <div class="stat-sub">تحتاج متابعة</div>
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

<!-- ══ Receipts + Transactions ════════════════════════════════════════ -->
<div class="dash-row">

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🧾 آخر الإيصالات</h3>
            <a href="<?= APP_URL ?>/receipts?branch_ids[]=<?= $branchId ?>">عرض الكل ←</a>
        </div>
        <?php if (!empty($recentReceipts)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>الكابتن</th>
                    <th>أول جلسة</th>
                    <th>الحالة</th>
                </tr>
            </thead>
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
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['captain_name'] ?? '—') ?></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($r['first_session'] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد إيصالات حديثة</div><?php endif; ?>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <h3>💳 آخر المعاملات</h3>
        </div>
        <?php if (!empty($recentTransactions)): ?>
        <table>
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>طريقة الدفع</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentTransactions as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['client_name'] ?? '—') ?></strong></td>
                    <td style="color:#22c55e;font-weight:800;font-size:1rem"><?= number_format($t['amount'] ?? 0) ?> ج</td>
                    <td style="font-size:.88rem"><?= htmlspecialchars($t['payment_method'] ?? '—') ?></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لا توجد معاملات</div><?php endif; ?>
    </div>

</div>


<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
