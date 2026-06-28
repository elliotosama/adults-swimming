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

// ── AM/PM formatter ───────────────────────────────────────────────────────
function formatAmPm(string $time): string {
    if (empty($time)) return '—';
    try {
        return (new DateTime($time))->format('g:i A'); // e.g. "9:30 AM"
    } catch (\Exception $e) {
        return $time;
    }
}

$canFilter = fn(string $key): bool => in_array($key, $allowedFilters ?? [], true);
$isAdmin   = $isAdmin ?? false;
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل الإيصال</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا الإيصال؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;transition:background .15s">تعطيل</button>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }
</style>

<script>
let _pendingForm = null;

function showDeleteModal(form) {
    _pendingForm = form;
    const modal = document.getElementById('confirmModal');
    modal.classList.add('open');
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('open');
    modal.style.display = 'none';
    _pendingForm = null;
}

document.getElementById('confirmBtn').addEventListener('click', function () {
    if (_pendingForm) _pendingForm.submit();
    closeModal();
});

document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});
</script>

<style>
/* ── Result count ──────────────────────────────────────────── */
.receipt-count-block {
    display: flex;
    align-items: baseline;
    gap: .5rem;
    margin-bottom: .5rem;
}
.receipt-count-number {
    font-size: 2.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--primary);
    letter-spacing: -.03em;
}
.receipt-count-label {
    font-size: 1rem;
    color: var(--muted);
    font-weight: 500;
}

/* ── Filter panel ──────────────────────────────────────────── */
.filter-panel{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:1.25rem 1.5rem;margin-bottom:.6rem;z-index:1}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem 1rem}
.filter-group{z-index:1;display:flex;flex-direction:column;gap:.3rem}
.filter-group label{z-index:1;font-size:.78rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.filter-group input,.filter-group select{padding:.42rem .65rem;border:1px solid var(--border);border-radius:6px;font-size:.88rem;background:var(--bg);color:var(--text);width:100%}
.filter-group select[multiple]{height:90px}
.filter-actions{display:flex;gap:.6rem;align-items:center;margin-top:.9rem;flex-wrap:wrap}
.badge-updated{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;font-size:.72rem;padding:.15rem .45rem;border-radius:999px;margin-right:.3rem}

/* ── Live search ───────────────────────────────────────────── */
.search-wrap{position:relative}
.search-wrap input{padding-right:2rem}
.search-spinner{display:none;width:14px;height:14px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .6s linear infinite;position:absolute;right:.6rem;top:50%;transform:translateY(-50%);pointer-events:none}
@keyframes spin{to{transform:translateY(-50%) rotate(360deg)}}

/* ── Tag-checkbox groups (branch & status) ─────────────────── */
.tag-check-group{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;padding:.35rem 0}
.tag-check{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .7rem;border:1px solid var(--border);border-radius:999px;font-size:.82rem;cursor:pointer;user-select:none;transition:background .15s,border-color .15s,color .15s;background:var(--bg);color:var(--text)}
.tag-check:hover{border-color:var(--primary);color:var(--primary)}
.tag-check.active{background:var(--primary);border-color:var(--primary);color:#fff;font-weight:600}
.tag-check input[type="checkbox"]{display:none}
.tag-clear{border:none;background:transparent;color:var(--muted);font-size:.78rem;cursor:pointer;padding:.2rem .4rem;border-radius:4px;transition:color .15s}
.tag-clear:hover{color:#e53e3e}

.pagination{display:flex;gap:.35rem;align-items:center;justify-content:center;padding:1rem 0}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:2rem;padding:0 .55rem;border-radius:6px;font-size:.85rem;border:1px solid var(--border);text-decoration:none;color:var(--text)}
.pagination a:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.pagination .active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700}
.pagination .disabled{opacity:.4;pointer-events:none}
.pag-info{font-size:.82rem;color:var(--muted);text-align:center}

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
    font-size: .75rem;
    padding: .2rem .55rem;
    white-space: nowrap;
}

