<?php // views/admin/captains/index.php
require ROOT . '/views/includes/layout_top.php';
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--card,#111d2b);border-radius:16px;border:0.5px solid var(--border);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.45);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);font-family:'Cairo',sans-serif;">
        <div style="width:52px;height:52px;border-radius:50%;background:#e05c5c20;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:var(--text,#e0eaf4);font-family:'Cairo',sans-serif;">حذف الكابتن</h2>
        <p style="text-align:center;color:var(--muted,#5a7a96);font-size:.9rem;margin:0 0 1.75rem;line-height:1.6;font-family:'Cairo',sans-serif;">هل أنت متأكد من حذف هذا الكابتن؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--border);background:transparent;cursor:pointer;font-size:.9rem;color:var(--text,#e0eaf4);font-family:'Cairo',sans-serif;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;font-family:'Cairo',sans-serif;transition:background .15s">حذف</button>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }
#captainTableWrap  { transition: opacity .15s ease; }
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

<div class="page-header">
    <div>
        <h1 class="page-title">🧑‍✈️ الكباتن</h1>
        <p class="breadcrumb">لوحة التحكم · الكباتن</p>
    </div>
    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
    <a href="<?= APP_URL ?>/admin/captains/create" class="btn btn-primary">
        + إضافة كابتن جديد
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
<form id="filterForm" method="GET" action="<?= APP_URL ?>/admin/captains">
    <div class="filter-bar">

        <div class="form-group">
            <label class="form-label">🔍 البحث</label>
            <input type="text"
                   id="filterSearch"
                   name="search"
                   class="form-control"
                   placeholder="الاسم أو رقم الهاتف..."
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                   autocomplete="off">
        </div>

        <div class="form-group">
            <label class="form-label">الفرع</label>
            <div class="form-select-wrap">
                <select id="filterBranch" name="branch_id" class="form-control">
                    <option value="">جميع الفروع</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= ((int)($filters['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['branch_name']) ?>
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
                    <option value="visible" <?= ($filters['visible'] ?? '') === 'visible' ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="hidden"  <?= ($filters['visible'] ?? '') === 'hidden'  ? 'selected' : '' ?>>معطّل ❌</option>
                </select>
            </div>
        </div>

        <div class="filter-bar__actions">
            <button type="submit" class="btn btn-primary">تطبيق</button>
            <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary" id="clearFiltersBtn">مسح</a>
        </div>

    </div>
</form>

<!-- Table -->
<div class="card">
    <div id="captainTableWrap">
        <?php if (empty($captains)): ?>
            <div class="empty-state">
                <div class="empty-icon">🧑‍✈️</div>
                <p>لا يوجد كباتن تطابق البحث.</p>
                <?php if (!empty($filters['search']) || !empty($filters['branch_id']) || !empty($filters['visible'])): ?>
                    <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary" style="margin-top:1rem">إعادة ضبط الفلاتر</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الكابتن</th>
                            <th>رقم الهاتف</th>
                            <th>الحالة</th>
                            <th>الفروع</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($captains as $c): ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.82rem"><?= $c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['captain_name']) ?></strong></td>
                                <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['phone_number'] ?? '—') ?></td>
                                <td>
                                    <?php if ($c['visible']): ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">معطّل</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem;color:var(--muted)">
                                    <?= $c['branch_names'] ? htmlspecialchars($c['branch_names']) : '—' ?>
                                </td>
                                <td style="color:var(--muted);font-size:.85rem"><?= htmlspecialchars($c['created_at'] ?? '—') ?></td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= APP_URL ?>/admin/captains/show?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                        <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                        <form method="POST"
                                              action="<?= APP_URL ?>/admin/captains/delete?id=<?= $c['id'] ?>"
                                              style="display:inline"
                                              onsubmit="event.preventDefault(); showDeleteModal(this);">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
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
                عرض <?= count($captains) ?> كابتن
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const AJAX_URL = '<?= APP_URL ?>/admin/captains/search';
    const APP_URL  = '<?= APP_URL ?>';
    const isAdmin  = <?= json_encode($_SESSION['user']['role'] === 'admin') ?>;

    const form             = document.getElementById('filterForm');
    const wrap             = document.getElementById('captainTableWrap');
    const searchInput      = document.getElementById('filterSearch');
    const branchSelect     = document.getElementById('filterBranch');
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

    // ── Build action buttons ─────────────────────────────────────────────
    function actionButtons(c) {
        const csrf = esc(document.getElementById('globalCsrfToken').value);

        const deleteBtn = isAdmin ? `
            <form method="POST"
                  action="${APP_URL}/admin/captains/delete?id=${c.id}"
                  style="display:inline"
                  onsubmit="event.preventDefault(); showDeleteModal(this);">
                <input type="hidden" name="csrf_token" value="${csrf}">
                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
            </form>` : '';

        return `
            <div class="td-actions">
                <a href="${APP_URL}/admin/captains/show?id=${c.id}" class="btn btn-sm btn-secondary">عرض</a>
                <a href="${APP_URL}/admin/captains/edit?id=${c.id}" class="btn btn-sm btn-warning">تعديل</a>
                ${deleteBtn}
            </div>`;
    }

    // ── Render captains array into #captainTableWrap ─────────────────────
    function renderTable(captains) {
        if (!captains.length) {
            wrap.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">🧑‍✈️</div>
                    <p>لا يوجد كباتن تطابق البحث.</p>
                </div>`;
            return;
        }

        const rows = captains.map(c => `
            <tr>
                <td style="color:var(--muted);font-size:.82rem">${esc(c.id)}</td>
                <td><strong>${esc(c.captain_name)}</strong></td>
                <td style="font-size:.85rem;color:var(--muted)">${esc(c.phone_number || '—')}</td>
                <td>
                    ${c.visible == 1
                        ? '<span class="badge badge-success">نشط</span>'
                        : '<span class="badge badge-danger">معطّل</span>'}
                </td>
                <td style="font-size:.82rem;color:var(--muted)">${esc(c.branch_names || '—')}</td>
                <td style="color:var(--muted);font-size:.85rem">${esc(c.created_at || '—')}</td>
                <td>${actionButtons(c)}</td>
            </tr>`).join('');

        wrap.innerHTML = `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الكابتن</th>
                            <th>رقم الهاتف</th>
                            <th>الحالة</th>
                            <th>الفروع</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض ${captains.length} كابتن
            </div>`;
    }

    // ── Fetch from AJAX endpoint ─────────────────────────────────────────
    function fetchCaptains() {
        const params = new URLSearchParams({
            search:     searchInput.value.trim(),
            branch_id:  branchSelect.value,
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

    // ── Live search — debounced 300 ms ───────────────────────────────────
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchCaptains, 300);
    });

    // ── Instant on select change ─────────────────────────────────────────
    branchSelect.addEventListener('change', fetchCaptains);
    visibilitySelect.addEventListener('change', fetchCaptains);

    // ── Intercept form submit ────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchCaptains();
    });

    // ── Clear button ─────────────────────────────────────────────────────
    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value      = '';
        branchSelect.value     = '';
        visibilitySelect.value = '';
        fetchCaptains();
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>