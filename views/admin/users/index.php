<?php // views/admin/users/index.php
require ROOT . '/views/includes/layout_top.php';

$roleLabels = [
    'admin'            => ['label' => 'مدير النظام', 'color' => 'role-admin'],
    'area_manager'     => ['label' => 'مدير منطقة',  'color' => 'role-area'],
    'customer_service' => ['label' => 'خدمة العملاء', 'color' => 'role-cs'],
    'branch_manager'   => ['label' => 'اداري',        'color' => 'role-manager'],
];
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل المستخدم</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا المستخدم؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
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
#userTableWrap     { transition: opacity .15s ease; }

.role-admin        { background:#7c3aed20; color:#a78bfa;        border:1px solid #7c3aed40; }
.role-manager      { background:#00b4d820; color:var(--accent);   border:1px solid #00b4d840; }
.role-area         { background:#f4a62320; color:var(--gold);     border:1px solid #f4a62340; }
.role-cs           { background:#34c78920; color:var(--success);  border:1px solid #34c78940; }
.role-instructor   { background:#e05c5c20; color:var(--error);    border:1px solid #e05c5c40; }
.role-receptionist { background:#0077b620; color:#90e0ef;         border:1px solid #0077b640; }
.role-student      { background:#ffffff10; color:var(--muted);    border:1px solid #ffffff20; }

.avatar {
    width:36px; height:36px; border-radius:10px;
    background: linear-gradient(135deg, var(--accent2), var(--accent));
    display:inline-flex; align-items:center; justify-content:center;
    font-weight:900; font-size:.88rem; color:#fff; flex-shrink:0;
}
.user-cell  { display:flex; align-items:center; gap:.75rem; }
.user-info  { display:flex; flex-direction:column; }
.user-info strong { font-size:.9rem; }
.user-info span   { font-size:.76rem; color:var(--muted); }

.stats-strip {
    display:flex; gap:1rem; margin-bottom:1.4rem; flex-wrap:wrap;
}
.stat-card {
    z-index:1;
    flex:1; min-width:130px;
    background:linear-gradient(145deg,#111d2b,#0d1821);
    border:1px solid var(--border); border-radius:16px;
    padding:1rem 1.2rem;
    display:flex; flex-direction:column; gap:.25rem;
    box-shadow:0 0 0 1px #00b4d808;
}
.stat-card__value { font-size:1.6rem; font-weight:900; }
.stat-card__label { font-size:.76rem; color:var(--muted); font-weight:600; }
.stat-card--accent  .stat-card__value { color:var(--accent); }
.stat-card--success .stat-card__value { color:var(--success); }
.stat-card--muted   .stat-card__value { color:var(--muted); }
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

<!-- Page header -->
<div class="page-header">
    <div>
        <h1 class="page-title">👥 الموظفين</h1>
        <p class="breadcrumb">لوحة التحكم · الموظفين</p>
    </div>
    <a href="<?= APP_URL ?>/admin/user/create" class="btn btn-primary">+ إضافة مستخدم</a>
</div>

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Hidden CSRF + current user id for JS -->
<input type="hidden" id="globalCsrfToken"   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<input type="hidden" id="currentUserId"     value="<?= (int)($_SESSION['user']['id'] ?? 0) ?>">

<!-- Stats strip -->
<div class="stats-strip">
    <div class="stat-card stat-card--accent">
        <span class="stat-card__value"><?= $totalUsers ?></span>
        <span class="stat-card__label">إجمالي المستخدمين</span>
    </div>
    <div class="stat-card stat-card--success">
        <span class="stat-card__value"><?= $activeUsers ?></span>
        <span class="stat-card__label">نشطون</span>
    </div>
    <div class="stat-card stat-card--muted">
        <span class="stat-card__value"><?= $totalUsers - $activeUsers ?></span>
        <span class="stat-card__label">معطّلون</span>
    </div>
</div>

<!-- Filters -->
<form id="filterForm" method="GET" action="<?= APP_URL ?>/admin/users">
    <div class="filter-bar">
        <div class="form-group">
            <label class="form-label">🔍 البحث</label>
            <input type="text"
                   id="filterSearch"
                   name="search"
                   class="form-control"
                   placeholder="الاسم أو البريد أو الهاتف..."
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                   autocomplete="off">
        </div>

        <div class="form-group">
            <label class="form-label">الدور</label>
            <div class="form-select-wrap">
                <select id="filterRole" name="role" class="form-control">
                    <option value="">جميع الأدوار</option>
                    <?php foreach ($roleLabels as $key => $r): ?>
                        <option value="<?= $key ?>"
                            <?= ($filters['role'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $r['label'] ?>
                            <?php if (!empty($roleCounts[$key])): ?>
                                (<?= $roleCounts[$key] ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

<div class="form-group">
    <label class="form-label">الحالة</label>
    <div class="form-select-wrap">
        <!-- Changed name to is_active and updated the PHP checks to look for it -->
        <select id="filterVisible" name="is_active" class="form-control">
            <option value="">الكل</option>
            <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>نشط ✅</option>
            <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>معطّل ❌</option>
        </select>
    </div>
</div>

        <div class="filter-bar__actions">
            <button type="submit" class="btn btn-primary">تطبيق</button>
            <a href="<?= APP_URL ?>/admin/users" class="btn btn-secondary" id="clearFiltersBtn">مسح</a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="card">
    <div id="userTableWrap">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-icon">👤</div>
                <p>لا يوجد مستخدمون يطابقون البحث.</p>
                <?php if (!empty($filters['search']) || !empty($filters['role']) || ($filters['visible'] ?? '') !== ''): ?>
                    <a href="<?= APP_URL ?>/admin/users" class="btn btn-secondary" style="margin-top:1rem">إعادة ضبط الفلاتر</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>الهاتف</th>
                            <th>الحالة</th>
                            <th>آخر دخول</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $initials = mb_strtoupper(mb_substr($u['username'] ?? '?', 0, 1));
                                $role     = $roleLabels[$u['role']] ?? ['label' => $u['role'], 'color' => 'badge'];
                                $isActive = !empty($u['is_active']) && !empty($u['visible']);
                            ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.82rem"><?= $u['id'] ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div class="user-info">
                                            <strong><?= htmlspecialchars($u['username'] ?? '—') ?></strong>
                                            <span><?= htmlspecialchars($u['email'] ?? '—') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $role['color'] ?>"><?= $role['label'] ?></span>
                                </td>
                                <td style="color:var(--muted);font-size:.85rem">
                                    <?= htmlspecialchars($u['phone'] ?? '—') ?>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">معطّل</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--muted);font-size:.82rem">
                                    <?= !empty($u['last_login']) ? htmlspecialchars($u['last_login']) : '—' ?>
                                </td>
                                <td>
                                    <div class="td-actions">
                                        <a href="<?= APP_URL ?>/admin/user/show?id=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-secondary">عرض</a>
                                        <a href="<?= APP_URL ?>/admin/user/edit?id=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-warning">تعديل</a>
                                        <?php if ($u['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                                        <form method="POST"
                                              action="<?= APP_URL ?>/admin/user/delete?id=<?= $u['id'] ?>"
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
                عرض <?= count($users) ?> مستخدم
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const AJAX_URL     = '<?= APP_URL ?>/admin/users/search';
    const APP_URL      = '<?= APP_URL ?>';
    const currentUid   = parseInt(document.getElementById('currentUserId').value, 10);

    // Role map for JS rendering — mirrors the PHP $roleLabels array above
    const roleLabels = {
        admin:            { label: 'مدير النظام', color: 'role-admin' },
        area_manager:     { label: 'مدير منطقة',  color: 'role-area'  },
        customer_service: { label: 'خدمة العملاء', color: 'role-cs'   },
        branch_manager:   { label: 'اداري',        color: 'role-manager' },
    };

    const form            = document.getElementById('filterForm');
    const wrap            = document.getElementById('userTableWrap');
    const searchInput     = document.getElementById('filterSearch');
    const roleSelect      = document.getElementById('filterRole');
    const visibleSelect   = document.getElementById('filterVisible');
    const clearBtn        = document.getElementById('clearFiltersBtn');

    let debounceTimer = null;

    // ── XSS guard ────────────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── First letter for avatar ──────────────────────────────────────────
    function initial(name) {
        return esc((name ?? '?').charAt(0).toUpperCase());
    }

    // ── Action buttons per row ───────────────────────────────────────────
    function actionButtons(u) {
        const csrf = esc(document.getElementById('globalCsrfToken').value);

        const deleteBtn = u.id != currentUid ? `
            <form method="POST"
                  action="${APP_URL}/admin/user/delete?id=${u.id}"
                  style="display:inline"
                  onsubmit="event.preventDefault(); showDeleteModal(this);">
                <input type="hidden" name="csrf_token" value="${csrf}">
                <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
            </form>` : '';

        return `
            <div class="td-actions">
                <a href="${APP_URL}/admin/user/show?id=${u.id}" class="btn btn-sm btn-secondary">عرض</a>
                <a href="${APP_URL}/admin/user/edit?id=${u.id}" class="btn btn-sm btn-warning">تعديل</a>
                ${deleteBtn}
            </div>`;
    }

    // ── Render users array into #userTableWrap ───────────────────────────
    function renderTable(users) {
        if (!users.length) {
            wrap.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">👤</div>
                    <p>لا يوجد مستخدمون يطابقون البحث.</p>
                </div>`;
            return;
        }

        const rows = users.map(u => {
            const role     = roleLabels[u.role] ?? { label: esc(u.role), color: 'badge' };
            const isActive = u.is_active == 1 && u.visible == 1;

            return `
            <tr>
                <td style="color:var(--muted);font-size:.82rem">${esc(u.id)}</td>
                <td>
                    <div class="user-cell">
                        <div class="avatar">${initial(u.username)}</div>
                        <div class="user-info">
                            <strong>${esc(u.username || '—')}</strong>
                            <span>${esc(u.email || '—')}</span>
                        </div>
                    </div>
                </td>
                <td><span class="badge ${role.color}">${role.label}</span></td>
                <td style="color:var(--muted);font-size:.85rem">${esc(u.phone || '—')}</td>
                <td>
                    ${isActive
                        ? '<span class="badge badge-success">نشط</span>'
                        : '<span class="badge badge-danger">معطّل</span>'}
                </td>
                <td style="color:var(--muted);font-size:.82rem">${esc(u.last_login || '—')}</td>
                <td>${actionButtons(u)}</td>
            </tr>`;
        }).join('');

        wrap.innerHTML = `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>الهاتف</th>
                            <th>الحالة</th>
                            <th>آخر دخول</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
                عرض ${users.length} مستخدم
            </div>`;
    }

    // ── Fetch from AJAX endpoint ─────────────────────────────────────────
function fetchUsers() {
        const params = new URLSearchParams({
            search:    searchInput.value.trim(),
            role:      roleSelect.value,
            is_active: visibleSelect.value, // <-- Changed from 'visible' to 'is_active'
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
        debounceTimer = setTimeout(fetchUsers, 300);
    });

    // ── Instant on select change ─────────────────────────────────────────
    roleSelect.addEventListener('change', fetchUsers);
    visibleSelect.addEventListener('change', fetchUsers);

    // ── Intercept form submit ────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchUsers();
    });

    // ── Clear button ─────────────────────────────────────────────────────
    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value   = '';
        roleSelect.value    = '';
        visibleSelect.value = '';
        fetchUsers();
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>