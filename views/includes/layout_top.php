<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'لوحة التحكم') ?> — أكاديمية السباحة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #080e14;
            --surface: #0d1821;
            --card:    #111d2b;
            --border:  #1a2e42;
            --accent:  #00b4d8;
            --accent2: #0077b6;
            --gold:    #f4a623;
            --text:    #e0eaf4;
            --muted:   #5a7a96;
            --error:   #e05c5c;
            --success: #34c789;
            --warning: #f4a623;
            --radius:  14px;
        }

        html, body {
            min-height: 100%;
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            color: var(--text);
            direction: rtl;
        }

        /* ── Background ── */
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 80%, #00b4d81a 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 20%, #0077b614 0%, transparent 55%),
                var(--bg);
            pointer-events: none;
        }
        .wave { position: fixed; bottom: 0; left: 0; right: 0; height: 220px; z-index: 0; overflow: hidden; pointer-events: none; }
        .wave svg { width: 200%; animation: wave-move 8s linear infinite; }
        .wave.wave2 svg { animation: wave-move 12s linear infinite reverse; opacity: .5; }
        @keyframes wave-move { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .bubbles { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
        .bubble {
            position: absolute; bottom: -60px; border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #00b4d840, #00b4d810);
            border: 1px solid #00b4d830;
            animation: rise linear infinite;
        }
        @keyframes rise { 0% { transform: translateY(0) scale(1); opacity: .7; } 100% { transform: translateY(-110vh) scale(1.3); opacity: 0; } }

        /* ── Top nav ── */
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: #0a1520ee;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: .9rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
        }
        .nav-brand {
            display: flex; align-items: center; gap: .75rem;
            font-weight: 900; font-size: 1.1rem;
            background: linear-gradient(90deg, var(--accent), #90e0ef);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-decoration: none;
            flex-shrink: 0;
        }
        .nav-brand span { font-size: 1.4rem; }
        .nav-links { display: flex; gap: .5rem; flex-wrap: wrap; }

        /* ── Hide nav links on mobile by default ── */
        @media (max-width: 900px) {
            .nav-links { display: none; }
        }
        .nav-link {
            padding: .45rem 1rem; border-radius: 8px;
            color: var(--muted); text-decoration: none; font-size: .88rem; font-weight: 500;
            transition: color .2s, background .2s;
        }
        .nav-link:hover, .nav-link.active { color: var(--accent); background: #00b4d810; }

        /* ── Page wrapper ── */
        .page {
            position: relative; z-index: 10;
            max-width: 1100px; margin: 0 auto;
            padding: 2rem 1.5rem 6rem;
        }

        /* ── Full-width page override (e.g. receipts index) ── */
        .page--full {
            max-width: 100%;
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }

        /* ── Page header ── */
        .page-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
            margin-bottom: 1.8rem;
            animation: card-in .5s cubic-bezier(.22,1,.36,1) both;
        }
        .page-title { font-size: 1.6rem; font-weight: 900; }
        .breadcrumb { font-size: .8rem; color: var(--muted); margin-top: .25rem; }

        /* ── Cards ── */
        .card {
            background: linear-gradient(145deg, #111d2b, #0d1821);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 0 0 1px #00b4d810, 0 24px 60px #00000060, inset 0 1px 0 #ffffff08;
            animation: card-in .6s cubic-bezier(.22,1,.36,1) both;
            overflow: hidden;
        }
        @keyframes card-in { from { opacity: 0; transform: translateY(20px) scale(.98); } to { opacity: 1; transform: none; } }

        /* ── Alerts ── */
        .alert {
            border-radius: var(--radius); padding: .85rem 1.1rem;
            font-size: .88rem; margin-bottom: 1.4rem;
            animation: card-in .4s ease both;
        }
        .alert-success { background: #34c78918; border: 1px solid #34c78950; color: var(--success); }
        .alert-error   { background: #e05c5c18; border: 1px solid #e05c5c50; color: var(--error); }
        .alert-info    { background: #00b4d818; border: 1px solid #00b4d850; color: var(--accent); }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .7rem 1.4rem; border-radius: var(--radius);
            font-family: 'Tajawal', sans-serif; font-size: .9rem; font-weight: 700;
            cursor: pointer; text-decoration: none; border: none;
            transition: transform .15s, box-shadow .2s, opacity .2s;
            position: relative; overflow: hidden;
        }
        .btn::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg,#ffffff18,transparent); opacity: 0; transition: opacity .2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn:hover::after { opacity: 1; }
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        .btn-primary   { background: linear-gradient(135deg, var(--accent2), var(--accent)); color: #fff; box-shadow: 0 6px 20px #00b4d840; }
        .btn-primary:hover  { box-shadow: 0 10px 28px #00b4d860; }
        .btn-secondary { background: #1a2e42; color: var(--text); border: 1px solid var(--border); }
        .btn-warning   { background: linear-gradient(135deg, #c07800, var(--gold)); color: #fff; box-shadow: 0 4px 16px #f4a62330; }
        .btn-danger    { background: linear-gradient(135deg, #a02020, var(--error)); color: #fff; box-shadow: 0 4px 16px #e05c5c30; }
        .btn-success   { background: linear-gradient(135deg, #1a8a50, var(--success)); color: #fff; }
        .btn-sm { padding: .45rem .9rem; font-size: .82rem; border-radius: 9px; }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;
            background: linear-gradient(145deg, #111d2b, #0d1821);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.2rem 1.4rem;
            margin-bottom: 1.4rem;
            animation: card-in .55s cubic-bezier(.22,1,.36,1) both;
        }
        .filter-bar .form-group { display: flex; flex-direction: column; gap: .4rem; flex: 1; min-width: 160px; }
        .filter-bar__actions { display: flex; gap: .6rem; align-items: flex-end; padding-bottom: 0; }
        .form-label { font-size: .78rem; font-weight: 700; color: var(--muted); letter-spacing: .04em; }
        .form-control {
            width: 100%; padding: .72rem 1rem;
            background: #0a1520; border: 1.5px solid var(--border);
            border-radius: var(--radius); color: var(--text);
            font-family: 'Tajawal', sans-serif; font-size: .88rem;
            outline: none; transition: border-color .25s, box-shadow .25s;
            appearance: none;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px #00b4d820; }
        .form-control::placeholder { color: #2a4a64; }
        .form-select-wrap { position: relative; }
        .form-select-wrap::after {
            content: '▾'; position: absolute; left: .85rem; top: 50%;
            transform: translateY(-50%); color: var(--muted); pointer-events: none; font-size: .8rem;
        }
        select.form-control { padding-left: 2rem; }
        select.form-control option { background: var(--surface); }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { border-bottom: 1px solid var(--border); }
        th {
            padding: 1rem 1.2rem; text-align: right;
            font-size: .78rem; font-weight: 700; letter-spacing: .06em;
            color: var(--muted); text-transform: uppercase; white-space: nowrap;
        }
        td {
            padding: 1rem 1.2rem; font-size: .9rem;
            border-bottom: 1px solid #1a2e4260;
            vertical-align: middle;
        }
        tbody tr { transition: background .15s; }
        tbody tr:hover { background: #00b4d808; }
        tbody tr:last-child td { border-bottom: none; }
        .td-actions { display: flex; gap: .4rem; flex-wrap: wrap; }

        /* ── Badges ── */
        .badge {
            display: inline-block; padding: .25rem .7rem;
            border-radius: 20px; font-size: .76rem; font-weight: 700;
        }
        .badge-success { background: #34c78920; color: var(--success); border: 1px solid #34c78940; }
        .badge-danger  { background: #e05c5c20; color: var(--error);   border: 1px solid #e05c5c40; }
        .badge-warning { background: #f4a62320; color: var(--warning); border: 1px solid #f4a62340; }
        .badge-secondary { background: #1a2e42; color: var(--muted); border: 1px solid var(--border); }

        /* ── Day pills (show view) ── */
        .shift-row { display: flex; align-items: center; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid #1a2e4240; }
        .shift-row:last-child { border-bottom: none; }
        .shift-label { font-size: .82rem; font-weight: 700; color: var(--muted); width: 60px; flex-shrink: 0; }
        .day-pills { display: flex; gap: .35rem; flex-wrap: wrap; }
        .day-pill { padding: .28rem .65rem; border-radius: 8px; font-size: .78rem; font-weight: 600; }
        .day-pill--active { background: #00b4d820; color: var(--accent); border: 1px solid #00b4d840; }
        .day-pill--off    { background: #1a2e4250; color: #2a4a64; border: 1px solid transparent; }

        /* ── Detail grid (show view) ── */
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; padding: 1.6rem; border-bottom: 1px solid var(--border); }
        .detail-item { display: flex; flex-direction: column; gap: .3rem; }
        .detail-label { font-size: .75rem; font-weight: 700; color: var(--muted); letter-spacing: .05em; }
        .detail-value { font-size: 1rem; font-weight: 600; }
        .detail-section { padding: 1.4rem 1.6rem; border-bottom: 1px solid var(--border); }
        .detail-section-title { font-size: .85rem; font-weight: 700; color: var(--muted); margin-bottom: 1rem; letter-spacing: .05em; }

        /* ── Danger zone ── */
        .danger-zone {
            padding: 1.2rem 1.6rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            background: #e05c5c08; border-top: 1px solid #e05c5c30;
        }
        .danger-zone p { font-size: .85rem; color: var(--muted); }

        /* ── Form styles ── */
        .form-body { padding: 1.8rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        .field { margin-bottom: 1.3rem; }
        .field label { display: block; font-size: .82rem; font-weight: 600; color: var(--muted); margin-bottom: .5rem; letter-spacing: .03em; }
        .required { color: var(--error); }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; top: 50%; right: 1rem; transform: translateY(-50%); font-size: 1rem; pointer-events: none; color: var(--muted); transition: color .2s; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="password"], input[type="time"], select {
            width: 100%; padding: .82rem 2.6rem .82rem 1rem;
            background: #0a1520; border: 1.5px solid var(--border);
            border-radius: var(--radius); color: var(--text);
            font-family: 'Tajawal', sans-serif; font-size: .92rem;
            outline: none; transition: border-color .25s, box-shadow .25s; direction: rtl;
            appearance: none;
        }
        input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px #00b4d820; }
        input:focus + .icon, select:focus + .icon { color: var(--accent); }
        input::placeholder { color: #2a4a64; }
        select option { background: var(--surface); }

        /* ── Radio group ── */
        .radio-group { display: flex; gap: 1rem; margin-top: .3rem; }
        .radio-label {
            display: flex; align-items: center; gap: .5rem;
            font-size: .88rem; color: var(--text); cursor: pointer; font-weight: 400;
            padding: .6rem 1.1rem; border-radius: 10px; border: 1.5px solid var(--border);
            transition: border-color .2s, background .2s;
        }
        .radio-label:has(input:checked) { border-color: var(--accent); background: #00b4d812; color: var(--accent); }
        .radio-label input[type="radio"] { accent-color: var(--accent); }

        /* ── Day checkboxes ── */
        .section-title {
            font-size: .78rem; font-weight: 700; color: var(--muted);
            letter-spacing: .07em; text-transform: uppercase;
            margin-bottom: 1rem; padding-bottom: .5rem;
            border-bottom: 1px solid var(--border);
        }
        .shifts-grid { display: flex; flex-direction: column; gap: 1rem; }
        .shift-block { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .shift-name { font-size: .82rem; font-weight: 700; color: var(--muted); width: 55px; flex-shrink: 0; }
        .day-checks { display: flex; gap: .4rem; flex-wrap: wrap; }
        .day-check-label {
            display: flex; align-items: center; gap: .3rem;
            padding: .38rem .75rem; border-radius: 8px;
            border: 1.5px solid var(--border); font-size: .82rem;
            cursor: pointer; transition: border-color .2s, background .2s, color .2s;
            user-select: none;
        }
        .day-check-label:has(input:checked) { border-color: var(--accent); background: #00b4d815; color: var(--accent); }
        .day-check-label input[type="checkbox"] { display: none; }

        /* ── Form actions ── */
        .form-actions {
            display: flex; gap: .8rem; flex-wrap: wrap;
            padding-top: 1.4rem; margin-top: .4rem;
            border-top: 1px solid var(--border);
        }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 3.5rem 1rem; color: var(--muted); }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: .8rem; }
        .empty-state p { font-size: .95rem; }

        /* ── Hamburger button ── */
        .nav-toggle {
            display: none;
            background: #1a2e42;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 8px;
            padding: .5rem .75rem;
            font-size: 1.2rem;
            cursor: pointer;
            line-height: 1;
            transition: color .2s, background .2s;
            flex-shrink: 0;
        }
        .nav-toggle:hover { color: var(--accent); background: #00b4d810; }

        /* ── Mobile nav ── */
        @media (max-width: 900px) {
            .nav-links {
                flex-direction: column;
                gap: .3rem;
                position: fixed;
                top: 57px;
                right: 0; left: 0;
                background: #0a1520f5;
                backdrop-filter: blur(16px);
                border-bottom: 1px solid var(--border);
                padding: .75rem 1rem;
                z-index: 99;
            }
            .nav-links.open { display: flex; }
            .nav-link { width: 100%; text-align: right; padding: .65rem 1rem; font-size: .92rem; }
            .nav-toggle { display: block; }
        }

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .page { padding: 1.2rem 1rem 5rem; }
            .topnav { padding: .8rem 1rem; }
            .page-header { flex-direction: column; }
            .detail-grid { grid-template-columns: 1fr 1fr; padding: 1rem; }
            .form-body { padding: 1.2rem; }
            .radio-group { flex-direction: column; gap: .5rem; }
            .danger-zone { flex-direction: column; align-items: flex-start; }
            .td-actions { justify-content: flex-start; }
            .btn { font-size: .85rem; }
            th, td { padding: .75rem .8rem; }
            .filter-bar { flex-direction: column; }
            .filter-bar .form-group { min-width: 100%; }
        }

        @media (max-width: 400px) {
            .detail-grid { grid-template-columns: 1fr; }
            .nav-brand span { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<div class="bg"></div>
<div class="wave">
    <svg viewBox="0 0 1440 220" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" height="220">
        <path fill="#00b4d815" d="M0,80 C360,160 720,0 1080,80 C1260,120 1380,60 1440,80 L1440,220 L0,220Z"/>
        <path fill="#0077b620" d="M0,120 C300,60 600,180 900,120 C1100,80 1300,140 1440,120 L1440,220 L0,220Z"/>
    </svg>
</div>
<div class="wave wave2">
    <svg viewBox="0 0 1440 220" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" height="220">
        <path fill="#00b4d80a" d="M0,100 C400,40 800,160 1200,100 C1320,80 1400,110 1440,100 L1440,220 L0,220Z"/>
    </svg>
</div>
<div class="bubbles" id="bubbles"></div>

<nav class="topnav">
    <!-- RTL: brand on right, links in middle, toggle on left for mobile -->
    <a class="nav-brand" href="<?= APP_URL ?>">
        <?php echo htmlspecialchars($_SESSION['user']['full_name']) ?>
    </a>
    <div class="nav-links" id="navLinks">
        <a class="nav-link" href="<?= APP_URL ?>/<?php echo $_SESSION['user']['role'] ?>/dashboard">لوحة التحكم</a>
        <?php if ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['role'] === 'area_manager'): ?>
            <a class="nav-link" href="<?= APP_URL ?>/admin/branches">الفروع</a>
            <a class="nav-link" href="<?= APP_URL ?>/admin/captains">الكباتن</a>
        <?php endif; ?>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a class="nav-link" href="<?= APP_URL ?>/admin/users">الموظفين</a>
            <a class="nav-link" href="<?= APP_URL ?>/admin/prices">الاسعار</a>
            <a class="nav-link" href="<?= APP_URL ?>/transactions">المعاملات الماليه</a>
            <a class="nav-link" href="<?= APP_URL ?>/country">الدول</a>
        <?php endif; ?>
        <a class="nav-link" href="<?= APP_URL ?>/receipts">ايصالاتي</a>
        <a class="nav-link" href="<?= APP_URL ?>/receipt/manage">اداره</a>
        <a class="nav-link" href="<?= APP_URL ?>/receipt/payment-by-id">بحث</a>
        <a class="nav-link" href="<?= APP_URL ?>/logout">خروج</a>
    </div>
    <button class="nav-toggle" id="navToggle" aria-label="القائمة" aria-expanded="false">&#9776;</button>
</nav>

<script>
    // Bubble generator
    (function() {
        const container = document.getElementById('bubbles');
        if (!container) return;
        for (let i = 0; i < 12; i++) {
            const b = document.createElement('div');
            b.className = 'bubble';
            const size = 8 + Math.random() * 24;
            b.style.cssText = `width:${size}px;height:${size}px;left:${Math.random()*100}%;animation-duration:${6+Math.random()*10}s;animation-delay:${Math.random()*8}s`;
            container.appendChild(b);
        }
    })();


</script>

<!-- /.page opens here — closed in layout_bottom.php -->
<div class="page <?= htmlspecialchars($pageClass ?? '') ?>">
