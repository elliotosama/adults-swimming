<?php // views/admin/branches/index.php
require ROOT . '/views/includes/layout_top.php';
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل الفرع</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا الفرع؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;transition:background .15s">تعطيل</button>
        </div>
    </div>
</div>

<style>
/* ── Match the gym theme: dark bg, surface, accent, Cairo bold white text ── */
:root {
    --bg:      #1E1E2D;
    --surface: #252736;
    --border:  #3C3F58;
    --accent:  #007ACC;
    --gold:    #D19A66;
    --success: #98C379;
    --danger:  #E06C75;
    --text:    #FFFFFF;
    --muted:   #ffffff;
}
html, body, .page, .page--full {
    background: var(--bg) !important;
}
body {
    font-family: 'Cairo', sans-serif;
    font-size: 16px;
    font-weight: bold;
    color: var(--text);
}
.card,
.filter-bar {
    background: var(--surface) !important;
    color: var(--text) !important;
    border-color: var(--border) !important;
}

@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }

#branchTableWrap {
    transition: opacity .15s ease;
}
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



<div class="page-header" style="flex-direction: row; margin-top: 20px;">
    <div>
        <h1 class="page-title">🏢 الفروع</h1>
        <p class="breadcrumb">لوحة التحكم · الفروع</p>
    </div>
    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
    <a href="<?= APP_URL ?>/admin/branch/create" class="btn btn-primary">
        + إضافة فرع جديد
    </a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Hidden CSRF token for JS-rendered delete forms -->
<input type="hidden" id="globalCsrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

