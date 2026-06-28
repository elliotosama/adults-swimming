<?php
if (isset($_SESSION['user'])) {
    header('Location: /dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — أكاديمية السباحة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #080e14;
            --surface:   #0d1821;
            --card:      #111d2b;
            --border:    #1a2e42;
            --accent:    #00b4d8;
            --accent2:   #0077b6;
            --gold:      #f4a623;
            --text:      #e0eaf4;
            --muted:     #5a7a96;
            --error:     #e05c5c;
            --radius:    14px;
        }

        html, body {
            height: 100%;
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        /* ── Animated water background ── */
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 80%, #00b4d81a 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 20%, #0077b614 0%, transparent 55%),
                var(--bg);
        }

        .wave {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 220px; z-index: 0; overflow: hidden;
        }

        .wave svg { width: 200%; animation: wave-move 8s linear infinite; }
        .wave.wave2 svg { animation: wave-move 12s linear infinite reverse; opacity: .5; }

        @keyframes wave-move {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* ── Bubbles ── */
        .bubbles { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
        .bubble {
            position: absolute; bottom: -60px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #00b4d840, #00b4d810);
            border: 1px solid #00b4d830;
            animation: rise linear infinite;
        }
        @keyframes rise {
            0%   { transform: translateY(0) scale(1); opacity: .7; }
            100% { transform: translateY(-110vh) scale(1.3); opacity: 0; }
        }

        /* ── Layout ── */
        .page {
            position: relative; z-index: 10;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }

        /* ── Card ── */
        .card {
            width: 100%; max-width: 440px;
            background: linear-gradient(145deg, #111d2b, #0d1821);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.8rem 2.4rem;
            box-shadow:
                0 0 0 1px #00b4d810,
                0 32px 80px #00000080,
                inset 0 1px 0 #ffffff08;
            animation: card-in .6s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes card-in {
            from { opacity: 0; transform: translateY(28px) scale(.97); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Logo ── */
        .logo {
            text-align: center; margin-bottom: 2rem;
        }
        .logo-icon {
            width: 72px; height: 72px; margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            box-shadow: 0 8px 32px #00b4d840;
            position: relative; overflow: hidden;
        }
        .logo-icon::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #ffffff20, transparent);
        }
        .logo h1 {
            font-size: 1.5rem; font-weight: 900;
            letter-spacing: .02em;
            background: linear-gradient(90deg, var(--accent), #90e0ef);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .logo p {
            font-size: .85rem; color: var(--muted); margin-top: .3rem; font-weight: 300;
        }

        /* ── Divider ── */
        .divider {
            display: flex; align-items: center; gap: .8rem;
            margin-bottom: 1.8rem;
        }
        .divider span { flex: 1; height: 1px; background: var(--border); }
        .divider em { font-style: normal; font-size: .8rem; color: var(--muted); white-space: nowrap; }

        /* ── Form ── */
        .field { margin-bottom: 1.2rem; }
        label {
            display: block; font-size: .82rem; font-weight: 500;
            color: var(--muted); margin-bottom: .5rem;
            letter-spacing: .03em;
        }
        .input-wrap { position: relative; }
        .input-wrap .icon {
            position: absolute; top: 50%; right: 1rem;
            transform: translateY(-50%);
            font-size: 1.1rem; pointer-events: none;
            color: var(--muted);
            transition: color .2s;
        }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%; padding: .85rem 2.8rem .85rem 1rem;
            background: #0a1520;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            font-size: .95rem;
            outline: none;
            transition: border-color .25s, box-shadow .25s;
            direction: rtl;
        }
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px #00b4d820;
        }
        input:focus + .icon { color: var(--accent); }
        input::placeholder { color: var(--muted); }

        /* ── Remember / Forgot ── */
        .row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.6rem; font-size: .83rem;
        }
        .check-label {
            display: flex; align-items: center; gap: .5rem;
            color: var(--muted); cursor: pointer; user-select: none;
        }
        .check-label input[type="checkbox"] {
            width: 16px; height: 16px; padding: 0;
            accent-color: var(--accent);
            cursor: pointer;
        }
        .forgot { color: var(--accent); text-decoration: none; transition: opacity .2s; }
        .forgot:hover { opacity: .75; }

        /* ── Button ── */
        .btn {
            width: 100%; padding: .95rem;
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            border: none; border-radius: var(--radius);
            color: #fff; font-family: 'Tajawal', sans-serif;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; letter-spacing: .04em;
            position: relative; overflow: hidden;
            transition: transform .15s, box-shadow .2s;
            box-shadow: 0 6px 24px #00b4d840;
        }
        .btn::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #ffffff20, transparent);
            opacity: 0; transition: opacity .2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 32px #00b4d860; }
        .btn:hover::after { opacity: 1; }
        .btn:active { transform: translateY(0); }

        /* ── Footer ── */
        .card-footer {
            text-align: center; margin-top: 1.6rem;
            font-size: .85rem; color: var(--muted);
        }
        .card-footer a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .card-footer a:hover { text-decoration: underline; }

        /* ── Error ── */
        .alert {
            background: #e05c5c18; border: 1px solid #e05c5c50;
            border-radius: var(--radius); padding: .75rem 1rem;
            color: var(--error); font-size: .85rem;
            margin-bottom: 1.2rem; text-align: center;
        }


.logo-img {
    max-width: 220px;
    width: 100%;
    height: auto;
}
    </style>
</head>
<body>

<div class="bg"></div>

<!-- Water waves -->
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

<!-- Bubbles -->
<div class="bubbles" id="bubbles"></div>

<div class="page">
    <div class="card">

        <div class="logo">
    <img src="/logo.png" alt="Adults Swimming Academy" class="logo-img">
    <p>مرحباً بك — سجّل دخولك للمتابعة</p>
</div>

        <div class="divider">
            <span></span><em>تسجيل الدخول</em><span></span>
        </div>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST" action="/login" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email"
                           placeholder="example@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                    <span class="icon">✉️</span>
                </div>
            </div>

            <div class="field">
                <label for="password">كلمة المرور</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                    <span class="icon">🔒</span>
                </div>
            </div>

            <div class="row">
                <label class="check-label">
                    <input type="checkbox" name="remember"> تذكّرني
                </label>
                <a class="forgot" href="/forgot-password">نسيت كلمة المرور؟</a>
            </div>

            <button type="submit" class="btn">دخول</button>
        </form>

    </div>
</div>

<script>
    // Generate bubbles
    const container = document.getElementById('bubbles');
    for (let i = 0; i < 18; i++) {
        const b = document.createElement('div');
        b.className = 'bubble';
        const size = 8 + Math.random() * 28;
        b.style.cssText = `
            width:${size}px; height:${size}px;
            left:${Math.random()*100}%;
            animation-duration:${6 + Math.random()*12}s;
            animation-delay:${Math.random()*10}s;
        `;
        container.appendChild(b);
    }
</script>
</body>
</html>
