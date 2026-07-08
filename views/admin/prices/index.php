<?php // views/admin/prices/index.php
require ROOT . '/views/includes/layout_top.php';
?>

<!-- Custom Confirm Modal (delete) -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">حذف السعر</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من حذف هذا السعر؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;transition:background .15s">حذف</button>
        </div>
    </div>
</div>

<!-- AJAX Modal (show / create / edit) -->
<div id="ajaxModalOverlay" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1.5rem;">
    <div id="ajaxModalBox" style="background:var(--color-background-primary,#12161c);border-radius:16px;border:0.5px solid var(--color-border-tertiary,var(--border));max-width:640px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.35);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div id="ajaxModalContent" style="padding:1.4rem;">
            <div class="modal-loading" style="text-align:center;padding:2rem;color:var(--muted)">⏳ جارٍ التحميل...</div>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open, #ajaxModalOverlay.open { display:flex; }
#priceTableWrap     { transition: opacity .15s ease; }

.price-amount {
    font-weight: 700;
    font-size: .95rem;
    color: var(--gold);
    letter-spacing: .02em;
}
.sessions-badge {
    background: #00b4d820;
    color: var(--accent);
    border: 1px solid #00b4d840;
    border-radius: 6px;
    padding: 2px 10px;
    font-size: .8rem;
    font-weight: 600;
}
.desc-cell  { display: flex; align-items: center; gap: .75rem; }
.desc-icon  {
    width: 34px; height: 34px; border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--accent));
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.desc-info          { display: flex; flex-direction: column; }
.desc-info strong   { font-size: .9rem; }
.desc-info span     { font-size: .78rem; color: var(--muted); }