<!-- Filters -->
<form id="filterForm" method="GET" action="<?= APP_URL ?>/admin/branches">
    <div class="filter-bar">
        <div class="form-group">
            <label class="form-label">🔍 البحث بالاسم</label>
            <input type="text"
                   id="filterSearch"
                   name="search"
                   class="form-control"
                   placeholder="اسم الفرع..."
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                   autocomplete="off">
        </div>

        <div class="form-group">
            <label class="form-label">الدولة</label>
            <div class="form-select-wrap">
                <select id="filterCountry" name="country_id" class="form-control">
                    <option value="">جميع الدول</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= ($filters['country_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['country']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">الحالة</label>
            <div class="form-select-wrap">
                <select id="filterVisibility" name="visibility" class="form-control">
                    <option value="">الكل</option>
                    <option value="visible" <?= ($filters['visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="hidden"  <?= ($filters['visibility'] ?? '') === 'hidden'  ? 'selected' : '' ?>>معطّل ❌</option>
                </select>
            </div>
        </div>

        <div class="filter-bar__actions">
            <button type="submit" class="btn btn-primary">تطبيق</button>
            <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary" id="clearFiltersBtn">مسح</a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="card">
    <div id="branchTableWrap">
        <?php if (empty($branches)): ?>
            <div class="empty-state">
                <div class="empty-icon">🏢</div>
                <p>لا توجد فروع تطابق البحث.</p>
                <?php if (!empty($filters['search']) || !empty($filters['country_id']) || !empty($filters['visibility'])): ?>
                    <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary" style="margin-top:1rem">إعادة ضبط الفلاتر</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الفرع</th>
                            <th>الدولة</th>
                            <th>ايام العمل 1</th>
                            <th>ايام العمل 2</th>
                            <th>ايام العمل 3</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $b): ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.82rem"><?= $b['id'] ?></td>
                                <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                                <td><?= htmlspecialchars($b['country'] ?? '—') ?></td>
                                <td style="font-size:.8rem;color:var(--muted)">
                                    <?php if (!empty($b['working_days1'])): ?>
                                        <?php foreach (explode(',', $b['working_days1']) as $d): ?>
                                            <span class="badge" style="background:#00b4d815;color:var(--accent);border:1px solid #00b4d830;margin:1px">
                                                <?= htmlspecialchars(trim($d)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td style="font-size:.8rem;color:var(--muted)">
                                    <?php if (!empty($b['working_days2'])): ?>
                                        <?php foreach (explode(',', $b['working_days2']) as $d): ?>
                                            <span class="badge" style="background:#f4a62315;color:var(--gold);border:1px solid #f4a62330;margin:1px">
                                                <?= htmlspecialchars(trim($d)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td style="font-size:.8rem;color:var(--muted)">
                                    <?php if (!empty($b['working_days3'])): ?>
                                        <?php foreach (explode(',', $b['working_days3']) as $d): ?>
                                            <span class="badge" style="background:#34c78915;color:var(--success);border:1px solid #34c78930;margin:1px">
                                                <?= htmlspecialchars(trim($d)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['visible']): ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">معطّل</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--muted);font-size:.85rem">
                                    <?= htmlspecialchars($b['created_at'] ?? '—') ?>
                                </td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= APP_URL ?>/admin/branch/show?id=<?= $b['id'] ?>"
                                           class="btn btn-sm btn-secondary">عرض</a>
                                        <a href="<?= APP_URL ?>/admin/branch/edit?id=<?= $b['id'] ?>"
                                           class="btn btn-sm btn-warning">تعديل</a>
                                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                        <form method="POST"
                                              action="<?= APP_URL ?>/admin/branch/delete?id=<?= $b['id'] ?>"
                                              style="display:inline"
                                              onsubmit="event.preventDefault(); showDeleteModal(this);">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض <?= count($branches) ?> فرع
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const AJAX_URL = '<?= APP_URL ?>/admin/branches/search';
    const APP_URL  = '<?= APP_URL ?>';
    const isAdmin  = <?= json_encode($_SESSION['user']['role'] === 'admin') ?>;

    const form             = document.getElementById('filterForm');
    const wrap             = document.getElementById('branchTableWrap');
    const searchInput      = document.getElementById('filterSearch');
    const countrySelect    = document.getElementById('filterCountry');
    const visibilitySelect = document.getElementById('filterVisibility');
    const clearBtn         = document.getElementById('clearFiltersBtn');

    let debounceTimer = null;

    // ── XSS guard ────────────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Day-badge colour sets ────────────────────────────────────────────
    const badgeStyles = [
        { bg: '#00b4d815', color: 'var(--accent)',   border: '#00b4d830' },
        { bg: '#f4a62315', color: 'var(--gold)',     border: '#f4a62330' },
        { bg: '#34c78915', color: 'var(--success)',  border: '#34c78930' },
    ];

    function dayBadges(csv, idx) {
        if (!csv) return '—';
        const s = badgeStyles[idx];
        return csv.split(',').map(d =>
            `<span class="badge" style="background:${s.bg};color:${s.color};border:1px solid ${s.border};margin:1px">${esc(d.trim())}</span>`
        ).join('');
    }

    // ── Build action buttons for each row ────────────────────────────────
    function actionButtons(b) {
        const csrf = esc(document.getElementById('globalCsrfToken').value);

        const deleteBtn = isAdmin ? `
            <form method="POST"
                  action="${APP_URL}/admin/branch/delete?id=${b.id}"
                  style="display:inline"
                  onsubmit="event.preventDefault(); showDeleteModal(this);">
                <input type="hidden" name="csrf_token" value="${csrf}">
                <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
            </form>` : '';

        return `
            <div class="td-actions">
                <a href="${APP_URL}/admin/branch/show?id=${b.id}" class="btn btn-sm btn-secondary">عرض</a>
                <a href="${APP_URL}/admin/branch/edit?id=${b.id}" class="btn btn-sm btn-warning">تعديل</a>
                ${deleteBtn}
            </div>`;
    }

    // ── Render branches array into #branchTableWrap ───────────────────────
    function renderTable(branches) {
        if (!branches.length) {
            wrap.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">🏢</div>
                    <p>لا توجد فروع تطابق البحث.</p>
                </div>`;
            return;
        }

        const rows = branches.map(b => `
            <tr>
                <td style="color:var(--muted);font-size:.82rem">${esc(b.id)}</td>
                <td><strong>${esc(b.branch_name)}</strong></td>
                <td>${esc(b.country ?? '—')}</td>
                <td style="font-size:.8rem;color:var(--muted)">${dayBadges(b.working_days1, 0)}</td>
                <td style="font-size:.8rem;color:var(--muted)">${dayBadges(b.working_days2, 1)}</td>
                <td style="font-size:.8rem;color:var(--muted)">${dayBadges(b.working_days3, 2)}</td>
                <td>
                    ${b.visible == 1
                        ? '<span class="badge badge-success">نشط</span>'
                        : '<span class="badge badge-danger">معطّل</span>'}
                </td>
                <td style="color:var(--muted);font-size:.85rem">${esc(b.created_at ?? '—')}</td>
                <td>${actionButtons(b)}</td>
            </tr>`).join('');

        wrap.innerHTML = `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الفرع</th>
                            <th>الدولة</th>
                            <th>ايام العمل 1</th>
                            <th>ايام العمل 2</th>
                            <th>ايام العمل 3</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض ${branches.length} فرع
            </div>`;
    }

    // ── Fetch branches from AJAX endpoint ────────────────────────────────
    function fetchBranches() {
        const params = new URLSearchParams({
            search:     searchInput.value.trim(),
            country_id: countrySelect.value,
            visibility: visibilitySelect.value,
        });

        wrap.style.opacity = '0.45';

        fetch(`${AJAX_URL}?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Server error');
            return r.json();
        })
        .then(data => {
            renderTable(data);
            wrap.style.opacity = '1';
        })
        .catch(() => {
            wrap.style.opacity = '1';
        });
    }

    // ── Live search on text input — debounced 300 ms ─────────────────────
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchBranches, 300);
    });

    // ── Instant update on select change ─────────────────────────────────
    countrySelect.addEventListener('change', fetchBranches);
    visibilitySelect.addEventListener('change', fetchBranches);

    // ── Intercept form submit (keeps normal fallback working too) ─────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchBranches();
    });

    // ── Clear button: reset inputs then fetch ────────────────────────────
    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value      = '';
        countrySelect.value    = '';
        visibilitySelect.value = '';
        fetchBranches();
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>