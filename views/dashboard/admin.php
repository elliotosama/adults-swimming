<?php // views/dashboard/index.php
require ROOT . '/views/includes/layout_top.php';

function adminActivityLabel(string $action): string {
    $labels = [
        'updated_branch' => 'عدّل فرع',
        'created_captain' => 'أضاف كابتن',
        'captain_added_to_branch' => 'أضاف كابتن إلى فرعه',
        'captain_removed_from_branch' => 'أزال كابتن من فرعه',
        'updated_captain_branch_assignment' => 'عدّل فروع كابتن',
    ];

    return $labels[$action] ?? $action;
}
?>

<style>
/* ── Base font size bump ─────────────────────────────────────── */
body { font-size: 16px; }

/* ── Stats Grid ─────────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.1rem;
    margin-bottom: 1.8rem;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.4rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: .45rem;
    position: relative;
    overflow: hidden;
    transition: transform .2s cubic-bezier(.22,1,.36,1), box-shadow .2s;
    cursor: default;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,180,216,.13);
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 5px; height: 100%;
    border-radius: 0 16px 16px 0;
    background: var(--accent-color, var(--primary));
}
/* subtle glow behind the accent strip */
.stat-card::after {
    content: '';
    position: absolute;
    top: 10%; right: 0;
    width: 60px; height: 80%;
    background: var(--accent-color, var(--primary));
    opacity: .05;
    filter: blur(18px);
    pointer-events: none;
}
.stat-icon {
    font-size: 1.9rem;
    line-height: 1;
    margin-bottom: .25rem;
}
.stat-label {
    font-size: .88rem;
    color: var(--muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.stat-value {
    font-size: 2.4rem;
    font-weight: 900;
    color: var(--text);
    line-height: 1.1;
    letter-spacing: -.02em;
}
.stat-sub {
    font-size: .85rem;
    color: var(--muted);
    margin-top: .05rem;
}
.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .78rem;
    padding: .2rem .55rem;
    border-radius: 999px;
    font-weight: 700;
    margin-top: .2rem;
    width: fit-content;
}
.stat-badge.up      { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.stat-badge.down    { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.stat-badge.neutral { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

/* ── Section title ──────────────────────────────────────────── */
.section-title {
    font-size: .92rem;
    font-weight: 800;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin: 2rem 0 .9rem;
    display: flex;
    align-items: center;
    gap: .6rem;
}
.section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Two-column layout ──────────────────────────────────────── */
.dash-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.1rem;
    margin-bottom: 1.1rem;
}
@media (max-width: 900px) {
    .dash-row { grid-template-columns: 1fr; }
}

/* ── Cards ─────────────────────────────────────────────────── */
.dash-card {
    z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow .2s;
}
.dash-card:hover {
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
}
.dash-card-header {
    padding: 1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, var(--surface), var(--bg));
}
.dash-card-header h3 {
    font-size: 1rem;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    color: var(--text);
}
.dash-card-header a {
    font-size: .85rem;
    color: var(--accent, #00b4d8);
    text-decoration: none;
    font-weight: 600;
    padding: .3rem .7rem;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s;
}
.dash-card-header a:hover {
    background: #00b4d812;
    border-color: #00b4d830;
}

/* ── Tables ─────────────────────────────────────────────────── */
.dash-card table {
    width: 100%;
    border-collapse: collapse;
    font-size: .92rem;
}
.dash-card table th {
    padding: .65rem 1.2rem;
    text-align: center;
    font-size: .78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted);
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.dash-card table td {
    padding: .75rem 1.2rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
    text-align: center;
    font-size: .92rem;
}
.dash-card table tr:last-child td { border-bottom: none; }
.dash-card table tbody tr {
    transition: background .12s;
}
.dash-card table tbody tr:hover td {
    background: #00b4d806;
}

/* ── Progress bar ───────────────────────────────────────────── */
.progress-bar-wrap {
    height: 6px;
    background: var(--border);
    border-radius: 999px;
    overflow: hidden;
    margin-top: .35rem;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--primary, #00b4d8);
    transition: width .6s cubic-bezier(.22,1,.36,1);
}

/* ── Status dots ────────────────────────────────────────────── */
.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-left: .4rem; }
.dot.green  { background: #22c55e; }
.dot.red    { background: #ef4444; }
.dot.yellow { background: #f59e0b; }

/* ── Badges ─────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: .28rem .75rem;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: 700;
}
.badge-success { background: #34c78920; color: #34c789; border: 1px solid #34c78940; }
.badge-danger  { background: #e05c5c20; color: #e05c5c; border: 1px solid #e05c5c40; }
.badge-warning { background: #f4a62320; color: #f4a623; border: 1px solid #f4a62340; }
.badge-secondary { background: #1a2e42; color: #5a7a96; border: 1px solid #1a2e42; }

/* ── Activity list ──────────────────────────────────────────── */
.activity-list { list-style: none; margin: 0; padding: 0; }
.activity-list li {
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    padding: .9rem 1.4rem;
    border-bottom: 1px solid var(--border);
    font-size: .9rem;
    transition: background .12s;
}
.activity-list li:hover { background: #00b4d806; }
.activity-list li:last-child { border-bottom: none; }
.activity-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    background: var(--bg);
    border: 1px solid var(--border);
}
.activity-text { flex: 1; line-height: 1.5; }
.activity-text strong { display: block; font-weight: 700; font-size: .93rem; }
.activity-time { font-size: .8rem; color: var(--muted); margin-top: .15rem; }

/* ── Accent colors per card ─────────────────────────────────── */
.accent-blue   { --accent-color: #3b82f6; }
.accent-green  { --accent-color: #22c55e; }
.accent-purple { --accent-color: #a855f7; }
.accent-orange { --accent-color: #f97316; }
.accent-pink   { --accent-color: #ec4899; }
.accent-teal   { --accent-color: #14b8a6; }
.accent-yellow { --accent-color: #eab308; }
.accent-red    { --accent-color: #ef4444; }

/* ── Empty state ────────────────────────────────────────────── */
.dash-empty {
    padding: 2.5rem;
    text-align: center;
    color: var(--muted);
    font-size: .95rem;
}
.dash-empty::before {
    content: '📭';
    display: block;
    font-size: 2rem;
    margin-bottom: .5rem;
}

/* ── Page header enhancements ───────────────────────────────── */
.page-title { font-size: 1.9rem !important; font-weight: 900; }
.breadcrumb { font-size: .95rem !important; margin-top: .3rem; }

/* ── Stagger animation on load ──────────────────────────────── */
.stat-card { opacity: 0; animation: fadeUp .45s cubic-bezier(.22,1,.36,1) forwards; }
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.stat-card:nth-child(1) { animation-delay: .04s }
.stat-card:nth-child(2) { animation-delay: .09s }
.stat-card:nth-child(3) { animation-delay: .13s }
.stat-card:nth-child(4) { animation-delay: .17s }
.stat-card:nth-child(5) { animation-delay: .21s }
.stat-card:nth-child(6) { animation-delay: .25s }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 640px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: .75rem; }
    .stat-value { font-size: 1.8rem; }
    .stat-label { font-size: .78rem; }
    .dash-card table th,
    .dash-card table td { padding: .6rem .8rem; font-size: .85rem; }
    .page-title { font-size: 1.5rem !important; }
}
@media (max-width: 400px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">📊 لوحة التحكم</h1>
        <p class="breadcrumb">مرحباً، <?= htmlspecialchars($_SESSION['username'] ?? 'المشرف') ?> &nbsp;·&nbsp; <?= date('l، d F Y') ?></p>
    </div>
</div>

<!-- ══ SECTION: الإيصالات ══════════════════════════════════════════════ -->
<div class="section-title">🧾 الإيصالات</div>
<div class="stats-grid">

    <div class="stat-card accent-blue">
        <div class="stat-icon">🧾</div>
        <div class="stat-label">إجمالي الإيصالات</div>
        <div class="stat-value"><?= number_format($stats['receipts']['total'] ?? 0) ?></div>
        <div class="stat-sub">منذ بداية النظام</div>
    </div>
    <div class="stat-card accent-teal">
        <div class="stat-icon">🆕</div>
        <div class="stat-label">إيصالات جديدة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['new'] ?? 0) ?></div>
        <div class="stat-sub">عميل جديد</div>
    </div>

    <div class="stat-card accent-orange">
        <div class="stat-icon">🔁</div>
        <div class="stat-label">تجديد حالي</div>
        <div class="stat-value"><?= number_format($stats['receipts']['current_renewal'] ?? 0) ?></div>
        <div class="stat-sub">current_renewal</div>
    </div>

    <div class="stat-card accent-pink">
        <div class="stat-icon">⏪</div>
        <div class="stat-label">تجديد سابق</div>
        <div class="stat-value"><?= number_format($stats['receipts']['previous_renewal'] ?? 0) ?></div>
        <div class="stat-sub">previous_renewal</div>
    </div>

    <div class="stat-card accent-green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['completed'] ?? 0) ?></div>
        <div class="stat-sub">
            <?php $pct = $stats['receipts']['total'] ? round($stats['receipts']['completed'] / $stats['receipts']['total'] * 100) : 0; ?>
            <?= $pct ?>% من الإجمالي
        </div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
    </div>

    <div class="stat-card accent-red">
        <div class="stat-icon">❌</div>
        <div class="stat-label">غير مكتملة</div>
        <div class="stat-value"><?= number_format($stats['receipts']['not_completed'] ?? 0) ?></div>
        <?php $pct2 = $stats['receipts']['total'] ? round($stats['receipts']['not_completed'] / $stats['receipts']['total'] * 100) : 0; ?>
        <div class="stat-sub"><?= $pct2 ?>% من الإجمالي</div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct2 ?>%;background:#ef4444"></div></div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-icon">📅</div>
        <div class="stat-label">إيصالات اليوم</div>
        <div class="stat-value"><?= number_format($stats['receipts']['today'] ?? 0) ?></div>
        <div class="stat-sub">إيصال جديد اليوم</div>
    </div>

    <div class="stat-card accent-purple">
        <div class="stat-icon">🗓️</div>
        <div class="stat-label">إيصالات هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['receipts']['this_month'] ?? 0) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
    </div>

</div>

<!-- ══ SECTION: المالية ════════════════════════════════════════════════ -->
<div class="section-title">💳 المعاملات المالية</div>
<div class="stats-grid">

    <div class="stat-card accent-green">
        <div class="stat-icon">💰</div>
        <div class="stat-label">إجمالي المدفوعات</div>
        <div class="stat-value"><?= number_format($stats['transactions']['total_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه مصري</div>
    </div>

    <div class="stat-card accent-blue">
        <div class="stat-icon">🔢</div>
        <div class="stat-label">عدد المعاملات</div>
        <div class="stat-value"><?= number_format($stats['transactions']['count'] ?? 0) ?></div>
        <div class="stat-sub">معاملة مسجّلة</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-icon">📆</div>
        <div class="stat-label">مدفوعات اليوم</div>
        <div class="stat-value"><?= number_format($stats['transactions']['today_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه اليوم</div>
    </div>

    <div class="stat-card accent-purple">
        <div class="stat-icon">📊</div>
        <div class="stat-label">مدفوعات هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['transactions']['month_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه — <?= date('F Y') ?></div>
    </div>

    <div class="stat-card accent-orange">
        <div class="stat-icon">📈</div>
        <div class="stat-label">متوسط قيمة المعاملة</div>
        <div class="stat-value"><?= number_format($stats['transactions']['avg_amount'] ?? 0) ?></div>
        <div class="stat-sub">جنيه لكل معاملة</div>
    </div>

</div>

<!-- ══ SECTION: العملاء والموظفون ══════════════════════════════════════ -->
<div class="section-title">👥 العملاء والموظفون</div>
<div class="stats-grid">

    <div class="stat-card accent-blue">
        <div class="stat-icon">👤</div>
        <div class="stat-label">إجمالي العملاء</div>
        <div class="stat-value"><?= number_format($stats['clients']['total'] ?? 0) ?></div>
        <div class="stat-sub">عميل مسجّل</div>
    </div>

    <div class="stat-card accent-green">
        <div class="stat-icon">🆕</div>
        <div class="stat-label">عملاء هذا الشهر</div>
        <div class="stat-value"><?= number_format($stats['clients']['this_month'] ?? 0) ?></div>
        <div class="stat-sub">عميل جديد</div>
    </div>

    <div class="stat-card accent-orange">
        <div class="stat-icon">🏊</div>
        <div class="stat-label">الكباتن</div>
        <div class="stat-value"><?= number_format($stats['captains']['total'] ?? 0) ?></div>
        <div class="stat-sub">كابتن نشط</div>
    </div>

    <div class="stat-card accent-purple">
        <div class="stat-icon">👨‍💼</div>
        <div class="stat-label">الموظفون</div>
        <div class="stat-value"><?= number_format($stats['users']['total'] ?? 0) ?></div>
        <div class="stat-sub">مستخدم في النظام</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-icon">🏢</div>
        <div class="stat-label">الفروع</div>
        <div class="stat-value"><?= number_format($stats['branches']['total'] ?? 0) ?></div>
        <div class="stat-sub">فرع مسجّل</div>
    </div>

    <div class="stat-card accent-pink">
        <div class="stat-icon">📋</div>
        <div class="stat-label">خطط الأسعار</div>
        <div class="stat-value"><?= number_format($stats['plans']['total'] ?? 0) ?></div>
        <div class="stat-sub">خطة متاحة</div>
    </div>

</div>

<!-- ══ TABLES ROW 1 ════════════════════════════════════════════════════ -->
<div class="dash-row">

    <!-- Recent Receipts -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🧾 آخر الإيصالات</h3>
            <a href="<?= APP_URL ?>/receipts">عرض الكل ←</a>
        </div>
        <?php if (!empty($recentReceipts)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>الفرع</th>
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
                    <td style="font-size:.88rem"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty">لا توجد إيصالات حديثة</div>
        <?php endif; ?>
    </div>

    <!-- Recent Transactions -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>💳 آخر المعاملات</h3>
            <a href="<?= APP_URL ?>/transactions">عرض الكل ←</a>
        </div>
        <?php if (!empty($recentTransactions)): ?>
        <table>
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentTransactions as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['client_name'] ?? '—') ?></strong></td>
                    <td style="color:#22c55e;font-weight:800;font-size:1rem"><?= number_format($t['amount'] ?? 0) ?> ج</td>
                    <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty">لا توجد معاملات حديثة</div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ TABLES ROW 2 ════════════════════════════════════════════════════ -->
<div class="dash-row">

    <!-- Top Branches -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🏢 أداء الفروع</h3>
            <a href="<?= APP_URL ?>/admin/branches">إدارة الفروع ←</a>
        </div>
        <?php if (!empty($branchStats)): ?>
        <table>
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>الإيصالات</th>
                    <th>المدفوعات</th>
                    <th>النسبة</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $maxReceipts = max(array_column($branchStats, 'receipt_count') ?: [1]);
            foreach ($branchStats as $b):
                $pct = $maxReceipts ? round($b['receipt_count'] / $maxReceipts * 100) : 0;
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                    <td><?= number_format($b['receipt_count']) ?></td>
                    <td style="color:#22c55e;font-weight:700"><?= number_format($b['total_paid'] ?? 0) ?> ج</td>
                    <td style="min-width:90px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem"><?= $pct ?>%</div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty">لا توجد بيانات للفروع</div>
        <?php endif; ?>
    </div>

    <!-- Top Captains -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🏊 أداء الكابتنات</h3>
            <a href="<?= APP_URL ?>/admin/captains">إدارة الكابتنات ←</a>
        </div>
        <?php if (!empty($captainStats)): ?>
        <table>
            <thead>
                <tr>
                    <th>الكابتن</th>
                    <th>الإيصالات</th>
                    <th>مكتمل</th>
                    <th>النسبة</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $maxCap = max(array_column($captainStats, 'receipt_count') ?: [1]);
            foreach ($captainStats as $cap):
                $cpct = $maxCap ? round($cap['receipt_count'] / $maxCap * 100) : 0;
                $compPct = $cap['receipt_count'] ? round($cap['completed_count'] / $cap['receipt_count'] * 100) : 0;
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cap['captain_name']) ?></strong></td>
                    <td><?= number_format($cap['receipt_count']) ?></td>
                    <td><span class="badge badge-success"><?= $compPct ?>%</span></td>
                    <td style="min-width:90px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $cpct ?>%;background:#a855f7"></div>
                        </div>
                        <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem"><?= $cpct ?>%</div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty">لا توجد بيانات للكابتنات</div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ TABLES ROW 3 ════════════════════════════════════════════════════ -->
<div class="dash-row">

    <!-- Top Clients -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>🥇 أعلى العملاء دفعاً</h3>
            <a href="<?= APP_URL ?>/clients">عرض العملاء ←</a>
        </div>
        <?php if (!empty($topClients)): ?>
        <table>
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>الهاتف</th>
                    <th>إجمالي المدفوع</th>
                    <th>الإيصالات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topClients as $i => $cl): ?>
                <tr>
                    <td>
                        <span style="color:var(--muted);font-size:.8rem;margin-left:.3rem;font-weight:700"><?= $i+1 ?>.</span>
                        <strong><?= htmlspecialchars($cl['client_name']) ?></strong>
                    </td>
                    <td style="font-size:.88rem;color:var(--muted)"><?= htmlspecialchars($cl['phone'] ?? '—') ?></td>
                    <td style="color:#22c55e;font-weight:800;font-size:1rem"><?= number_format($cl['total_paid'] ?? 0) ?> ج</td>
                    <td><?= number_format($cl['receipt_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="dash-empty">لا توجد بيانات</div>
        <?php endif; ?>
    </div>

    <!-- Employee Activity -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>👨‍💼 نشاط الموظفين</h3>
            <a href="<?= APP_URL ?>/admin/users">إدارة الموظفين ←</a>
        </div>
        <?php if (!empty($userStats)): ?>
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>الإيصالات</th>
                    <th>هذا الشهر</th>
                </tr>
            </thead>
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
        <?php else: ?>
            <div class="dash-empty">لا توجد بيانات</div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ Captain Activity Log ═════════════════════════════════════════════ -->
<?php if (!empty($recentCaptainAuditLog)): ?>
<div class="section-title">🧑‍✈️ آخر تحديثات الكباتن</div>
<div class="dash-card" style="margin-bottom:2rem">
    <ul class="activity-list">
        <?php foreach ($recentCaptainAuditLog as $log): ?>
        <li>
            <div class="activity-icon">🧑‍✈️</div>
            <div class="activity-text">
                <strong><?= htmlspecialchars($log['changed_by_name'] ?? 'مستخدم') ?></strong>
                <?= htmlspecialchars(adminActivityLabel((string)($log['action'] ?? ''))) ?>
                <?php if (!empty($log['detail'])): ?>
                    — <em><?= htmlspecialchars($log['detail']) ?></em>
                <?php endif; ?>
                <div class="activity-time"><?= htmlspecialchars($log['created_at'] ?? '') ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- ══ Recent Activity Log ═════════════════════════════════════════════ -->
<?php if (!empty($recentAuditLog)): ?>
<div class="section-title">🕐 آخر التعديلات والنشاطات</div>
<div class="dash-card" style="margin-bottom:2rem">
    <ul class="activity-list">
        <?php foreach ($recentAuditLog as $log): ?>
        <li>
            <div class="activity-icon">✏️</div>
            <div class="activity-text">
                <strong><?= htmlspecialchars($log['changed_by_name'] ?? 'مستخدم') ?></strong>
                <?php if (in_array(($log['log_type'] ?? ''), ['branch', 'captain'], true)): ?>
                    <?= htmlspecialchars(adminActivityLabel((string)($log['field_name'] ?? ''))) ?>
                    <?php if (!empty($log['old_value'])): ?>
                        — <em><?= htmlspecialchars($log['old_value']) ?></em>
                    <?php endif; ?>
                <?php else: ?>
                    عدّل إيصال رقم #<?= htmlspecialchars((string)($log['entity_id'] ?? '')) ?>
                    — <?= htmlspecialchars($log['field_name'] ?? '') ?>
                    <?php if (!empty($log['old_value']) && !empty($log['new_value'])): ?>
                        من <em><?= htmlspecialchars($log['old_value']) ?></em>
                        إلى <em><?= htmlspecialchars($log['new_value']) ?></em>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="activity-time"><?= htmlspecialchars($log['changed_at'] ?? '') ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
