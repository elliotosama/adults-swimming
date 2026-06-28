<?php // views/dashboard/customer_service.php
require ROOT . '/views/includes/layout_top.php';
?>

<style>
<?php include ROOT . '/views/dashboard/shared_dashboard.css'; ?>

/* ── Week activity bar chart ────────────────────────────────── */
.week-chart {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 90px;
    padding: 1rem 1.4rem;
    border-top: 1px solid var(--border);
}
.week-bar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    flex: 1;
}
.week-bar {
    width: 100%;
    border-radius: 5px 5px 0 0;
    background: var(--primary, #00b4d8);
    opacity: .75;
    min-height: 4px;
    transition: opacity .2s, transform .2s;
}
.week-bar:hover { opacity: 1; transform: scaleY(1.04); transform-origin: bottom; }
.week-label { font-size: .72rem; color: var(--muted); }
.week-count { font-size: .75rem; font-weight: 700; color: var(--text); }

/* ── Tabbed card ────────────────────────────────────────────── */
.tab-bar {
    display: flex;
    border-bottom: 1px solid var(--border);
    padding: 0 1.4rem;
    gap: .25rem;
}
.tab-btn {
    padding: .65rem 1rem;
    font-size: .9rem;
    font-weight: 700;
    border: none;
    background: none;
    color: var(--muted);
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    cursor: pointer;
    transition: color .15s, border-color .15s;
    white-space: nowrap;
}
.tab-btn.active { color: var(--primary, #00b4d8); border-bottom-color: var(--primary, #00b4d8); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
</style>

<!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">📋 لوحتي</h1>
        <p class="breadcrumb">مرحباً، <?= htmlspecialchars($_SESSION['username'] ?? '') ?> · <?= date('l، d F Y') ?></p>
    </div>
    <div style="display:flex;gap:.7rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/receipt/create" class="btn btn-primary">+ إيصال جديد</a>
    </div>
</div>

<!-- ══ SECTION: إحصائياتي ══════════════════════════════════════════════ -->
<div class="section-title">📊 إحصائياتي</div>
<div class="stats-grid">

    <div class="stat-card accent-blue">
        <div class="stat-icon">🧾</div>
        <div class="stat-label">إيصالات أنشأتها</div>
        <div class="stat-value"><?= number_format($stats['receipts']['total'] ?? 0) ?></div>
        <div class="stat-sub">إجمالي منذ البداية</div>
    </div>

    <div class="stat-card accent-green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['completed'] ?? 0) ?></div>
        <?php $pct = ($stats['receipts']['total'] ?? 0) ? round($stats['receipts']['completed'] / $stats['receipts']['total'] * 100) : 0; ?>
        <div class="stat-sub"><?= $pct ?>% من إيصالاتي</div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
    </div>

    <div class="stat-card accent-yellow">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">معلّقة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['pending'] ?? 0) ?></div>
        <div class="stat-sub">تحتاج متابعة</div>
    </div>

    <div class="stat-card accent-red">
        <div class="stat-icon">❌</div>
        <div class="stat-label">غير مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['not_completed'] ?? 0) ?></div>
        <?php $pct2 = ($stats['receipts']['total'] ?? 0) ? round($stats['receipts']['not_completed'] / $stats['receipts']['total'] * 100) : 0; ?>
        <div class="stat-sub"><?= $pct2 ?>%</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-icon">📅</div>
        <div class="stat-label">أنشأت اليوم</div>
        <div class="stat-value"><?= number_format($stats['receipts']['today'] ?? 0) ?></div>
        <div class="stat-sub">إيصال اليوم</div>
    </div>

    <div class="stat-card accent-purple">
        <div class="stat-icon">🗓️</div>
        <div class="stat-label">هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['receipts']['this_month'] ?? 0) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
    </div>

    <div class="stat-card accent-orange">
        <div class="stat-icon">✏️</div>
        <div class="stat-label">إيصالات عدّلتها</div>
        <div class="stat-value"><?= number_format($stats['updated_only'] ?? 0) ?></div>
        <div class="stat-sub">لم أنشئها أنا</div>
    </div>

    <div class="stat-card accent-green">
        <div class="stat-icon">💰</div>
        <div class="stat-label">مدفوعات إيصالاتي</div>
        <div class="stat-value"><?= number_format($stats['transactions']['total_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه إجمالي</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-icon">📆</div>
        <div class="stat-label">مدفوعات اليوم</div>
        <div class="stat-value"><?= number_format($stats['transactions']['today_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه اليوم</div>
    </div>

    <div class="stat-card accent-blue">
        <div class="stat-icon">🔢</div>
        <div class="stat-label">معاملات هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['transactions']['count'] ?? 0) ?></div>
        <div class="stat-sub">معاملة مسجّلة</div>
    </div>

</div>

<!-- ══ Week activity chart ════════════════════════════════════════════ -->
<?php if (!empty($weekActivity)): ?>
<div class="section-title">📈 نشاطي — آخر 7 أيام</div>
<div class="dash-card" style="margin-bottom:1.8rem">
    <?php
    $dayMap = [];
    foreach ($weekActivity as $w) $dayMap[$w['day']] = (int)$w['cnt'];
    $maxCnt = max(array_values($dayMap) ?: [1]);
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $days[] = ['date' => $d, 'cnt' => $dayMap[$d] ?? 0];
    }
    ?>
    <div class="week-chart">
        <?php foreach ($days as $d):
            $barPct = $maxCnt ? round($d['cnt'] / $maxCnt * 100) : 0;
        ?>
        <div class="week-bar-wrap">
            <div class="week-count"><?= $d['cnt'] ?: '' ?></div>
            <div class="week-bar"
                 style="height:<?= max(4, $barPct * 0.62) ?>px"
                 title="<?= $d['date'] ?>: <?= $d['cnt'] ?> إيصال"></div>
            <div class="week-label"><?= date('D', strtotime($d['date'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══ My receipts + My edits (tabbed) ════════════════════════════════ -->
<div class="section-title">🗂️ إيصالاتي</div>
<div class="dash-card" style="margin-bottom:1.8rem">
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('created', this)">🧾 إيصالات أنشأتها (<?= count($myReceipts) ?>)</button>
        <button class="tab-btn" onclick="switchTab('edited', this)">✏️ إيصالات عدّلتها (<?= count($myEdits) ?>)</button>
    </div>

    <!-- Tab: Created -->
    <div id="tab-created" class="tab-pane active">
        <?php if (!empty($myReceipts)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>الفرع</th>
                    <th>الكابتن</th>
                    <th>أول جلسة</th>
                    <th>آخر جلسة</th>
                    <th>إجمالي المدفوع</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $statusMap = [
                'completed'     => ['badge-success', 'مكتمل'],
                'not_completed' => ['badge-danger',  'غير مكتمل'],
                'pending'       => ['badge-warning', 'معلّق'],
            ];
            foreach ($myReceipts as $r):
                [$cls, $lbl] = $statusMap[$r['receipt_status']] ?? ['badge-secondary', $r['receipt_status']];
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:.85rem"><?= $r['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($r['client_name'] ?? '—') ?></strong>
                        <?php if (!empty($r['phone'])): ?>
                            <br><small style="color:var(--muted)"><?= htmlspecialchars($r['phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></td>
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['captain_name'] ?? '—') ?></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($r['first_session'] ?? '—') ?></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($r['last_session'] ?? '—') ?></td>
                    <td style="color:#22c55e;font-weight:800;font-size:1rem"><?= number_format($r['total_paid'] ?? 0) ?> ج</td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/receipt/show?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لم تُنشئ أي إيصالات بعد</div><?php endif; ?>
    </div>

    <!-- Tab: Edited -->
    <div id="tab-edited" class="tab-pane">
        <?php if (!empty($myEdits)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>الفرع</th>
                    <th>الحالة</th>
                    <th>آخر تعديل مني</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myEdits as $r):
                [$cls, $lbl] = $statusMap[$r['receipt_status']] ?? ['badge-secondary', $r['receipt_status']];
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:.85rem"><?= $r['id'] ?></td>
                    <td><strong><?= htmlspecialchars($r['client_name'] ?? '—') ?></strong></td>
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($r['last_edit'] ?? '—') ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/receipt/show?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="dash-empty">لم تُعدّل أي إيصالات لم تُنشئها</div><?php endif; ?>
    </div>
</div>

<!-- ══ My detailed audit trail ════════════════════════════════════════ -->
<?php if (!empty($myAuditLog)): ?>
<div class="section-title">🕐 سجل تعديلاتي</div>
<div class="dash-card" style="margin-bottom:2rem">
    <ul class="activity-list">
        <?php foreach ($myAuditLog as $log): ?>
        <li>
            <div class="activity-icon">✏️</div>
            <div class="activity-text">
                <strong>إيصال #<?= $log['receipt_id'] ?></strong>
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

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>