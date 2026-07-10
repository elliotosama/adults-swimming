<?php // views/receipts/index.php
$pageClass = 'page--full';
require ROOT . '/views/includes/layout_top.php';


function paginationUrl(int $p): string {
    $q         = $_GET;
    $q['page'] = $p;
    return APP_URL . '/receipts?' . http_build_query($q);
}

function exportUrl(): string {
    $params = $_GET;
    unset($params['page']);
    if (empty($params) && !empty($_SESSION['receipt_filters'])) {
        $params = $_SESSION['receipt_filters'];
    }
    $query = http_build_query($params);
    return APP_URL . '/receipt/export' . ($query ? '?' . $query : '');
}

function formatAmPm(string $time): string {
    if (empty($time)) return '—';
    try {
        return (new DateTime($time))->format('g:i A');
    } catch (\Exception $e) {
        return $time;
    }
}

function formatDateDmy(?string $date): string {
    if (empty($date)) return '—';
    try {
        return (new DateTime($date))->format('d/m/Y');
    } catch (\Exception $e) {
        return htmlspecialchars($date);
    }
}

function exerciseDaysLabel(?string $days): string {
    $days = trim((string) $days);
    if ($days === '') return '—';

    $map = [
        'Sunday' => 'الأحد',
        'Monday' => 'الاثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
    ];

    $parts = array_filter(array_map('trim', explode(',', $days)));
    $labels = array_map(fn($day) => $map[$day] ?? $day, $parts);
    return htmlspecialchars(implode('-', $labels));
}

// ── Renewal-type → Arabic label ─────────────────────────────────────────
//
// Mirrors the JS renewalTypeLabel() map used by the live-search buildRow().
// Used here so the server-rendered table (initial page load / after
// "إعادة تعيين") shows the same Arabic labels as the live-filtered table,
// instead of the raw English enum value.
function renewalTypeLabel(?string $type): string {
    $map = [
        'new'              => 'جديد',
        'current_renewal'  => 'حالي',
        'previous_renewal' => 'سابق',
        'renew'            => 'تجديد',
        'renewal'          => 'تجديد',
        'جديد'             => 'جديد',
        'تجديد'            => 'تجديد',
    ];
    $key = strtolower(trim((string) $type));
    if ($key === '') return '—';
    return $map[$key] ?? htmlspecialchars($type);
}

$canFilter = fn(string $key): bool => in_array($key, $allowedFilters ?? [], true);
$isAdmin   = $isAdmin ?? false;
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;margin:0 0 .5rem;color:black">حذف الإيصال</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من حذف هذا الإيصال؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;transition:background .15s">حذف</button>
        </div>
    </div>
</div>

<style>
/* ── Match manage.php's dark theme: bg, surface, border, accent, Cairo font ── */
:root {
    --bg:      #1E1E2D;
    --surface: #252736;
    --border:  #3C3F58;
    --primary: #007ACC;
    --text:    #FFFFFF;
    --muted:   #ffffff;
}
.page--full,
.page--full * {
    font-family: 'Cairo', sans-serif;
}
.page--full {
    background: var(--bg);
    color: var(--text);
    font-size: 16px;
}
.filter-panel,
.card,
#tableCard {
    background: var(--surface);
    border-color: var(--border);
}
table th {
    background: var(--primary);
    font-weight: bold;
    text-align: center;
}

@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }

/* ══════════════════════════════════════════════════════════════════
   RESULT COUNT
══════════════════════════════════════════════════════════════════ */
.receipt-count-block {
    display: flex;
    align-items: baseline;
    gap: .5rem;
    margin-bottom: .75rem;
}
.receipt-count-number {
    font-size: 2.6rem;
    line-height: 1;
    color: var(--primary);
    letter-spacing: -.03em;
}
.receipt-count-label {
    font-size: 1rem;
    color: var(--muted);
}

/* ══════════════════════════════════════════════════════════════════
   PAGE HEADER
══════════════════════════════════════════════════════════════════ */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    margin-bottom: 1.25rem;
}
.page-header-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════════════════
   FILTER PANEL