/* Modal header used by _form.php / show.php in AJAX mode */
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem; padding-bottom: .9rem; border-bottom: 1px solid var(--border);
}
.modal-title { font-size: 1.05rem; font-weight: 800; margin: 0; }
.modal-close {
    background: transparent; border: none; font-size: 1.4rem; line-height: 1;
    color: var(--muted); cursor: pointer; padding: .2rem .5rem; border-radius: 6px;
}
.modal-close:hover { background: rgba(255,255,255,.06); color: #fff; }
.toast-success {
    position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
    background: #1d8f4a; color: #fff; padding: .7rem 1.4rem; border-radius: 10px;
    font-size: .88rem; font-weight: 600; z-index: 10000; box-shadow: 0 8px 24px rgba(0,0,0,.3);
    animation: toastIn .2s ease;
}
@keyframes toastIn { from { opacity:0; transform:translate(-50%, 10px);} to { opacity:1; transform:translate(-50%,0);} }
</style>

<!-- Hidden CSRF for JS-rendered delete forms -->
<input type="hidden" id="globalCsrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

<div class="page-header" style="flex-direction: row; margin-top: 20px;">
    <div>
        <h1 class="page-title">💰 الأسعار

            <span id="priceCountBadge" class="badge" style="background:#00b4d815;color:var(--accent);border:1px solid #00b4d830;font-size:.75rem;vertical-align:middle;margin-inline-start:.5rem">
                <?= count($prices) ?> سعر
            </span>
        </h1>
        
        <p class="breadcrumb">لوحة التحكم · الأسعار</p>
    </div>
    <a href="<?= APP_URL ?>/admin/price/create" class="btn btn-primary" data-modal-url="<?= APP_URL ?>/admin/price/create">+ إضافة سعر</a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Filter Bar -->
<form id="filterForm" method="GET" action="<?= APP_URL ?>/admin/prices">
    <div class="filter-bar">

        <div class="form-group">
            <label class="form-label">🔍 البحث بالاسم</label>
            <input type="text"
                   id="filterSearch"
                   name="search"
                   class="form-control"
                   placeholder="اسم الخطة..."
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                   autocomplete="off">
        </div>

        <div class="form-group">
            <label class="form-label">الدولة</label>
            <div class="form-select-wrap">
                <select id="filterCountry" name="country_id" class="form-control">
                    <option value="">جميع الدول</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= $c['id'] ?>"
                            <?= (int)($filters['country_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['country']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">الحالة</label>
            <div class="form-select-wrap">
                <select id="filterVisible" name="visible" class="form-control">
                    <option value="">الكل</option>
                    <option value="1" <?= ($filters['visible'] ?? '') === '1' ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="0" <?= ($filters['visible'] ?? '') === '0' ? 'selected' : '' ?>>معطّل ❌</option>
                </select>
            </div>
        </div>

        <div class="filter-bar__actions">
            <button type="submit" class="btn btn-primary">تطبيق</button>
            <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary" id="clearFiltersBtn">مسح</a>
        </div>

    </div>
</form>

<!-- Table -->
<div class="card">
    <div id="priceTableWrap">
        <?php if (empty($prices)): ?>
            <div class="empty-state">
                <div class="empty-icon">💰</div>
                <p>لا توجد أسعار تطابق البحث.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الوصف</th>
                            <th>السعر</th>
                            <th>الدولة</th>
                            <th>عدد الحصص</th>
                            <th>الحالة</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $p): ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.82rem"><?= $p['id'] ?></td>
                                <td>
                                    <div class="desc-cell">
                                        <div class="desc-icon">🏷️</div>
                                        <div class="desc-info">
                                            <strong><?= htmlspecialchars($p['description'] ?? '—') ?></strong>
                                            <span>آخر تحديث: <?= $p['updated_at'] ? htmlspecialchars($p['updated_at']) : '—' ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="price-amount">
                                        <?= $p['price'] !== null ? number_format((float)$p['price'], 2) : '—' ?>
                                    </span>
                                </td>
                                <td style="color:var(--muted);font-size:.85rem">
                                    <?= htmlspecialchars($p['country_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <?php if ($p['number_of_sessions']): ?>
                                        <span class="sessions-badge"><?= (int)$p['number_of_sessions'] ?> الحصص</span>
                                    <?php else: ?>
                                        <span style="color:var(--muted)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['visible']): ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">معطّل</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--muted);font-size:.82rem">
                                    <?= $p['created_at'] ? htmlspecialchars($p['created_at']) : '—' ?>
                                </td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= APP_URL ?>/admin/price/show?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" data-modal-url="<?= APP_URL ?>/admin/price/show?id=<?= $p['id'] ?>">عرض</a>
                                        <a href="<?= APP_URL ?>/admin/price/edit?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning" data-modal-url="<?= APP_URL ?>/admin/price/edit?id=<?= $p['id'] ?>">تعديل</a>
                                        <form method="POST"
                                              action="<?= APP_URL ?>/admin/price/delete?id=<?= $p['id'] ?>"
                                              style="display:inline"
                                              data-ajax-delete
                                              onsubmit="event.preventDefault(); showDeleteModal(this);">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض <?= count($prices) ?> سعر
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const AJAX_URL = '<?= APP_URL ?>/admin/prices/search';
    const APP_URL  = '<?= APP_URL ?>';

    const form           = document.getElementById('filterForm');
    const wrap           = document.getElementById('priceTableWrap');
    const searchInput    = document.getElementById('filterSearch');
    const countrySelect  = document.getElementById('filterCountry');
    const visibleSelect  = document.getElementById('filterVisible');
    const clearBtn       = document.getElementById('clearFiltersBtn');

    let debounceTimer = null;

    // ── XSS guard ────────────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtPrice(val) {
        if (val === null || val === undefined || val === '') return '—';
        return parseFloat(val).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Action buttons ───────────────────────────────────────────────────
    function actionButtons(p) {
        const csrf = esc(document.getElementById('globalCsrfToken').value);
        const showUrl   = `${APP_URL}/admin/price/show?id=${p.id}`;
        const editUrl   = `${APP_URL}/admin/price/edit?id=${p.id}`;
        const deleteUrl = `${APP_URL}/admin/price/delete?id=${p.id}`;
        return `
            <div class="td-actions">
                <a href="${showUrl}" class="btn btn-sm btn-secondary" data-modal-url="${showUrl}">عرض</a>
                <a href="${editUrl}" class="btn btn-sm btn-warning" data-modal-url="${editUrl}">تعديل</a>
                <form method="POST"
                      action="${deleteUrl}"
                      style="display:inline"
                      data-ajax-delete
                      onsubmit="event.preventDefault(); showDeleteModal(this);">
                    <input type="hidden" name="csrf_token" value="${csrf}">
                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                </form>
            </div>`;
    }

    // ── Render prices array ──────────────────────────────────────────────
    function renderTable(prices) {
        updateCountBadge(prices.length)
        if (!prices.length) {
            wrap.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">💰</div>
                    <p>لا توجد أسعار تطابق البحث.</p>
                </div>`;
            return;
        }

        const rows = prices.map(p => `
            <tr>
                <td style="color:var(--muted);font-size:.82rem">${esc(p.id)}</td>
                <td>
                    <div class="desc-cell">
                        <div class="desc-icon">🏷️</div>
                        <div class="desc-info">
                            <strong>${esc(p.description || '—')}</strong>
                            <span>آخر تحديث: ${esc(p.updated_at || '—')}</span>
                        </div>
                    </div>
                </td>
                <td><span class="price-amount">${fmtPrice(p.price)}</span></td>
                <td style="color:var(--muted);font-size:.85rem">${esc(p.country_name || '—')}</td>
                <td>
                    ${p.number_of_sessions
                        ? `<span class="sessions-badge">${esc(p.number_of_sessions)} الحصص</span>`
                        : `<span style="color:var(--muted)">—</span>`}
                </td>
                <td>
                    ${p.visible == 1
                        ? '<span class="badge badge-success">نشط</span>'
                        : '<span class="badge badge-danger">معطّل</span>'}
                </td>
                <td style="color:var(--muted);font-size:.82rem">${esc(p.created_at || '—')}</td>
                <td>${actionButtons(p)}</td>
            </tr>`).join('');

        wrap.innerHTML = `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الوصف</th>
                            <th>السعر</th>
                            <th>الدولة</th>
                            <th>عدد الحصص</th>
                            <th>الحالة</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض ${prices.length} سعر
            </div>`;
    }

    // ── Fetch ────────────────────────────────────────────────────────────
    function fetchPrices() {
        const params = new URLSearchParams({
            search:     searchInput.value.trim(),
            country_id: countrySelect.value,
            visible:    visibleSelect.value,
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
    // Expose so the modal logic below can refresh the table after save/delete
    window.fetchPrices = fetchPrices;

    // ── Events ───────────────────────────────────────────────────────────
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchPrices, 300);
    });

    countrySelect.addEventListener('change', fetchPrices);
    visibleSelect.addEventListener('change', fetchPrices);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchPrices();
    });

    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value    = '';
        countrySelect.value  = '';
        visibleSelect.value  = '';
        fetchPrices();
    });
})();
</script>

<script>
// ══════════════════════════════════════════════════════════════════════
//  AJAX MODAL: show / create / edit — loads a full view fragment into
//  #ajaxModalContent and intercepts its form submit.
// ══════════════════════════════════════════════════════════════════════
(function () {

    const overlay = document.getElementById('ajaxModalOverlay');
    const content = document.getElementById('ajaxModalContent');

    function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'toast-success';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 2600);
    }

    function openModal(url) {
        content.innerHTML = '<div class="modal-loading" style="text-align:center;padding:2rem;color:var(--muted)">⏳ جارٍ التحميل...</div>';
        overlay.classList.add('open');
        overlay.style.display = 'flex';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => {
                if (!r.ok) throw new Error('load failed');
                return r.text();
            })
            .then(html => {
                content.innerHTML = html;
                bindAjaxForm();
            })
            .catch(() => {
                content.innerHTML = '<div style="text-align:center;padding:2rem;color:#e24b4a">⚠️ تعذر تحميل البيانات، حاول مرة أخرى.</div>';
            });
    }

    function closeAjaxModal() {
        overlay.classList.remove('open');
        overlay.style.display = 'none';
        content.innerHTML = '';
    }
    window.closeAjaxModal = closeAjaxModal;

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeAjaxModal();
    });

    // Bind the create/edit form loaded inside the modal for AJAX submit
    function bindAjaxForm() {
        const f = content.querySelector('form[data-ajax-form]');
        if (!f) return;

        f.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = f.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch(f.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(f)
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    closeAjaxModal();
                    showToast(data.message || '✅ تم الحفظ بنجاح');
                    if (window.fetchPrices) window.fetchPrices();
                } else {
                    // Server returns { success:false, html: '<rendered form with errors>' }
                    if (data && data.html) {
                        content.innerHTML = data.html;
                        bindAjaxForm();
                    } else if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            })
            .catch(() => {
                if (submitBtn) submitBtn.disabled = false;
                alert('حدث خطأ أثناء الحفظ، حاول مرة أخرى');
            });
        });
    }

    // Delegated click handler — works for both server-rendered rows
    // and rows re-rendered dynamically by the search script above.
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-modal-url]');
        if (!trigger) return;
        e.preventDefault();
        openModal(trigger.getAttribute('data-modal-url'));
    });

})();
</script>

<script>
// ══════════════════════════════════════════════════════════════════════
//  DELETE CONFIRM: shared by index rows AND the "show" view loaded
//  inside the AJAX modal. Submits via fetch and refreshes the table.
// ══════════════════════════════════════════════════════════════════════
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
    const form = _pendingForm;
    closeModal();
    if (!form) return;

    if (form.hasAttribute('data-ajax-delete')) {
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
        .then(r => r.json())
        .then(() => {
            if (window.closeAjaxModal) window.closeAjaxModal();
            if (window.fetchPrices) window.fetchPrices();
        })
        .catch(() => alert('حدث خطأ أثناء الحذف'));
    } else {
        form.submit();
    }
});

document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});



const countBadge = document.getElementById('priceCountBadge');

function updateCountBadge(n) {
    countBadge.textContent = `${n} سعر`;
}

</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>