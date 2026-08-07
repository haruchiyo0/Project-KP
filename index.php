<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (current_user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $statement->execute([$username]);
    $user = $statement->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'nik' => $user['nik'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Username atau password tidak sesuai.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="IndiHome Field — Sistem pencatatan pekerjaan dan monitoring tim teknisi lapangan.">
    <title>Login | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
    /* ===================================================================
       FRESH PREMIUM INTRO OVERLAY
       =================================================================== */
    .intro-overlay {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        background: #030305; /* Deep premium dark */
        overflow: hidden;
        transition: opacity .8s cubic-bezier(.4,0,.2,1), visibility .8s;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .intro-overlay.fade-out {
        opacity: 0; visibility: hidden;
        pointer-events: none;
    }

    /* Premium Blurred Orbs (Glassmorphism bg) */
    .intro-orb {
        position: absolute; border-radius: 50%;
        filter: blur(100px); opacity: 0;
        animation: orb-fade-in 2s forwards, orb-float 10s ease-in-out infinite alternate;
    }
    .intro-orb-1 {
        width: 45vw; height: 45vw; background: rgba(230,0,18,.12);
        top: -15%; left: -10%;
    }
    .intro-orb-2 {
        width: 35vw; height: 35vw; background: rgba(67, 56, 202, .12);
        bottom: -10%; right: -5%; animation-delay: .5s;
    }
    @keyframes orb-fade-in { to { opacity: 1; } }
    @keyframes orb-float {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(40px, 40px) scale(1.05); }
    }

    /* Central Hub */
    .intro-hub {
        position: relative; z-index: 10;
        display: flex; flex-direction: column; align-items: center;
    }

    /* Minimalist Rotating Rings */
    .intro-rings {
        position: relative; width: 120px; height: 120px;
        display: grid; place-items: center;
        margin-bottom: 40px;
    }
    .intro-ring {
        position: absolute; inset: 0; border-radius: 50%;
        border: 1px solid rgba(255,255,255,.05);
    }
    .intro-ring-inner {
        position: absolute; inset: 12px; border-radius: 50%;
        border: 1px dashed rgba(230,0,18,.3);
        animation: spin-slow 12s linear infinite;
    }
    .intro-ring-outer {
        position: absolute; inset: -16px; border-radius: 50%;
        border-top: 1px solid rgba(230,0,18,.7);
        border-right: 1px solid transparent;
        border-bottom: 1px solid transparent;
        border-left: 1px solid transparent;
        animation: spin-slow 4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }
    @keyframes spin-slow { 100% { transform: rotate(360deg); } }

    /* Logo inside rings */
    .intro-logo-text {
        font-size: 34px; font-weight: 300; letter-spacing: 1px;
        color: #fff;
        opacity: 0; transform: scale(0.8);
        animation: logo-pop 1.2s 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    .intro-logo-text strong { font-weight: 800; color: #e60012; }
    @keyframes logo-pop { to { opacity: 1; transform: scale(1); } }

    /* Fresh Typography */
    .intro-title {
        font-size: 15px; font-weight: 600; letter-spacing: 0.4em;
        text-transform: uppercase; color: #fff;
        margin-bottom: 12px;
        opacity: 0; transform: translateY(10px);
        animation: slide-up-fade 0.8s 0.8s forwards;
    }
    .intro-subtitle {
        font-size: 13px; font-weight: 400; color: #737373;
        letter-spacing: 0.05em; max-width: 320px; text-align: center; line-height: 1.6;
        opacity: 0; transform: translateY(10px);
        animation: slide-up-fade 0.8s 1s forwards;
    }
    @keyframes slide-up-fade { to { opacity: 1; transform: translateY(0); } }

    /* Ultra-sleek Loader */
    .intro-progress-container {
        position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
        background: rgba(255,255,255,0.03);
    }
    .intro-progress-bar {
        height: 100%; width: 0;
        background: #e60012;
        box-shadow: 0 0 15px rgba(230,0,18,0.5);
        animation: progress-fill 2.8s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    }
    @keyframes progress-fill {
        0% { width: 0; }
        40% { width: 40%; }
        70% { width: 85%; }
        100% { width: 100%; }
    }
    .intro-progress-text {
        position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
        font-size: 10px; font-weight: 600; letter-spacing: 0.25em;
        color: #555; text-transform: uppercase;
        opacity: 0; animation: fade-in 1s 1.2s forwards;
    }
    @keyframes fade-in { to { opacity: 1; } }

    /* ===================================================================
       LOGIN PAGE — STAGGERED ENTRANCE
       =================================================================== */
    .login-shell { opacity: 0; }
    .login-shell.revealed { opacity: 1; }

    .login-shell.revealed .login-brand {
        animation: login-brand-enter 1s .1s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes login-brand-enter {
        from { opacity: 0; transform: translateX(-30px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .login-shell.revealed .login-brand .brand-box {
        animation: login-el-enter .6s .3s cubic-bezier(.34,1.56,.64,1) both;
    }
    .login-shell.revealed .login-brand .eyebrow {
        animation: login-el-enter .5s .5s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .login-brand h1 {
        animation: login-el-enter .6s .6s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .login-brand > p:not(.eyebrow) {
        animation: login-el-enter .5s .8s cubic-bezier(.4,0,.2,1) both;
    }

    .login-shell.revealed .login-panel {
        animation: login-panel-enter .8s .2s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes login-panel-enter {
        from { opacity: 0; transform: translateX(30px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .login-shell.revealed .login-heading .eyebrow {
        animation: login-el-enter .5s .5s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .login-heading h2 {
        animation: login-el-enter .5s .6s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .login-heading > p:last-child {
        animation: login-el-enter .5s .7s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .stack-form label:nth-child(2) {
        animation: login-el-enter .5s .8s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .stack-form label:nth-child(3) {
        animation: login-el-enter .5s .9s cubic-bezier(.4,0,.2,1) both;
    }
    .login-shell.revealed .stack-form .primary-button {
        animation: login-el-enter .5s 1s cubic-bezier(.34,1.56,.64,1) both;
    }
    .login-shell.revealed .demo-accounts {
        animation: login-el-enter .5s 1.1s cubic-bezier(.4,0,.2,1) both;
    }

    @keyframes login-el-enter {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    </style>
</head>
<body class="login-page">

    <!-- ===== FRESH PREMIUM INTRO OVERLAY ===== -->
    <div class="intro-overlay" id="intro">
        <!-- Glassmorphism Orbs -->
        <div class="intro-orb intro-orb-1"></div>
        <div class="intro-orb intro-orb-2"></div>

        <div class="intro-hub">
            <div class="intro-rings">
                <div class="intro-ring"></div>
                <div class="intro-ring-inner"></div>
                <div class="intro-ring-outer"></div>
                <div class="intro-logo-text">I<strong>H</strong></div>
            </div>

            <div class="intro-title">IndiHome Field</div>
            <div class="intro-subtitle">Sistem Pencatatan & Monitoring Lapangan Terpadu</div>
        </div>

        <div class="intro-progress-text">Menginisiasi Workspace...</div>
        <div class="intro-progress-container">
            <div class="intro-progress-bar"></div>
        </div>
    </div>

    <!-- ===== LOGIN PAGE (hidden until intro finishes) ===== -->
    <main class="login-shell" id="loginShell">
        <section class="login-brand">
            <div class="premium-logo">
                <div class="logo-ring"></div>
                <div class="logo-text">I<strong>H</strong></div>
            </div>
            <p class="eyebrow">SISTEM KERJA TEKNISI</p>
            <h1>IndiHome<br>Field</h1>
            <p>Catat pekerjaan lapangan dan pantau rekap pendapatan tim dalam satu platform terpadu.</p>
        </section>

        <section class="login-panel">
            <div class="login-panel-inner">
                <div class="login-heading">
                    <p class="eyebrow">AKSES INTERNAL</p>
                    <h2>Masuk ke akun</h2>
                    <p>Gunakan akun pimpinan atau teknisi untuk melanjutkan.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="stack-form" id="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label>
                        <span>Username</span>
                        <input id="input-username" name="username" autocomplete="username" placeholder="Masukkan username" required autofocus>
                    </label>
                    <label>
                        <span>Password</span>
                        <input id="input-password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    </label>
                    <button id="btn-login" class="primary-button" type="submit">Masuk</button>
                </form>

                <div class="demo-accounts">
                    <p>Akun demo</p>
                    <div><span>Pimpinan</span><code>admin / admin123</code></div>
                    <div><span>Teknisi</span><code>andi / teknisi123</code></div>
                    <div><span>Teknisi</span><code>rizky / teknisi123</code></div>
                </div>
            </div>
        </section>
    </main>

    <script>
    (function() {
        var introDuration = 3800;
        var intro = document.getElementById('intro');
        var shell = document.getElementById('loginShell');

        // After the intro animation completes, fade out overlay & reveal login
        setTimeout(function() {
            intro.classList.add('fade-out');
            shell.classList.add('revealed');
        }, introDuration);

        // Clean up overlay from DOM after fade completes
        setTimeout(function() {
            intro.remove();
        }, introDuration + 900);

        /* ── Login Form Transition ── */
        document.getElementById('login-form').addEventListener('submit', function(){
            var ov = document.createElement('div');
            ov.className = 'page-transition-overlay';
            ov.style.opacity = '0';
            document.body.appendChild(ov);
            requestAnimationFrame(function(){ requestAnimationFrame(function(){
                ov.style.transition = 'opacity .35s cubic-bezier(.4,0,.2,1)';
                ov.style.opacity = '1';
            });});
        });

        /* ── Button Ripple ── */
        document.querySelectorAll('.primary-button, .secondary-button').forEach(function(btn){
            btn.addEventListener('click', function(e){
                var r = btn.getBoundingClientRect();
                var rp = document.createElement('span');
                rp.className = 'btn-ripple';
                var sz = Math.max(r.width, r.height) * 2;
                rp.style.width = rp.style.height = sz+'px';
                rp.style.left = (e.clientX - r.left - sz/2)+'px';
                rp.style.top = (e.clientY - r.top - sz/2)+'px';
                btn.appendChild(rp);
                setTimeout(function(){ rp.remove(); }, 700);
            });
        });
    })();
    </script>
</body>
</html>