══════════════════════════════════════════════════════════════════ */
.filter-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: .6rem;
    z-index: 1;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: .75rem 1rem;
}
.filter-group {
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: .3rem;
}
.filter-group label {
    font-size: .82rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.filter-group input,
.filter-group select {
    padding: .48rem .7rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: .92rem;
    background: var(--bg);
    color: var(--text);
    width: 100%;
}
.filter-group select[multiple] { height: 90px; }

/* Wraps a "from/to" date pair. On tablet/desktop this is transparent to
   layout (display:contents) so the two .filter-group children behave
   exactly as before inside the auto-fill grid. On mobile it becomes a
   2-column grid so "from" and "to" sit on the same line. */
.filter-pair {
    display: contents;
}

.filter-actions {
    display: flex;
    gap: .6rem;
    align-items: center;
    margin-top: .9rem;
    flex-wrap: wrap;
}

/* ══════════════════════════════════════════════════════════════════
   LIVE SEARCH SPINNER
══════════════════════════════════════════════════════════════════ */
.search-wrap { position: relative; }
.search-wrap input { padding-left: 2rem; }
.search-spinner {
    display: none;
    width: 14px; height: 14px;
    border: 2px solid var(--border);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin .6s linear infinite;
    position: absolute;
    left: .6rem; top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

/* ══════════════════════════════════════════════════════════════════
   TAG-CHECKBOX GROUPS
══════════════════════════════════════════════════════════════════ */
.tag-check-group {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    align-items: center;
    padding: .35rem 0;
}
.tag-check {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .32rem .75rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: .84rem;
    cursor: pointer;
    user-select: none;
    transition: background .15s, border-color .15s, color .15s;
    background: var(--bg);
    color: var(--text);
}
.tag-check:hover { border-color: var(--primary); color: var(--primary); }
.tag-check.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.tag-check input[type="checkbox"] { display: none; }

.tag-clear {
    border: none;
    background: transparent;
    color: var(--muted);
    font-size: .78rem;
    cursor: pointer;
    padding: .2rem .4rem;
    border-radius: 4px;
    transition: color .15s;
}
.tag-clear:hover { color: #e53e3e; }

.badge-updated {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    font-size: .75rem;
    padding: .15rem .45rem;
    border-radius: 999px;
    margin-right: .3rem;
}

/* ══════════════════════════════════════════════════════════════════
   BRANCH CHIP SCROLL
══════════════════════════════════════════════════════════════════ */
.branch-chip-scroll {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
    max-height: 72px;
    overflow-y: auto;
    padding: .25rem 0;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}
.branch-chip-scroll .tag-check {
    font-size: .78rem;
    padding: .22rem .58rem;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════════
   PAGINATION
══════════════════════════════════════════════════════════════════ */
.pagination {
    display: flex;
    gap: .35rem;
    align-items: center;
    justify-content: center;
    padding: 1rem 0;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 .55rem;
    border-radius: 6px;
    font-size: .88rem;
    border: 1px solid var(--border);
    text-decoration: none;
    color: var(--text);
}
.pagination a:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.pagination .active { background: var(--primary); color: #fff; border-color: var(--primary);}
.pagination .disabled { opacity: .4; pointer-events: none; }
.pag-info { font-size: .84rem; color: var(--muted); text-align: center; }

/* ══════════════════════════════════════════════════════════════════
   TABLE  (borders added)
══════════════════════════════════════════════════════════════════ */
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 8px;
}
.card { width: 100%; max-width: 100%; }

table {
    width: 100%;
    table-layout: auto;
    border-collapse: collapse;
}
table th {
    white-space: nowrap;
    font-size: .85rem;
    color: #fff;
    padding: .65rem .75rem;
    letter-spacing: .02em;
    border: 1px solid var(--border);
}
table td {
    white-space: nowrap;
    color: #fff;
    font-size: 1.2rem;
    padding: .6rem .75rem;
    border: 1px solid var(--border);
    text-align: center;
}
table tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.02);
}
table tbody tr:hover {
    background: rgba(0, 122, 204, 0.08);
}
table td.wrap-cell {
    white-space: normal;
    min-width: 130px;
}
table td strong { color: #fff;}


input[type="date"]::-webkit-calendar-picker-indicator {
    background-color: #fff
}


/* ══════════════════════════════════════════════════════════════════
   REFUND BADGE
══════════════════════════════════════════════════════════════════ */
.badge-refund {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: #fff0f0;
    color: #c0392b;
    border: 1px solid #f5c6c6;
    font-size: .78rem;
    padding: .2rem .55rem;
    border-radius: 999px;
    white-space: nowrap;
}

.td-actions {
    display: flex;
    gap: .35rem;
    flex-wrap: wrap;
    align-items: center;
}

/* ══════════════════════════════════════════════════════════════════
   RECEIPT OVERLAYS
══════════════════════════════════════════════════════════════════ */
.receipt-overlay {
    position: fixed;
    inset: 0;
    z-index: 9500;
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding: 32px 18px;
    background: rgba(8, 10, 18, .72);
    backdrop-filter: blur(6px);
    overflow-y: auto;
}
.receipt-overlay.open { display: flex; }
.receipt-overlay-panel {
    margin-top: 60px;
    width: min(1120px, 100%);
    max-height: calc(100vh - 64px);
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 24px 80px rgba(0,0,0,.32);
    overflow: hidden;
}
.receipt-overlay-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    background: #1E1E2D;
    border-bottom: 1px solid var(--border);
}
.receipt-overlay-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--text);
}
.receipt-overlay-close {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text);
    cursor: pointer;
}
.receipt-overlay-close:hover {
    border-color: var(--primary);
    color: #fff;
}
.receipt-overlay-body {
    padding: 18px;
    overflow: auto;
}
.receipt-overlay-body.loading,
.receipt-overlay-body.error {
    min-height: 180px;
    display: grid;
    place-items: center;
    color: var(--muted);
}
.receipt-edit-frame {
    width: 100%;
    height: calc(100vh - 150px);
    min-height: 560px;
    border: 0;
    background: var(--bg);
}
.receipt-evidence-lightbox {
    position: fixed;
    inset: 0;
    z-index: 9800;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(0,0,0,.84);
}
.receipt-evidence-lightbox.open { display: flex; }
.receipt-evidence-box {
    position: relative;
    max-width: min(92vw, 920px);
    max-height: 88vh;
}
.receipt-evidence-box img {
    display: block;
    max-width: 100%;
    max-height: 88vh;
    object-fit: contain;
    border-radius: 8px;
    background: #111;
    box-shadow: 0 18px 70px rgba(0,0,0,.55);
}
.receipt-evidence-close {
    position: absolute;
    top: -16px;
    left: -16px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 999px;
    background: #fff;
    color: #111;
    cursor: pointer;
    font-weight: 700;
}

/* ══════════════════════════════════════════════════════════════════
   RESPONSIVE — TABLET (≤ 900px)
══════════════════════════════════════════════════════════════════ */
@media (max-width: 900px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: .65rem; }
    .page-header-actions { width: 100%; }
    .page-header-actions .btn { flex: 1 1 auto; text-align: center; justify-content: center; }
    .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .receipt-count-number { font-size: 2.1rem; }
    table th { font-size: .8rem; padding: .55rem .65rem; }
    table td { font-size: .92rem; padding: .55rem .65rem; }
    table td.wrap-cell { min-width: 110px; }
    .td-actions { flex-direction: column; align-items: stretch; }
    .td-actions .btn { width: 100%; text-align: center; justify-content: center; }
}