/* ── Table tweaks ──────────────────────────────────────────── */
.table-wrap { overflow-x: scroll; width: 100%; }
.card { width: 100%; max-width: 100%; }
table { width: 100%; table-layout: auto; }
table th { white-space: nowrap; font-size: .8rem; }
table td { white-space: nowrap; color: #fff; font-size: .95rem; font-weight: 500; }
table td.wrap-cell { white-space: normal; min-width: 120px; }

/* ── Refund flag badge ─────────────────────────────────────── */
.badge-refund {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: #fff0f0;
    color: #c0392b;
    border: 1px solid #f5c6c6;
    font-size: .75rem;
    font-weight: 700;
    padding: .18rem .5rem;
    border-radius: 999px;
    white-space: nowrap;
}



/* ════════════════════════════════════════════════════════════════
   RECEIPTS INDEX — RESPONSIVE PATCH
════════════════════════════════════════════════════════════════ */

/* ── Tablet (≤900px) ── */
@media (max-width: 900px) {

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: .75rem;
    }
    .page-header > div:last-child {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        width: 100%;
    }
    .page-header > div:last-child .btn {
        flex: 1 1 auto;
        text-align: center;
        justify-content: center;
    }

    .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .filter-group[style*="span 2"] {
        grid-column: span 2;
    }

    .receipt-count-number { font-size: 2rem; }

    .table-wrap {
        -webkit-overflow-scrolling: touch;
        overflow-x: auto;
    }
    table th { font-size: .75rem; padding: .5rem .6rem; }
    table td { font-size: .82rem; padding: .5rem .6rem; }
    table td.wrap-cell { min-width: 100px; }

    .td-actions {
        display: flex;
        flex-direction: column;
        gap: .3rem;
        align-items: flex-start;
    }
    .td-actions .btn { width: 100%; text-align: center; justify-content: center; }

    .pagination { flex-wrap: wrap; gap: .25rem; }
}

/* ── Mobile (≤600px) ── */
@media (max-width: 600px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }
    .filter-group[style*="span 2"] {
        grid-column: span 1;
    }

    .filter-panel { padding: 1rem; }

    .filter-group:first-child { grid-column: 1; }

    .tag-check-group  { flex-wrap: wrap; }
    .branch-chip-scroll { max-height: none; overflow-y: visible; flex-wrap: wrap; }
    .tag-check        { font-size: .78rem; padding: .25rem .6rem; }

    .filter-actions {
        flex-direction: column;
        gap: .5rem;
    }
    .filter-actions .btn { width: 100%; text-align: center; }

    .receipt-count-number { font-size: 1.75rem; }
    .receipt-count-label  { font-size: .88rem; }
    .receipt-count-block  { margin-bottom: .75rem; }

    .table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    table {
        table-layout: auto;
        min-width: 900px;
    }
    table th { font-size: .72rem; padding: .45rem .5rem; white-space: nowrap; }
    table td { font-size: .78rem; padding: .45rem .5rem; white-space: nowrap; }

    .td-actions {
        flex-direction: row;
        flex-wrap: wrap;
        gap: .25rem;
        min-width: 180px;
    }
    .td-actions .btn {
        width: auto;
        padding: .25rem .5rem;
        font-size: .72rem;
    }

    .pagination a,
    .pagination span {
        min-width: 1.75rem;
        height: 1.75rem;
        font-size: .78rem;
        padding: 0 .4rem;
    }
    .pag-info { font-size: .75rem; }

    #confirmModal > div {
        width: 95% !important;
        padding: 1.5rem 1.25rem 1.25rem !important;
    }
}

/* ── Very small (≤400px) ── */
@media (max-width: 400px) {

    .page-title { font-size: 1.2rem; }

    .badge-refund { font-size: .7rem; padding: .15rem .4rem; }
    .badge-updated { font-size: .68rem; padding: .1rem .35rem; }

    table { min-width: 860px; }

    .filter-group label {
        font-size: .72rem;
    }
    .filter-group input,
    .filter-group select {
        font-size: .82rem;
        padding: .38rem .55rem;
    }
}

