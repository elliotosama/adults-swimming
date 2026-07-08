<?php // views/admin/captains/index.php
require ROOT . '/views/includes/layout_top.php';
?>

<!-- Confirm Modal (used for delete confirmation) -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">حذف الكابتن</h2>
        <p id="confirmModalText" style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من حذف هذا الكابتن؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button id="confirmCancelBtn" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;transition:background .15s">حذف</button>
        </div>
    </div>
</div>

<!-- Captain Modal (create / edit / show — loaded via AJAX) -->
<div id="captainModal" class="captain-modal-overlay">
    <div class="captain-modal-panel">
        <div style="display:flex;justify-content:flex-end">
            <button type="button" id="captainModalCloseX" class="js-modal-close"
                    style="background:transparent;border:none;font-size:1.3rem;cursor:pointer;color:var(--text);line-height:1">✕</button>
        </div>
        <div id="captainModalBody"></div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }
#captainTableWrap  { transition: opacity .15s ease; }

.captain-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9998;
    background: rgba(0,0,0,.5);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.captain-modal-overlay.open { display: flex; }
.captain-modal-panel {
    background: var(--surface, #252736);
    border: 1px solid var(--border);
    border-radius: 16px;
    width: 92%;
    max-width: 760px;
    max-height: 90vh;
    overflow-y: auto;
    margin-top: 60px;
    padding: .5rem 1.5rem 1.5rem;
    animation: modalIn .2s cubic-bezier(.34,1.56,.64,1);
}
</style>

<div class="page-header" style="flex-direction: row; margin-top: 20px;">
    <div>
        <h1 class="page-title">
            🧑‍✈️ الكباتن
            <span id="captainCountBadge" class="badge" style="background:#00b4d815;color:var(--accent);border:1px solid #00b4d830;font-size:.75rem;vertical-align:middle;margin-inline-start:.5rem">
                <?= count($captains) ?> كابتن
            </span>
        </h1>
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

<!-- Hidden CSRF token for JS-rendered delete/create/edit requests -->
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
                                        <button type="button"
                                                class="btn btn-sm btn-danger js-delete-captain"
                                                data-id="<?= $c['id'] ?>"
                                                data-name="<?= htmlspecialchars($c['captain_name']) ?>">حذف</button>
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
    const countBadge       = document.getElementById('captainCountBadge');

    const confirmModal     = document.getElementById('confirmModal');
    const confirmModalText = document.getElementById('confirmModalText');
    const confirmBtn       = document.getElementById('confirmBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');

    const captainModal     = document.getElementById('captainModal');
    const captainModalBody = document.getElementById('captainModalBody');

    let debounceTimer    = null;
    let _pendingDeleteId = null;

    // ── XSS guard ────────────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Toast ────────────────────────────────────────────────────────────
    function showToast(message, type) {
        type = type || 'success';
        const el = document.createElement('div');
        el.className = 'alert alert-' + type;
        el.style.position  = 'fixed';
        el.style.top       = '20px';
        el.style.left      = '50%';
        el.style.transform = 'translateX(-50%)';
        el.style.zIndex    = '10001';
        el.style.boxShadow = '0 8px 24px rgba(0,0,0,.25)';
        el.textContent = (type === 'success' ? '✅ ' : '⚠️ ') + message;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3500);
    }

    function updateCountBadge(n) {
        countBadge.textContent = `${n} كابتن`;
    }

    // ── Build action buttons ─────────────────────────────────────────────
    function actionButtons(c) {
        const deleteBtn = isAdmin
            ? `<button type="button" class="btn btn-sm btn-danger js-delete-captain" data-id="${c.id}" data-name="${esc(c.captain_name)}">حذف</button>`
            : '';

        return `
            <div class="td-actions">
                <a href="${APP_URL}/admin/captains/show?id=${c.id}" class="btn btn-sm btn-secondary">عرض</a>
                <a href="${APP_URL}/admin/captains/edit?id=${c.id}" class="btn btn-sm btn-warning">تعديل</a>
                ${deleteBtn}
            </div>`;
    }

    // ── Render captains array into #captainTableWrap ─────────────────────
    function renderTable(captains) {
        updateCountBadge(captains.length);

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

    branchSelect.addEventListener('change', fetchCaptains);
    visibilitySelect.addEventListener('change', fetchCaptains);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchCaptains();
    });

    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value      = '';
        branchSelect.value     = '';
        visibilitySelect.value = '';
        fetchCaptains();
    });

    // ══════════════════════════════════════════════════════════════════
    // Captain modal (create / edit / show)
    // ══════════════════════════════════════════════════════════════════

    function openCaptainModal(url) {
        captainModalBody.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--muted)">جارِ التحميل...</div>';
        captainModal.classList.add('open');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => { captainModalBody.innerHTML = html; })
            .catch(() => {
                captainModalBody.innerHTML = '<div style="padding:2rem;color:var(--danger)">تعذر تحميل البيانات.</div>';
            });
    }

    function closeCaptainModal() {
        captainModal.classList.remove('open');
        captainModalBody.innerHTML = '';
    }

    function showFormErrors(formEl, errors) {
        let box = formEl.querySelector('.js-form-errors');
        if (!box) {
            box = document.createElement('div');
            box.className = 'alert alert-error js-form-errors';
            box.style.marginBottom = '1rem';
            formEl.insertBefore(box, formEl.firstChild);
        }
        box.innerHTML = '⚠️ ' + errors.map(esc).join('<br>');
    }

    // Intercept clicks anywhere (table rows, modal content, "+ إضافة" button)
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (link) {
            const href = link.getAttribute('href') || '';
            if (href.includes('/admin/captains/create') ||
                href.includes('/admin/captains/edit')   ||
                href.includes('/admin/captains/show')) {
                e.preventDefault();
                openCaptainModal(link.href);
                return;
            }
        }

        if (e.target.closest('.js-modal-close')) {
            e.preventDefault();
            closeCaptainModal();
            return;
        }

        const delBtn = e.target.closest('.js-delete-captain');
        if (delBtn) {
            e.preventDefault();
            _pendingDeleteId = delBtn.dataset.id;
            const name = delBtn.dataset.name || '';
            confirmModalText.innerHTML = name
                ? `هل أنت متأكد من حذف الكابتن "${esc(name)}"؟<br>يمكنك إعادة تفعيله لاحقاً.`
                : 'هل أنت متأكد من حذف هذا الكابتن؟<br>يمكنك إعادة تفعيله لاحقاً.';
            confirmModal.classList.add('open');
        }
    });

    // Click outside the captain modal panel closes it
    captainModal.addEventListener('click', function (e) {
        if (e.target === captainModal) closeCaptainModal();
    });

    // Intercept the create/edit form submit wherever it's injected
    document.addEventListener('submit', function (e) {
        const formEl = e.target;
        if (!formEl.classList || !formEl.classList.contains('js-captain-form')) return;

        e.preventDefault();
        const submitBtn = formEl.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch(formEl.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(formEl),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeCaptainModal();
                fetchCaptains();
                showToast(data.message || 'تم الحفظ بنجاح.', 'success');
            } else {
                showFormErrors(formEl, data.errors || [data.message].filter(Boolean));
                if (submitBtn) submitBtn.disabled = false;
            }
        })
        .catch(() => {
            showFormErrors(formEl, ['حدث خطأ في الاتصال بالخادم.']);
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    // ── Delete confirm modal buttons ──────────────────────────────────────
    confirmCancelBtn.addEventListener('click', function () {
        confirmModal.classList.remove('open');
        _pendingDeleteId = null;
    });

    confirmModal.addEventListener('click', function (e) {
        if (e.target === this) {
            confirmModal.classList.remove('open');
            _pendingDeleteId = null;
        }
    });

    confirmBtn.addEventListener('click', function () {
        if (!_pendingDeleteId) {
            confirmModal.classList.remove('open');
            return;
        }
        const id   = _pendingDeleteId;
        const csrf = document.getElementById('globalCsrfToken').value;

        fetch(`${APP_URL}/admin/captains/delete?id=${id}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrf)}`,
        })
        .then(r => r.json())
        .then(data => {
            confirmModal.classList.remove('open');
            _pendingDeleteId = null;
            if (data.success) {
                closeCaptainModal();
                fetchCaptains();
                showToast(data.message || 'تم الحذف بنجاح.', 'success');
            } else {
                showToast(data.message || 'حدث خطأ أثناء الحذف.', 'error');
            }
        })
        .catch(() => {
            confirmModal.classList.remove('open');
            _pendingDeleteId = null;
            showToast('حدث خطأ أثناء الحذف.', 'error');
        });
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>