/* ══════════════════════════════════════════════════════════════════
   RESPONSIVE — MOBILE (≤ 600px)
══════════════════════════════════════════════════════════════════ */
@media (max-width: 600px) {
    .filter-panel { padding: .9rem 1rem; }
    .filter-grid { grid-template-columns: 1fr; gap: .65rem; }
    .filter-group[style*="span 2"] { grid-column: span 1 !important; }
    .filter-group label { font-size: .78rem; }
    .filter-group input, .filter-group select { font-size: .88rem; }
    .tag-check-group { flex-wrap: wrap; gap: .35rem; }

    /* Force each "from/to" date pair onto a single line on mobile */
    .filter-pair {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
    }
    .filter-pair .filter-group label {
        font-size: .72rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .filter-pair .filter-group input[type="date"] {
        padding: .4rem .3rem;
        font-size: .8rem;
    }

    /* Keep branch chips scrollable on mobile instead of expanding freely */
    .branch-chip-scroll {
        max-height: 100px;
        overflow-y: auto;
        flex-wrap: wrap;
    }

    .tag-check { font-size: .8rem; padding: .28rem .65rem; }
    .filter-actions { flex-direction: column; gap: .45rem; }
    .filter-actions .btn { width: 100%; text-align: center; }
    .receipt-count-number { font-size: 1.9rem; }
    .receipt-count-label  { font-size: .9rem; }
    table { min-width: 1180px; }
    table th { font-size: .76rem; padding: .5rem .55rem; }
    table td { font-size: .88rem; padding: .5rem .55rem; }
    .td-actions { flex-direction: row; flex-wrap: wrap; gap: .25rem; min-width: 120px; }
    .td-actions .btn { width: auto; padding: .28rem .55rem; font-size: .75rem; }
    .receipt-overlay { padding: 12px 8px; }
    .receipt-overlay-panel { max-height: calc(100vh - 24px); }
    .receipt-overlay-body { padding: 12px; }
    .receipt-edit-frame { height: calc(100vh - 92px); min-height: 520px; }
    .pagination a, .pagination span { min-width: 1.8rem; height: 1.8rem; font-size: .8rem; padding: 0 .4rem; }
    .pag-info { font-size: .78rem; }
    #confirmModal > div { width: 95% !important; padding: 1.5rem 1.1rem 1.1rem !important; }
}

@media (max-width: 400px) {
    .page-title { font-size: 1.2rem; }
    .receipt-count-number { font-size: 1.65rem; }
    table { min-width: 1100px; }
    table th { font-size: .72rem; padding: .45rem .48rem; }
    table td { font-size: .82rem; padding: .45rem .48rem; }
    .badge-refund  { font-size: .72rem; padding: .15rem .42rem; }
    .badge-updated { font-size: .7rem; padding: .12rem .38rem; }
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🧾 الإيصالات</h1>
        <p class="breadcrumb">لوحة التحكم · الإيصالات</p>
    </div>
    <div class="page-header-actions">
        <?php if ($isAdmin): ?>
        <a href="<?= exportUrl() ?>" class="btn btn-secondary">⬇️ تصدير Excel</a>
        <a href="<?= APP_URL ?>/receipt/create" class="btn btn-primary">+ إضافة إيصال جديد</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- ── Result count ── -->
<div class="receipt-count-block">
    <span class="receipt-count-number" id="resultCountBig"><?= number_format($total) ?></span>
    <span class="receipt-count-label">إيصال</span>
</div>

<!-- ── Filter Panel ── -->
<div class="filter-panel">
    <form method="GET" action="<?= APP_URL ?>/receipts" id="filterForm">
        <input type="hidden" name="page" value="1">

        <div class="filter-grid">

            <?php if ($canFilter('search')): ?>
            <div class="filter-group" style="grid-column:span 2">
                <label>🔍 بحث (اسم / هاتف / رقم العميل / رقم الإيصال)</label>
                <div class="search-wrap">
                    <input type="text" name="search" id="liveSearch"
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                           placeholder="ابحث بالاسم، الهاتف، رقم العميل، أو رقم الإيصال..." autocomplete="off">
                    <span class="search-spinner" id="searchSpinner"></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('first_session')): ?>
            <div class="filter-pair">
                <div class="filter-group">
                    <label>تاريخ البدايه - من</label>
                    <input type="date" name="first_session_from"
                           value="<?= htmlspecialchars($filters['first_session_from'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>تاريخ البدايه — إلى</label>
                    <input type="date" name="first_session_to"
                           value="<?= htmlspecialchars($filters['first_session_to'] ?? '') ?>">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('last_session')): ?>
            <div class="filter-pair">
                <div class="filter-group">
                    <label>تاريخ النهايه — من</label>
                    <input type="date" name="last_session_from"
                           value="<?= htmlspecialchars($filters['last_session_from'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>تاريخ النهايه — إلى</label>
                    <input type="date" name="last_session_to"
                           value="<?= htmlspecialchars($filters['last_session_to'] ?? '') ?>">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('created')): ?>
            <div class="filter-pair">
                <div class="filter-group">
                    <label>تاريخ الإنشاء — من</label>
                    <input type="date" name="created_from"
                           value="<?= htmlspecialchars($filters['created_from'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>تاريخ الإنشاء — إلى</label>
                    <input type="date" name="created_to"
                           value="<?= htmlspecialchars($filters['created_to'] ?? '') ?>">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('statuses')): ?>
            <?php
            $allStatuses = ['completed' => 'مكتمل', 'not_completed' => 'غير مكتمل'];
            $selStatuses = (array) ($filters['statuses'] ?? []);
            ?>
            <div class="filter-group">
                <label>الحالة</label>
                <div class="tag-check-group" id="statusTagGroup">
                    <?php foreach ($allStatuses as $val => $lbl): ?>
                    <label class="tag-check <?= in_array($val, $selStatuses) ? 'active' : '' ?>">
                        <input type="checkbox" name="statuses[]" value="<?= $val ?>"
                               <?= in_array($val, $selStatuses) ? 'checked' : '' ?>>
                        <?= $lbl ?>
                    </label>
                    <?php endforeach; ?>
                    <button type="button" class="tag-clear" data-group="statusTagGroup"
                            style="<?= empty($selStatuses) ? 'display:none' : '' ?>">✕ إلغاء</button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('renewal_types')): ?>
            <?php
            $allRenewalTypes = [
                'new'              => 'جديد',
                'renew'            => 'تجديد',
                'previous_renewal' => 'تجديد سابق',
                'current_renewal'  => 'تجديد حالي',
            ];
            $selRenewalTypes = (array) ($filters['renewal_types'] ?? []);
            ?>
            <div class="filter-group" style="grid-column:span 2">
                <label>نوع الإيصال</label>
                <div class="tag-check-group" id="renewalTagGroup">
                    <?php foreach ($allRenewalTypes as $val => $lbl): ?>
                    <label class="tag-check <?= in_array($val, $selRenewalTypes) ? 'active' : '' ?>">
                        <input type="checkbox" name="renewal_types[]" value="<?= $val ?>"
                               <?= in_array($val, $selRenewalTypes) ? 'checked' : '' ?>>
                        <?= $lbl ?>
                    </label>
                    <?php endforeach; ?>
                    <button type="button" class="tag-clear" data-group="renewalTagGroup"
                            style="<?= empty($selRenewalTypes) ? 'display:none' : '' ?>">✕ إلغاء</button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('branch')): ?>
            <?php $selBranches = array_map('intval', (array) ($filters['branch_ids'] ?? [])); ?>
            <div class="filter-group">
                <label style="display:flex;align-items:center;justify-content:space-between">
                    <span>الفرع</span>
                    <button type="button" class="tag-clear" data-group="branchTagGroup"
                            style="<?= empty($selBranches) ? 'display:none' : '' ?>">✕ إلغاء</button>
                </label>
                <div class="branch-chip-scroll tag-check-group" id="branchTagGroup">
                    <?php foreach ($branches as $b): ?>
                    <label class="tag-check <?= in_array((int)$b['id'], $selBranches) ? 'active' : '' ?>">
                        <input type="checkbox" name="branch_ids[]" value="<?= $b['id'] ?>"
                               <?= in_array((int)$b['id'], $selBranches) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($b['branch_name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('creator')): ?>
            <div class="filter-group">
                <label>المنشئ</label>
                <select name="creator_id" id="creatorSelect">
                    <option value="">— الكل —</option>
                    <?php foreach ($creators as $u): ?>
                        <option value="<?= $u['id'] ?>"
                            <?= ((int)($filters['creator_id'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label id="creatorOnlyWrap"
                       style="display:<?= !empty($filters['creator_id']) ? 'flex' : 'none' ?>;
                              align-items:center;gap:.4rem;margin-top:.5rem;cursor:pointer">
                    <input type="checkbox"
                           name="creator_created_only"
                           id="creatorOnlyCb"
                           value="1"
                           <?= !empty($filters['creator_created_only']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.84rem;color:var(--muted)">
                        الإيصالات المنشأة فقط
                        <small style="display:block;font-size:.74rem;margin-top:.1rem">
                            بدون تحديد: يشمل التعديلات والمعاملات أيضاً
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('has_updates')): ?>
            <div class="filter-group">
                <label>فقط الإيصالات المحدَّثة</label>
                <label style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem;cursor:pointer">
                    <input type="checkbox" name="has_updates" id="hasUpdatesCb" value="1"
                           <?= !empty($filters['has_updates']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.9rem;">
                        تفعيل
                        <small style="color:var(--muted);display:block;font-size:.76rem">
                            لديها سجل تعديل أو معاملتان على الأقل
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('has_no_updates')): ?>
            <div class="filter-group">
                <label>فقط الإيصالات المنشأة بدون تحديثات</label>
                <label style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem;cursor:pointer">
                    <input type="checkbox" name="has_no_updates" id="hasNoUpdatesCb" value="1"
                           <?= !empty($filters['has_no_updates']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.9rem;">
                        تفعيل
                        <small style="color:var(--muted);display:block;font-size:.76rem">
                            بدون سجل تعديل وأقل من معاملتين
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <?php if ($canFilter('has_refund')): ?>
            <div class="filter-group">
                <label>الإيصالات المستردّة</label>
                <label style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem;cursor:pointer">
                    <input type="checkbox" name="has_refund" value="1"
                           <?= !empty($filters['has_refund']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.9rem;">
                        تفعيل
                        <small style="color:var(--muted);display:block;font-size:.76rem;">
                            يُظهر فقط الإيصالات التي تم استرداد مبلغ منها
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

        </div><!-- .filter-grid -->

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">بحث</button>
            <a href="<?= APP_URL ?>/receipts?reset=1" class="btn btn-secondary">إعادة تعيين</a>
        </div>
    </form>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     TABLE
════════════════════════════════════════════════════════════════════ -->
<div class="card" id="tableCard">
    <?php if (empty($receipts)): ?>
        <div class="empty-state" id="emptyState">
            <div class="empty-icon">🧾</div>
            <p>لا توجد إيصالات تطابق معايير البحث.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap" id="tableWrap">
            <table>
                <thead>
                    <tr>
                        <th>نوع التجديد</th>
                        <th>رقم العميل</th>
                        <th>اسم العميل</th>
                        <th>العمر</th>
                        <th>الهاتف</th>
                        <th>أيام التمرين</th>
                        <th>وقت التمرين</th>
                        <th>المستوى</th>
                        <th>الكابتن</th>
                        <th>قيمه الاشتراك</th>
                        <th>المدفوع</th>
                        <th>أول تمرين</th>
                        <th>آخر تمرين</th>
                        <th>رقم الإيصال</th>
                        <?php if ($isAdmin): ?>
                            <th>المنشئ</th>
                            <?php endif; ?>
                            <th>مسترد؟</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="receiptsBody">
                    <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td><?= renewalTypeLabel($r['renewal_type'] ?? null) ?></td>
                            <td><?= $r['client_id'] ?? '—' ?></td>
                            <td class="wrap-cell"><?= htmlspecialchars($r['client_name'] ?? '—') ?></td>
                            <td style="text-align:center"><?= htmlspecialchars($r['client_age'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['client_phone'] ?? '—') ?></td>
                            <td><?= exerciseDaysLabel($r['exercise_days'] ?? null) ?></td>
                            <td><?= htmlspecialchars(formatAmPm($r['exercise_time'] ?? '')) ?></td>
                            <td style="text-align:center"><?= htmlspecialchars($r['level'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['captain_name'] ?? '—') ?></td>
                            <td style="color:#fff"><?= number_format((float)($r['plan_price'] ?? 0)) ?></td>
                            <td style="color:#4ade80;"><?= number_format((float)($r['total_paid'] ?? 0)) ?></td>
                            <td><?= formatDateDmy($r['first_session'] ?? null) ?></td>
                            <td><?= formatDateDmy($r['last_session'] ?? null) ?></td>
                            <td style="color:#fff;font-size:1rem;">
                                <?= htmlspecialchars($r['receipt_ref'] ?? $r['id']) ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td><?= htmlspecialchars($r['creator_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if (!empty($r['is_refunded'])): ?>
                                        <span class="badge-refund">↩️ مسترد</span>
                                    <?php else: ?>
                                        <span style="color:var(--muted)">—</span>
                                    <?php endif; ?>
                                </td>
                            <td>
                                <div class="td-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="loadReceiptModal(<?= (int)$r['id'] ?>)">عرض الإيصال</button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="loadLogsModal(<?= (int)$r['id'] ?>)"><?= $isAdmin ? 'المعاملات والتعديلات' : 'المعاملات الماليه' ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <p class="pag-info" id="pagInfo">
                عرض <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> من <?= number_format($total) ?>
            </p>
            <nav class="pagination" id="pagNav" aria-label="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= paginationUrl($page - 1) ?>">‹ السابق</a>
                <?php else: ?>
                    <span class="disabled">‹ السابق</span>
                <?php endif; ?>

                <?php
                $window = 2;
                $shown  = [];
                for ($i = 1; $i <= $lastPage; $i++) {
                    if ($i === 1 || $i === $lastPage || abs($i - $page) <= $window) $shown[] = $i;
                }
                $prev = null;
                foreach ($shown as $p):
                    if ($prev !== null && $p - $prev > 1): ?>
                        <span>…</span>
                    <?php endif;
                    if ($p === $page): ?>
                        <span class="active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= paginationUrl($p) ?>"><?= $p ?></a>
                    <?php endif;
                    $prev = $p;
                endforeach; ?>

                <?php if ($page < $lastPage): ?>
                    <a href="<?= paginationUrl($page + 1) ?>">التالي ›</a>
                <?php else: ?>
                    <span class="disabled">التالي ›</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<div class="receipt-overlay" id="receiptViewOverlay" aria-hidden="true">
    <div class="receipt-overlay-panel" role="dialog" aria-modal="true" aria-labelledby="receiptViewTitle">
        <div class="receipt-overlay-header">
            <h2 class="receipt-overlay-title" id="receiptViewTitle">عرض الإيصال</h2>
            <button type="button" class="receipt-overlay-close" onclick="closeReceiptOverlay('receiptViewOverlay')" aria-label="إغلاق">✕</button>
        </div>
        <div class="receipt-overlay-body" id="receiptViewBody"></div>
    </div>
</div>

<div class="receipt-overlay" id="receiptLogsOverlay" aria-hidden="true">
    <div class="receipt-overlay-panel" role="dialog" aria-modal="true" aria-labelledby="receiptLogsTitle">
        <div class="receipt-overlay-header">
            <h2 class="receipt-overlay-title" id="receiptLogsTitle"><?= $isAdmin ? 'المعاملات والتعديلات' : 'المعاملات الماليه' ?></h2>
            <button type="button" class="receipt-overlay-close" onclick="closeReceiptOverlay('receiptLogsOverlay')" aria-label="إغلاق">✕</button>
        </div>
        <div class="receipt-overlay-body" id="receiptLogsBody"></div>
    </div>
</div>

<div class="receipt-overlay" id="receiptEditOverlay" aria-hidden="true">
    <div class="receipt-overlay-panel" role="dialog" aria-modal="true" aria-labelledby="receiptEditTitle">
        <div class="receipt-overlay-header">
            <h2 class="receipt-overlay-title" id="receiptEditTitle">تحديث الإيصال</h2>
            <button type="button" class="receipt-overlay-close" onclick="closeReceiptOverlay('receiptEditOverlay')" aria-label="إغلاق">✕</button>
        </div>
        <iframe class="receipt-edit-frame" id="receiptEditFrame" title="تحديث الإيصال"></iframe>
    </div>
</div>

<div class="receipt-evidence-lightbox" id="receiptEvidenceLightbox" aria-hidden="true">
    <div class="receipt-evidence-box" role="dialog" aria-modal="true" aria-label="إثبات الدفع">
        <button type="button" class="receipt-evidence-close" onclick="closeReceiptEvidence()" aria-label="إغلاق">✕</button>
        <img id="receiptEvidenceImage" src="" alt="إثبات الدفع">
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     LIVE SEARCH / DYNAMIC TABLE
════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
    const input     = document.getElementById('liveSearch');
    if (!input) return;

    const spinner   = document.getElementById('searchSpinner');
    const countBig  = document.getElementById('resultCountBig');
    const tableCard = document.getElementById('tableCard');

    const BASE_URL   = <?= json_encode(APP_URL) ?>;
    const IS_ADMIN   = <?= json_encode($isAdmin) ?>;
    const PER_PAGE   = <?= (int) ($perPage ?? 25) ?>;

    let livePage     = 1;
    let liveTotalNow = <?= (int) $total ?>;
    let liveLastPage = <?= (int) $lastPage ?>;

    function esc(str) {
        if (str == null) return '—';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmt(n) {
        const num = parseFloat(n);
        if (isNaN(num)) return '—';
        return num
    }

    function fmtTime(t) {
        if (!t || t === '—') return '—';
        const parts = String(t).split(':');
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1] ?? '0', 10);
        if (isNaN(h) || isNaN(m)) return esc(t);
        const period = h >= 12 ? 'PM' : 'AM';
        const h12    = h % 12 || 12;
        return `${h12}:${String(m).padStart(2, '0')} ${period}`;
    }

    function fmtDate(d) {
        if (!d || d === '—') return '—';
        const parts = String(d).split('-');
        if (parts.length !== 3) return esc(d);
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function exerciseDaysLabel(days) {
        if (!days) return '—';
        const map = {
            Sunday: 'الأحد',
            Monday: 'الاثنين',
            Tuesday: 'الثلاثاء',
            Wednesday: 'الأربعاء',
            Thursday: 'الخميس',
            Friday: 'الجمعة',
            Saturday: 'السبت',
        };
        return String(days)
            .split(',')
            .map(day => map[day.trim()] || day.trim())
            .filter(Boolean)
            .map(esc)
            .join('-') || '—';
    }

function renewalTypeLabel(type) {
    const map = {
        'new':              'جديد',
        'current_renewal':  'حالي',
        'previous_renewal': 'سابق',
        'renew':            'تجديد',
        'renewal':          'تجديد',
        'جديد':             'جديد',
        'حالي':             'حالي',
        'سابق':             'سابق',
        'تجديد':            'تجديد',
    };
    const key = (type || '').toString().trim().toLowerCase();
    return map[key] || esc(type);
}

function buildRow(r) {
    const creatorCell  = IS_ADMIN ? `<td>${esc(r.creator_name)}</td>` : '';
    const receiptRef   = r.receipt_ref ? esc(r.receipt_ref) : esc(r.id);
    const refundedCell = r.is_refunded
        ? `<td><span class="badge-refund">↩️ مسترد</span></td>`
        : `<td><span style="color:var(--muted)">—</span></td>`;

    return `<tr>
        <td>${renewalTypeLabel(r.renewal_type)}</td>
        <td>${esc(r.client_id)}</td>
        <td class="wrap-cell">${esc(r.client_name)}</td>
        <td style="text-align:center">${esc(r.client_age)}</td>
        <td>${esc(r.client_phone)}</td>
        <td>${exerciseDaysLabel(r.exercise_days)}</td>
        <td>${fmtTime(r.exercise_time)}</td>
        <td style="text-align:center">${esc(r.level)}</td>
        <td>${esc(r.captain_name)}</td>
        <td style="color:#fff">${fmt(r.plan_price)}</td>
        <td style="color:#4ade80;">${fmt(r.total_paid)}</td>
        <td>${fmtDate(r.first_session)}</td>
        <td>${fmtDate(r.last_session)}</td>
        <td style="color:#fff;font-size:1rem;">${receiptRef}</td>
        ${creatorCell}
        ${refundedCell}
        <td>
            <div class="td-actions">
                <button type="button" class="btn btn-sm btn-secondary" onclick="loadReceiptModal('${esc(r.id)}')">عرض الإيصال</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="loadLogsModal('${esc(r.id)}')">${IS_ADMIN ? 'المعاملات والتعديلات' : 'المعاملات الماليه'}</button>
            </div>
        </td>
    </tr>`;
}

    function currentParams(page = 1) {
        const form   = document.getElementById('filterForm');
        const data   = new FormData(form);
        const params = new URLSearchParams();
        for (const [k, v] of data.entries()) {
            if (k !== 'page') params.append(k, v);
        }
        params.set('page', String(page));
        return params;
    }

    function buildPagination(page, lastPage, total, perPage) {
        document.getElementById('livePagInfo')?.remove();
        document.getElementById('livePagNav')?.remove();
        if (lastPage <= 1) return;

        const from = (page - 1) * perPage + 1;
        const to   = Math.min(page * perPage, total);
        const info = document.createElement('p');
        info.className   = 'pag-info';
        info.id          = 'livePagInfo';
        info.textContent = `عرض ${from}–${to} من ${total}`;
        tableCard.appendChild(info);

        const nav = document.createElement('nav');
        nav.className = 'pagination';
        nav.id        = 'livePagNav';
        nav.setAttribute('aria-label', 'pagination');

        const btn = (label, p, disabled = false, active = false) => {
            const el = document.createElement(disabled || active ? 'span' : 'a');
            el.innerHTML = label;
            if (disabled) el.classList.add('disabled');
            if (active)   el.classList.add('active');
            if (!disabled && !active) {
                el.href = '#';
                el.addEventListener('click', e => { e.preventDefault(); doSearch(p); });
            }
            return el;
        };

        nav.appendChild(btn('‹ السابق', page - 1, page <= 1));
        const window_ = 2;
        const shown   = [];
        for (let i = 1; i <= lastPage; i++) {
            if (i === 1 || i === lastPage || Math.abs(i - page) <= window_) shown.push(i);
        }
        let prev = null;
        for (const p of shown) {
            if (prev !== null && p - prev > 1) {
                const dots = document.createElement('span');
                dots.textContent = '…';
                nav.appendChild(dots);
            }
            nav.appendChild(btn(String(p), p, false, p === page));
            prev = p;
        }
        nav.appendChild(btn('التالي ›', page + 1, page >= lastPage));
        tableCard.appendChild(nav);
    }

    function showEmpty() {
        const tw = document.getElementById('tableWrap');
        if (tw) tw.style.display = 'none';
        document.getElementById('livePagInfo')?.remove();
        document.getElementById('livePagNav')?.remove();
        document.getElementById('pagNav')  && (document.getElementById('pagNav').style.display  = 'none');
        document.getElementById('pagInfo') && (document.getElementById('pagInfo').style.display = 'none');
        if (!document.getElementById('liveEmpty')) {
            const div = document.createElement('div');
            div.className = 'empty-state';
            div.id        = 'liveEmpty';
            div.innerHTML = '<div class="empty-icon">🧾</div><p>لا توجد إيصالات تطابق معايير البحث.</p>';
            tableCard.prepend(div);
        }
    }

    function hideEmpty() {
        document.getElementById('liveEmpty')?.remove();
        const tw = document.getElementById('tableWrap');
        if (tw) tw.style.display = '';
        document.getElementById('pagNav')  && (document.getElementById('pagNav').style.display  = 'none');
        document.getElementById('pagInfo') && (document.getElementById('pagInfo').style.display = 'none');
    }

    let timer = null;
    let ctrl  = null;

    async function doSearch(page = 1) {
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        if (spinner) spinner.style.display = 'block';

        const params = currentParams(page);
        history.pushState({ page }, '', `${BASE_URL}/receipts?${params}`);

        try {
            const res  = await fetch(`${BASE_URL}/receipts/search-json?${params}`, { signal: ctrl.signal });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();

            livePage     = json.page;
            liveTotalNow = json.total;
            liveLastPage = json.lastPage;

            if (countBig) countBig.textContent = Number(json.total)

            if (!json.data || json.data.length === 0) {
                showEmpty();
                buildPagination(0, 0, 0, PER_PAGE);
                return;
            }

            hideEmpty();

            let tbody = document.getElementById('receiptsBody');
            if (!tbody) {
                const creatorTh = IS_ADMIN ? '<th>المنشئ</th>' : '';
                const wrap = document.createElement('div');
                wrap.className = 'table-wrap';
                wrap.id        = 'tableWrap';
                wrap.innerHTML = `<table>
                    <thead><tr>
                        <th>نوع التجديد</th>
                        <th>رقم العميل</th>
                        <th>اسم العميل</th>
                        <th>العمر</th>
                        <th>الهاتف</th>
                        <th>أيام التمرين</th>
                        <th>وقت التمرين</th>
                        <th>المستوى</th>
                        <th>الكابتن</th>
                        <th>قيمه الاشتراك</th>
                        <th>المدفوع</th>
                        <th>أول تمرين</th>
                        <th>آخر تمرين</th>
                        <th>رقم الإيصال</th>
                        ${creatorTh}
                        <th>مسترد؟</th>
                        <th>الإجراءات</th>
                    </tr></thead>
                    <tbody id="receiptsBody"></tbody>
                </table>`;
                tableCard.prepend(wrap);
                tbody = document.getElementById('receiptsBody');
            }

            tbody.innerHTML = json.data.map(buildRow).join('');
            buildPagination(json.page, json.lastPage, json.total, json.perPage);

        } catch (e) {
            if (e.name !== 'AbortError') console.error('Live search error:', e);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    function restoreFormFromUrl(urlParams) {
        const form = document.getElementById('filterForm');
        if (!form) return;
        const searchInput = document.getElementById('liveSearch');
        if (searchInput) searchInput.value = urlParams.get('search') ?? '';
        form.querySelectorAll('input[type="date"]').forEach(el => {
            el.value = urlParams.get(el.name) ?? '';
        });
        form.querySelectorAll('select').forEach(el => {
            el.value = urlParams.get(el.name) ?? '';
            if (el.id === 'creatorSelect') {
                const wrap = document.getElementById('creatorOnlyWrap');
                const cb   = document.getElementById('creatorOnlyCb');
                if (wrap && cb) {
                    wrap.style.display = el.value ? 'flex' : 'none';
                    if (!el.value) cb.checked = false;
                }
            }
        });
        form.querySelectorAll('input[type="checkbox"]').forEach(el => {
            if (el.closest('.tag-check-group')) return;
            el.checked = urlParams.has(el.name);
        });
        form.querySelectorAll('.tag-check-group').forEach(group => {
            const clearBtn = group.querySelector('.tag-clear');
            group.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                const active = urlParams.getAll(cb.name).includes(cb.value);
                cb.checked   = active;
                cb.closest('.tag-check')?.classList.toggle('active', active);
            });
            if (clearBtn) {
                const anyChecked = [...group.querySelectorAll('input[type="checkbox"]')].some(i => i.checked);
                clearBtn.style.display = anyChecked ? '' : 'none';
            }
        });
    }

    window.addEventListener('popstate', function (e) {
        const urlParams    = new URLSearchParams(window.location.search);
        const restoredPage = e.state?.page ?? parseInt(urlParams.get('page') ?? '1', 10);
        restoreFormFromUrl(urlParams);
        doSearch(restoredPage);
    });

    const creatorSelect = document.getElementById('creatorSelect');
    const creatorWrap   = document.getElementById('creatorOnlyWrap');
    const creatorCb     = document.getElementById('creatorOnlyCb');
    if (creatorSelect && creatorWrap && creatorCb) {
        creatorSelect.addEventListener('change', function () {
            const hasValue = this.value !== '';
            creatorWrap.style.display = hasValue ? 'flex' : 'none';
            if (!hasValue) creatorCb.checked = false;
            creatorCb.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    const hasUpdatesCb   = document.getElementById('hasUpdatesCb');
    const hasNoUpdatesCb = document.getElementById('hasNoUpdatesCb');
    if (hasUpdatesCb && hasNoUpdatesCb) {
        hasUpdatesCb.addEventListener('change', () => {
            if (hasUpdatesCb.checked) hasNoUpdatesCb.checked = false;
        });
        hasNoUpdatesCb.addEventListener('change', () => {
            if (hasNoUpdatesCb.checked) hasUpdatesCb.checked = false;
        });
    }

    document.querySelectorAll('.tag-check').forEach(label => {
        label.addEventListener('click', () => {
            const cb       = label.querySelector('input[type="checkbox"]');
            const group    = label.closest('.tag-check-group');
            const clearBtn = group?.querySelector('.tag-clear');
            cb.checked = !cb.checked;
            label.classList.toggle('active', cb.checked);
            if (clearBtn) {
                const anyChecked = [...group.querySelectorAll('input[type="checkbox"]')].some(i => i.checked);
                clearBtn.style.display = anyChecked ? '' : 'none';
            }
            clearTimeout(timer);
            timer = setTimeout(() => doSearch(1), 150);
        });
    });

    document.querySelectorAll('.tag-clear').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = document.getElementById(btn.dataset.group);
            if (!group) return;
            group.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
                cb.closest('.tag-check')?.classList.remove('active');
            });
            btn.style.display = 'none';
            clearTimeout(timer);
            timer = setTimeout(() => doSearch(1), 150);
        });
    });

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => doSearch(1), 300);
    });

    document.getElementById('filterForm')
        ?.querySelectorAll('select, input[type="date"], input[type="checkbox"]')
        .forEach(el => {
            if (el.closest('.tag-check-group')) return;
            el.addEventListener('change', () => {
                clearTimeout(timer);
                timer = setTimeout(() => doSearch(1), 150);
            });
        });

    window.showDeleteModal = function(form) {
        _pendingForm = form;
        const modal = document.getElementById('confirmModal');
        modal.classList.add('open');
        modal.style.display = 'flex';
    };
    window.closeModal = function() {
        const modal = document.getElementById('confirmModal');
        modal.classList.remove('open');
        modal.style.display = 'none';
        _pendingForm = null;
    };

})();

let _pendingForm = null;
document.getElementById('confirmBtn').addEventListener('click', function () {
    if (_pendingForm) _pendingForm.submit();
    closeModal();
});
document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

(function () {
    const BASE_URL   = <?= json_encode(APP_URL) ?>;
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    function openOverlay(id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    window.closeReceiptOverlay = function(id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.receipt-overlay.open')) {
            document.body.style.overflow = '';
        }
        if (id === 'receiptEditOverlay') {
            document.getElementById('receiptEditFrame').src = 'about:blank';
        }
    };

    async function loadFragment(url, overlayId, bodyId) {
        const body = document.getElementById(bodyId);
        body.className = 'receipt-overlay-body loading';
        body.innerHTML = 'جاري التحميل...';
        openOverlay(overlayId);

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            body.className = 'receipt-overlay-body';
            body.innerHTML = await response.text();
            bindReceiptOverlayActions(body);
        } catch (error) {
            body.className = 'receipt-overlay-body error';
            body.innerHTML = 'تعذر تحميل البيانات. حاول مرة أخرى.';
            console.error(error);
        }
    }

    window.loadReceiptModal = function(id) {
        loadFragment(`${BASE_URL}/receipt/view-modal?id=${encodeURIComponent(id)}`, 'receiptViewOverlay', 'receiptViewBody');
    };

    window.loadLogsModal = function(id) {
        loadFragment(`${BASE_URL}/receipt/logs-modal?id=${encodeURIComponent(id)}`, 'receiptLogsOverlay', 'receiptLogsBody');
    };

    window.loadEditModal = function(id) {
        const frame = document.getElementById('receiptEditFrame');
        frame.src = `${BASE_URL}/receipt/edit?id=${encodeURIComponent(id)}`;
        openOverlay('receiptEditOverlay');
    };

    function bindReceiptOverlayActions(root) {
        root.querySelectorAll('[data-rm-evidence]').forEach(button => {
            button.addEventListener('click', () => openReceiptEvidence(button.dataset.rmEvidence));
        });

        const emailBtn = root.querySelector('#rm-send-email-btn');
        if (emailBtn) {
            emailBtn.addEventListener('click', async () => {
                const message = root.querySelector('#rm-email-msg');
                emailBtn.disabled = true;
                const oldText = emailBtn.textContent;
                emailBtn.textContent = 'جاري الإرسال...';
                if (message) message.textContent = '';

                const formData = new FormData();
                formData.append('receipt_id', emailBtn.dataset.receiptId || '');
                formData.append('csrf_token', CSRF_TOKEN);

                try {
                    const response = await fetch(`${BASE_URL}/receipt/send-email`, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (message) {
                        message.style.color = data.success ? '#98C379' : '#E06C75';
                        message.textContent = data.success
                            ? 'تم إرسال البريد الإلكتروني بنجاح.'
                            : (data.message || 'تعذر إرسال البريد الإلكتروني.');
                    }
                } catch (error) {
                    if (message) {
                        message.style.color = '#E06C75';
                        message.textContent = 'حدث خطأ أثناء إرسال البريد الإلكتروني.';
                    }
                    console.error(error);
                } finally {
                    emailBtn.disabled = false;
                    emailBtn.textContent = oldText;
                }
            });
        }
    }

    document.querySelectorAll('.receipt-overlay').forEach(overlay => {
        overlay.addEventListener('click', event => {
            if (event.target === overlay) closeReceiptOverlay(overlay.id);
        });
    });

    window.openReceiptEvidence = function(url) {
        const lightbox = document.getElementById('receiptEvidenceLightbox');
        const image = document.getElementById('receiptEvidenceImage');
        if (!lightbox || !image) return;
        image.src = url;
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden', 'false');
    };

    window.closeReceiptEvidence = function() {
        const lightbox = document.getElementById('receiptEvidenceLightbox');
        const image = document.getElementById('receiptEvidenceImage');
        if (!lightbox || !image) return;
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden', 'true');
        image.src = '';
    };

    document.getElementById('receiptEvidenceLightbox')?.addEventListener('click', event => {
        if (event.target === event.currentTarget) closeReceiptEvidence();
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (document.getElementById('receiptEvidenceLightbox')?.classList.contains('open')) {
            closeReceiptEvidence();
            return;
        }
        const open = [...document.querySelectorAll('.receipt-overlay.open')].pop();
        if (open) closeReceiptOverlay(open.id);
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
