<?php // views/transactions/index.php
require ROOT . '/views/includes/layout_top.php';

// ── Pagination URL helper (preserves active filters) ──────────────────────
function paginationUrl(int $p): string {
    $params = array_filter([
        'page'         => $p,
        'receipt_id'   => $_GET['receipt_id']   ?? '',
        'client_phone' => $_GET['client_phone'] ?? '',
    ], fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل المعامله</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا المعامله؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
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

/* ── AJAX loading state ── */
.table-wrap.loading { opacity: .45; pointer-events: none; transition: opacity .15s; }
.search-spinner {
    display: none;
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
}
.search-spinner.visible { display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
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

document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

document.getElementById('confirmBtn').addEventListener('click', function () {
    if (_pendingForm) {
        // Submit the delete via fetch so the table can refresh in place
        // instead of doing a full page navigation.
        const form = _pendingForm;
        closeModal();

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
        .then(() => runSearch(currentPage))
        .catch(() => runSearch(currentPage));
    }
});
</script>

<div class="page-header">
    <div>
        <h1 class="page-title">💳 المعاملات المالية</h1>
        <p class="breadcrumb">لوحة التحكم · المعاملات</p>
    </div>
    <a href="<?= APP_URL ?>/transaction/create" class="btn btn-primary">
        + إضافة معاملة
    </a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- ══ Filter Form ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:1rem">
    <form id="txnFilterForm" method="GET" action="" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;padding:.75rem;">

        <div style="flex:1;min-width:130px">
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.25rem">
                رقم الإيصال
            </label>
            <input
                type="text"
                name="receipt_id"
                id="filter-receipt-id"
                value="<?= htmlspecialchars($_GET['receipt_id'] ?? '') ?>"
                class="form-input"
                placeholder="مثال: 142"
                style="width:100%"
            >
        </div>

        <div style="flex:1;min-width:160px">
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.25rem">
                رقم هاتف العميل
            </label>
            <input
                type="text"
                name="client_phone"
                id="filter-client-phone"
                value="<?= htmlspecialchars($_GET['client_phone'] ?? '') ?>"
                class="form-input"
                placeholder="مثال: 0501234567"
                style="width:100%"
            >
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-self:flex-end;align-items:center">
            <button type="submit" class="btn btn-primary" style="display:flex;align-items:center;gap:.4rem">
                🔍 بحث
                <span class="search-spinner" id="searchSpinner"></span>
            </button>

            <a href="<?= APP_URL ?>/transactions" id="clearFilterBtn" class="btn btn-secondary"
               style="<?= (!empty($_GET['receipt_id']) || !empty($_GET['client_phone'])) ? '' : 'display:none' ?>">
                ✕ مسح الفلتر
            </a>
        </div>

    </form>
</div>
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<div class="card">
    <div id="txnEmptyState" class="empty-state" style="<?= empty($transactions) ? '' : 'display:none' ?>">
        <div class="empty-icon">💳</div>
        <p>لا توجد معاملات مالية مسجّلة بعد.</p>
    </div>

    <div class="table-wrap" id="txnTableWrap" style="<?= empty($transactions) ? 'display:none' : '' ?>">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>النوع</th>
                    <th>طريقة الدفع</th>
                    <th>المبلغ</th>
                    <th>الإيصال</th>
                    <th>هاتف العميل</th>
                    <th>اسم العميل</th>
                    <th>المنشئ</th>
                    <th>التاريخ</th>
                    <th>ملاحظات</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody id="txnTableBody">
                <?php foreach ($transactions as $t): ?>
                    <?php
                    $typeMap = [
                        'payment'  => ['badge-success', 'دفعة'],
                        'refund'   => ['badge-danger',  'استرداد'],
                        'discount' => ['badge-warning', 'خصم'],
                    ];
                    [$tCls, $tLabel] = $typeMap[$t['type']] ?? ['badge-secondary', $t['type']];
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.82rem"><?= $t['id'] ?></td>
                        <td><span class="badge <?= $tCls ?>"><?= $tLabel ?></span></td>
                        <td><?= htmlspecialchars($t['payment_method'] ?? '—') ?></td>
                        <td><strong><?= number_format($t['amount'], 2) ?></strong></td>
                        <td>
                            <?php if ($t['receipt_id']): ?>
                                <a href="<?= APP_URL ?>/receipt/show?id=<?= $t['receipt_id'] ?>"
                                   style="color:var(--primary);text-decoration:none;font-size:.85rem">
                                    #<?= $t['receipt_id'] ?>
                                </a>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.85rem">
                            <?php if (!empty($t['client_phone'])): ?>
                                <a href="<?= APP_URL ?>/transactions?client_phone=<?= urlencode($t['client_phone']) ?>"
                                   style="color:var(--primary);text-decoration:none">
                                    <?= htmlspecialchars($t['client_phone']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.85rem"><?= htmlspecialchars($t['client_name'] ?? '—') ?></td>
                        <td style="font-size:.85rem"><?= htmlspecialchars($t['creator_name'] ?? '—') ?></td>
                        <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
                        <td style="font-size:.82rem;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= htmlspecialchars($t['notes'] ?? '—') ?>
                        </td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= APP_URL ?>/transaction/show?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                <a href="<?= APP_URL ?>/transaction/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                <form method="POST"
                                      action="<?= APP_URL ?>/transaction/delete?id=<?= $t['id'] ?>"
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
</div>

<div class="pagination-wrap" id="txnPaginationWrap" style="margin-top: 24px; <?= $totalPages > 1 ? '' : 'display:none' ?>">
    <span class="pagination-info" id="txnPaginationInfo">
        عرض صفحة <?= $page ?> من <?= $totalPages ?>
        &nbsp;·&nbsp; إجمالي <?= number_format($total) ?> معاملة
    </span>
    <div class="pagination" id="txnPaginationLinks" style="margin-top: 10px;">
        <?= /* initial server-rendered pagination, replaced by JS after first AJAX search */ '' ?>
    </div>
</div>

<script>
const APP_URL    = <?= json_encode(APP_URL) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

let currentPage = <?= (int) $page ?>;

const TYPE_MAP = {
    payment:  ['badge-success', 'دفعة'],
    refund:   ['badge-danger',  'استرداد'],
    discount: ['badge-warning', 'خصم'],
};

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatAmount(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function buildRow(t) {
    const [tCls, tLabel] = TYPE_MAP[t.type] || ['badge-secondary', t.type];

    const receiptCell = t.receipt_id
        ? `<a href="${APP_URL}/receipt/show?id=${t.receipt_id}" style="color:var(--primary);text-decoration:none;font-size:.85rem">#${t.receipt_id}</a>`
        : `<span style="color:var(--muted)">—</span>`;

    const phoneCell = t.client_phone
        ? `<a href="${APP_URL}/transactions?client_phone=${encodeURIComponent(t.client_phone)}" style="color:var(--primary);text-decoration:none">${escapeHtml(t.client_phone)}</a>`
        : `<span style="color:var(--muted)">—</span>`;

    return `
        <tr>
            <td style="color:var(--muted);font-size:.82rem">${t.id}</td>
            <td><span class="badge ${tCls}">${tLabel}</span></td>
            <td>${escapeHtml(t.payment_method || '—')}</td>
            <td><strong>${formatAmount(t.amount)}</strong></td>
            <td>${receiptCell}</td>
            <td style="font-size:.85rem">${phoneCell}</td>
            <td style="font-size:.85rem">${escapeHtml(t.client_name || '—')}</td>
            <td style="font-size:.85rem">${escapeHtml(t.creator_name || '—')}</td>
            <td style="font-size:.82rem;color:var(--muted)">${escapeHtml(t.created_at || '—')}</td>
            <td style="font-size:.82rem;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(t.notes || '—')}</td>
            <td>
                <div class="td-actions">
                    <a href="${APP_URL}/transaction/show?id=${t.id}" class="btn btn-sm btn-secondary">عرض</a>
                    <a href="${APP_URL}/transaction/edit?id=${t.id}" class="btn btn-sm btn-warning">تعديل</a>
                    <form method="POST" action="${APP_URL}/transaction/delete?id=${t.id}" style="display:inline"
                          onsubmit="event.preventDefault(); showDeleteModal(this);">
                        <input type="hidden" name="csrf_token" value="${escapeHtml(CSRF_TOKEN)}">
                        <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                    </form>
                </div>
            </td>
        </tr>
    `;
}

function buildPaginationUrl(p, filters) {
    const params = new URLSearchParams();
    params.set('page', p);
    if (filters.receipt_id)   params.set('receipt_id', filters.receipt_id);
    if (filters.client_phone) params.set('client_phone', filters.client_phone);
    return '?' + params.toString();
}

function buildPagination(page, totalPages, filters) {
    if (totalPages <= 1) return '';

    let html = '';

    if (page > 1) {
        html += `<a href="${buildPaginationUrl(page - 1, filters)}" data-page="${page - 1}" class="btn btn-sm btn-secondary pagination-link">« السابق</a>`;
    }

    const start = Math.max(1, page - 2);
    const end   = Math.min(totalPages, page + 2);

    if (start > 1) {
        html += `<a href="${buildPaginationUrl(1, filters)}" data-page="1" class="btn btn-sm btn-secondary pagination-link">1</a>`;
        if (start > 2) html += `<span class="pagination-ellipsis">…</span>`;
    }

    for (let p = start; p <= end; p++) {
        html += `<a href="${buildPaginationUrl(p, filters)}" data-page="${p}" class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-secondary'} pagination-link">${p}</a>`;
    }

    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span class="pagination-ellipsis">…</span>`;
        html += `<a href="${buildPaginationUrl(totalPages, filters)}" data-page="${totalPages}" class="btn btn-sm btn-secondary pagination-link">${totalPages}</a>`;
    }

    if (page < totalPages) {
        html += `<a href="${buildPaginationUrl(page + 1, filters)}" data-page="${page + 1}" class="btn btn-sm btn-secondary pagination-link">التالي »</a>`;
    }

    return html;
}

function getCurrentFilters() {
    return {
        receipt_id:   document.getElementById('filter-receipt-id').value.trim(),
        client_phone: document.getElementById('filter-client-phone').value.trim(),
    };
}

function setLoading(isLoading) {
    document.getElementById('txnTableWrap').classList.toggle('loading', isLoading);
    document.getElementById('searchSpinner').classList.toggle('visible', isLoading);
}

function runSearch(page) {
    const filters = getCurrentFilters();
    currentPage = page;

    const params = new URLSearchParams();
    params.set('page', page);
    if (filters.receipt_id)   params.set('receipt_id', filters.receipt_id);
    if (filters.client_phone) params.set('client_phone', filters.client_phone);

    setLoading(true);

    fetch(`${APP_URL}/transaction/search-json?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(res => res.json())
        .then(json => {
            const tbody   = document.getElementById('txnTableBody');
            const wrap    = document.getElementById('txnTableWrap');
            const empty   = document.getElementById('txnEmptyState');
            const pagWrap = document.getElementById('txnPaginationWrap');
            const pagInfo = document.getElementById('txnPaginationInfo');
            const pagLinks= document.getElementById('txnPaginationLinks');
            const clearBtn= document.getElementById('clearFilterBtn');

            if (!json.data || json.data.length === 0) {
                wrap.style.display  = 'none';
                empty.style.display = '';
            } else {
                empty.style.display = 'none';
                wrap.style.display  = '';
                tbody.innerHTML = json.data.map(buildRow).join('');
            }

            if (json.totalPages > 1) {
                pagWrap.style.display = '';
                pagInfo.textContent = `عرض صفحة ${json.page} من ${json.totalPages} · إجمالي ${Number(json.total).toLocaleString('en-US')} معاملة`;
                pagLinks.innerHTML = buildPagination(json.page, json.totalPages, filters);
            } else {
                pagWrap.style.display = 'none';
            }

            clearBtn.style.display = (filters.receipt_id || filters.client_phone) ? '' : 'none';

            // Keep the URL bar in sync (back/forward + shareable links) without reloading
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            history.pushState({ page, filters }, '', newUrl);
        })
        .catch(() => {
            // Fail quietly — the previously rendered table stays as-is.
        })
        .finally(() => setLoading(false));
}

// ── Intercept filter form submit ──────────────────────────────────────────
document.getElementById('txnFilterForm').addEventListener('submit', function (e) {
    e.preventDefault();
    runSearch(1);
});

// ── Intercept "clear filter" click ────────────────────────────────────────
document.getElementById('clearFilterBtn').addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('filter-receipt-id').value   = '';
    document.getElementById('filter-client-phone').value = '';
    runSearch(1);
});

// ── Intercept pagination link clicks (event delegation, since links are
//    rebuilt dynamically after every search) ───────────────────────────────
document.addEventListener('click', function (e) {
    const link = e.target.closest('.pagination-link');
    if (!link) return;
    e.preventDefault();
    const page = parseInt(link.dataset.page, 10);
    if (page) runSearch(page);
});

// ── Support browser back/forward ──────────────────────────────────────────
window.addEventListener('popstate', function (e) {
    const params = new URLSearchParams(window.location.search);
    document.getElementById('filter-receipt-id').value   = params.get('receipt_id')   || '';
    document.getElementById('filter-client-phone').value = params.get('client_phone') || '';
    runSearch(parseInt(params.get('page'), 10) || 1);
});
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>