</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🧾 الإيصالات</h1>
        <p class="breadcrumb">لوحة التحكم · الإيصالات</p>
    </div>
    <div style="display:flex;gap:.6rem">
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

<!-- ── Big bold result count ─────────────────────────────────────────────── -->
<div class="receipt-count-block">
    <span class="receipt-count-number" id="resultCountBig"><?= number_format($total) ?></span>
    <span class="receipt-count-label">إيصال</span>
</div>

<!-- ── Filter Panel ──────────────────────────────────────────────────── -->
<div class="filter-panel">
    <form method="GET" action="<?= APP_URL ?>/receipts" id="filterForm">
        <input type="hidden" name="page" value="1">

        <div class="filter-grid">

            <!-- Search -->
            <?php if ($canFilter('search')): ?>
            <div class="filter-group" style="grid-column:span 2">
                <label>🔍 بحث (اسم / هاتف / رقم العميل)</label>
                <div class="search-wrap">
                    <input type="text" name="search" id="liveSearch"
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                           placeholder="ابحث..." autocomplete="off">
                    <span class="search-spinner" id="searchSpinner"></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- First session range -->
            <?php if ($canFilter('first_session')): ?>
            <div class="filter-group">
                <label>أول تمرين — من</label>
                <input type="date" name="first_session_from"
                       value="<?= htmlspecialchars($filters['first_session_from'] ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>أول تمرين — إلى</label>
                <input type="date" name="first_session_to"
                       value="<?= htmlspecialchars($filters['first_session_to'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <!-- Last session range -->
            <?php if ($canFilter('last_session')): ?>
            <div class="filter-group">
                <label>آخر تمرين — من</label>
                <input type="date" name="last_session_from"
                       value="<?= htmlspecialchars($filters['last_session_from'] ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>آخر تمرين — إلى</label>
                <input type="date" name="last_session_to"
                       value="<?= htmlspecialchars($filters['last_session_to'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <!-- Created at range -->
            <?php if ($canFilter('created')): ?>
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
            <?php endif; ?>

            <!-- Status tag-checkboxes -->
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

            <!-- Renewal type tag-checkboxes -->
            <?php if ($canFilter('renewal_types')): ?>
            <?php
            $allRenewalTypes = [
                'new'              => 'جديد',
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

            <!-- Branch tag-checkboxes -->
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

            <!-- Creator filter + created-only checkbox -->
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
                    <span style="font-size:.82rem;color:var(--muted)">
                        الإيصالات المنشأة فقط
                        <small style="display:block;font-size:.73rem;font-weight:400;margin-top:.1rem">
                            بدون تحديد: يشمل التعديلات والمعاملات أيضاً
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <!-- Has updates toggle -->
            <?php if ($canFilter('has_updates')): ?>
            <div class="filter-group">
                <label>فقط المحدَّثة أو بها معاملات</label>
                <label style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem;cursor:pointer">
                    <input type="checkbox" name="has_updates" value="1"
                           <?= !empty($filters['has_updates']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.88rem">
                        تفعيل
                        <small style="color:var(--muted);display:block;font-size:.75rem;font-weight:400">
                            يُظهر فقط الإيصالات التي تم تعديلها أو لديها مدفوعات
                        </small>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <!-- Has refund toggle -->
            <?php if ($canFilter('has_refund')): ?>
            <div class="filter-group">
                <label>الإيصالات المستردّة</label>
                <label style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem;cursor:pointer">
                    <input type="checkbox" name="has_refund" value="1"
                           <?= !empty($filters['has_refund']) ? 'checked' : '' ?>
                           style="width:auto">
                    <span style="font-size:.88rem">
                        تفعيل
                        <small style="color:var(--muted);display:block;font-size:.75rem;font-weight:400">
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
                        <th>رقم الإيصال</th>
                        <th>نوع التجديد</th>
                        <th>رقم العميل</th>
                        <th>اسم العميل</th>
                        <th>العمر</th>
                        <th>الهاتف</th>
                        <th>وقت التمرين</th>
                        <th>المستوى</th>
                        <th>الكابتن</th>
                        <th>سعر الخطة</th>
                        <th>المدفوع</th>
                        <th>أول تمرين</th>
                        <th>آخر تمرين</th>
                        <?php if ($isAdmin): ?>
                        <th>المنشئ</th>
                        <?php endif; ?>
                        <th>الحالة</th>
                        <th>مُسترد</th>
                        <th>التعديلات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="receiptsBody">
                    <?php
                    $statusMap = [
                        'completed'     => ['badge-success', 'مكتمل'],
                        'not_completed' => ['badge-danger',  'غير مكتمل'],
                        'pending'       => ['badge-warning', 'معلّق'],
                    ];
                    foreach ($receipts as $r):
                        [$cls, $statusLabel] = $statusMap[$r['receipt_status']] ?? ['badge-secondary', $r['receipt_status']];
                        $hasActivity  = ($r['audit_count'] > 0 || $r['transaction_count'] > 0);
                    ?>
                        <tr>
                            <!-- 1 رقم الإيصال -->
                            <td style="color:#fff;font-size:.95rem;font-weight:600">
                                <?= htmlspecialchars($r['receipt_ref'] ?? $r['id']) ?>
                            </td>
                            <!-- 2 نوع التجديد -->
                            <td><?= htmlspecialchars($r['renewal_type'] ?? '—') ?></td>
                            <!-- 3 رقم العميل -->
                            <td><?= $r['client_id'] ?? '—' ?></td>
                            <!-- 4 اسم العميل -->
                            <td class="wrap-cell"><strong><?= htmlspecialchars($r['client_name'] ?? '—') ?></strong></td>
                            <!-- 5 العمر -->
                            <td style="text-align:center"><?= htmlspecialchars($r['client_age'] ?? '—') ?></td>
                            <!-- 6 الهاتف -->
                            <td><?= htmlspecialchars($r['client_phone'] ?? '—') ?></td>
                            <!-- 7 وقت التمرين — AM/PM -->
                            <td><?= htmlspecialchars(formatAmPm($r['exercise_time'] ?? '')) ?></td>
                            <!-- 8 المستوى -->
                            <td style="text-align:center"><?= htmlspecialchars($r['level'] ?? '—') ?></td>
                            <!-- 9 الكابتن -->
                            <td><?= htmlspecialchars($r['captain_name'] ?? '—') ?></td>
                            <!-- 10 سعر الخطة -->
                            <td style="font-weight:700"><?= number_format((float)($r['plan_price'] ?? 0)) ?></td>
                            <!-- 11 المدفوع -->
                            <td style="color:#4ade80;font-weight:700"><?= number_format((float)($r['total_paid'] ?? 0)) ?></td>
                            <!-- 12 أول تمرين -->
                            <td><?= htmlspecialchars($r['first_session'] ?? '—') ?></td>
                            <!-- 13 آخر تمرين -->
                            <td><?= htmlspecialchars($r['last_session'] ?? '—') ?></td>
                            <!-- 14 المنشئ (admin only) -->
                            <?php if ($isAdmin): ?>
                            <td><?= htmlspecialchars($r['creator_name'] ?? '—') ?></td>
                            <?php endif; ?>
                            <!-- 15 الحالة -->
                            <td><span class="badge <?= $cls ?>"><?= $statusLabel ?></span></td>
                            <!-- 16 مُسترد -->
                            <td>
                                <?php if($r['has_refund'] == '0') {
                                    $hasRefund = false;
                                } else {
                                    $hasRefund = true;
                                }
                                ?>
                                <?php if ($hasRefund): ?>
                                    <span class="badge-refund">🔴 مُسترد</span>
                                <?php else: ?>
                                    <span style="color:rgba(255,255,255,.35);font-size:.8rem">-</span>
                                <?php endif; ?>
                            </td>
                            <!-- 17 التعديلات -->
                            <td>
                                <?php if ($r['audit_count'] > 0): ?>
                                    <span class="badge-updated" title="تعديلات">✏️ <?= $r['audit_count'] ?></span>
                                <?php endif; ?>
                                <?php if ($r['transaction_count'] > 0): ?>
                                    <span class="badge-updated" title="معاملات">💳 <?= $r['transaction_count'] ?></span>
                                <?php endif; ?>
                                <?php if (!$hasActivity): ?>
                                    <span style="color:rgba(255,255,255,.35);font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- 18 الإجراءات -->
                            <td>
                                <div class="td-actions">
                                    <a href="<?= APP_URL ?>/receipt/show?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                    <a href="<?= APP_URL ?>/receipt/preview?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">تفاصيل</a>
                                    <a href="<?= APP_URL ?>/receipt/edit?id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                    <form method="POST"
                                          action="<?= APP_URL ?>/receipt/delete?id=<?= $r['id'] ?>"
                                          style="display:inline"
                                          onsubmit="event.preventDefault(); showDeleteModal(this);">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ────────────────────────────────────────────── -->
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
                    if ($i === 1 || $i === $lastPage || abs($i - $page) <= $window) {
                        $shown[] = $i;
                    }
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
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const IS_ADMIN   = <?= json_encode($isAdmin) ?>;
    const PER_PAGE   = <?= (int) ($perPage ?? 25) ?>;

    let livePage     = 1;
    let liveTotalNow = <?= (int) $total ?>;
    let liveLastPage = <?= (int) $lastPage ?>;

    // ── Helpers ──────────────────────────────────────────────────────────
    const statusMap = {
        completed:     ['badge-success', 'مكتمل'],
        not_completed: ['badge-danger',  'غير مكتمل'],
        pending:       ['badge-warning', 'معلّق'],
    };

    function esc(str) {
        if (str == null) return '—';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmt(n) {
        const num = parseFloat(n);
        if (isNaN(num)) return '—';
        return num.toLocaleString('ar-EG');
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

    function buildRow(r) {
        const [cls, statusLabel] = statusMap[r.receipt_status] ?? ['badge-secondary', esc(r.receipt_status)];
        const hasActivity = Number(r.audit_count) > 0 || Number(r.transaction_count) > 0;
        const hasRefund   = parseFloat(r.total_refunded ?? 0) > 0;

        const activityHtml = [
            Number(r.audit_count)       > 0 ? `<span class="badge-updated" title="تعديلات">✏️ ${esc(r.audit_count)}</span>`       : '',
            Number(r.transaction_count) > 0 ? `<span class="badge-updated" title="معاملات">💳 ${esc(r.transaction_count)}</span>` : '',
            !hasActivity                    ? `<span style="color:rgba(255,255,255,.35);font-size:.8rem">—</span>`                 : '',
        ].join('');

        const refundCell = hasRefund
            ? `<span class="badge-refund">🔴 مُسترد</span>`
            : `<span style="color:rgba(255,255,255,.35);font-size:.8rem">—</span>`;

        const creatorCell = IS_ADMIN
            ? `<td>${esc(r.creator_name)}</td>`
            : '';

        const receiptRef = r.receipt_ref ? esc(r.receipt_ref) : esc(r.id);

        return `<tr>
            <td style="color:#fff;font-size:.95rem;font-weight:600">${receiptRef}</td>
            <td>${esc(r.renewal_type)}</td>
            <td>${esc(r.client_id)}</td>
            <td class="wrap-cell"><strong>${esc(r.client_name)}</strong></td>
            <td style="text-align:center">${esc(r.client_age)}</td>
            <td>${esc(r.client_phone)}</td>
            <td>${fmtTime(r.exercise_time)}</td>
            <td style="text-align:center">${esc(r.level)}</td>
            <td>${esc(r.captain_name)}</td>
            <td style="font-weight:700">${fmt(r.plan_price)}</td>
            <td style="color:#4ade80;font-weight:700">${fmt(r.total_paid)}</td>
            <td>${esc(r.first_session)}</td>
            <td>${esc(r.last_session)}</td>
            ${creatorCell}
            <td><span class="badge ${cls}">${statusLabel}</span></td>
            <td>${refundCell}</td>
            <td>${activityHtml}</td>
            <td>
                <div class="td-actions">
                    <a href="${BASE_URL}/receipt/show?id=${esc(r.id)}" class="btn btn-sm btn-secondary">عرض</a>
                    <a href="${BASE_URL}/receipt/preview?id=${esc(r.id)}" class="btn btn-sm btn-secondary">تفاصيل</a>
                    <a href="${BASE_URL}/receipt/edit?id=${esc(r.id)}" class="btn btn-sm btn-warning">تعديل</a>
                    <form method="POST" action="${BASE_URL}/receipt/delete?id=${esc(r.id)}" style="display:inline"
                          onsubmit="event.preventDefault(); showDeleteModal(this);">
                        <input type="hidden" name="csrf_token" value="${esc(CSRF_TOKEN)}">
                        <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                    </form>
                </div>
            </td>
        </tr>`;
    }

    // ── Collect filter params from form ───────────────────────────────────
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

    // ── Pagination renderer ───────────────────────────────────────────────
    function buildPagination(page, lastPage, total, perPage) {
        document.getElementById('livePagInfo')?.remove();
        document.getElementById('livePagNav')?.remove();

        if (lastPage <= 1) return;

        const from = (page - 1) * perPage + 1;
        const to   = Math.min(page * perPage, total);
        const info = document.createElement('p');
        info.className   = 'pag-info';
        info.id          = 'livePagInfo';
        info.textContent = `عرض ${from.toLocaleString('ar-EG')}–${to.toLocaleString('ar-EG')} من ${total.toLocaleString('ar-EG')}`;
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

    // ── Show / hide empty state ───────────────────────────────────────────
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

    // ── Main fetch ────────────────────────────────────────────────────────
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

            if (countBig) countBig.textContent = Number(json.total).toLocaleString('ar-EG');

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
                        <th>رقم الإيصال</th>
                        <th>نوع التجديد</th>
                        <th>رقم العميل</th>
                        <th>اسم العميل</th>
                        <th>العمر</th>
                        <th>الهاتف</th>
                        <th>وقت التمرين</th>
                        <th>المستوى</th>
                        <th>الكابتن</th>
                        <th>سعر الخطة</th>
                        <th>المدفوع</th>
                        <th>أول تمرين</th>
                        <th>آخر تمرين</th>
                        ${creatorTh}
                        <th>الحالة</th>
                        <th>مُسترد</th>
                        <th>التعديلات</th>
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

    // ── Restore form fields from URL params ───────────────────────────────
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

    // ── Browser back / forward ────────────────────────────────────────────
    window.addEventListener('popstate', function (e) {
        const urlParams    = new URLSearchParams(window.location.search);
        const restoredPage = e.state?.page ?? parseInt(urlParams.get('page') ?? '1', 10);
        restoreFormFromUrl(urlParams);
        doSearch(restoredPage);
    });

    // ── Creator select: show/hide created-only checkbox ───────────────────
    const creatorSelect = document.getElementById('creatorSelect');
    const creatorWrap   = document.getElementById('creatorOnlyWrap');
    const creatorCb     = document.getElementById('creatorOnlyCb');

    if (creatorSelect && creatorWrap && creatorCb) {
        creatorSelect.addEventListener('change', function () {
            const hasValue = this.value !== '';
            creatorWrap.style.display = hasValue ? 'flex' : 'none';
            if (!hasValue) {
                creatorCb.checked = false;
            }
            creatorCb.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // ── Tag-checkbox interactivity ────────────────────────────────────────
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

    // ── Clear-all buttons ─────────────────────────────────────────────────
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

    // ── Text search: 300ms debounce ───────────────────────────────────────
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => doSearch(1), 300);
    });

    // ── Dates, dropdowns, plain checkboxes: 150ms debounce ───────────────
    document.getElementById('filterForm')
        ?.querySelectorAll('select, input[type="date"], input[type="checkbox"]')
        .forEach(el => {
            if (el.closest('.tag-check-group')) return;
            el.addEventListener('change', () => {
                clearTimeout(timer);
                timer = setTimeout(() => doSearch(1), 150);
            });
        });

})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
