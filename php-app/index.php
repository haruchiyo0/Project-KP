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
    <title>Login | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-brand">
            <span class="brand-box">IH</span>
            <p class="eyebrow">SISTEM KERJA TEKNISI</p>
            <h1>IndiHome Field</h1>
            <p>Catat pekerjaan lapangan dan pantau rekap tim dalam satu tempat.</p>
        </section>

        <section class="login-panel">
            <div class="login-heading">
                <p class="eyebrow">AKSES INTERNAL</p>
                <h2>Masuk ke akun</h2>
                <p>Gunakan akun pimpinan atau teknisi.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>Username</span>
                    <input name="username" autocomplete="username" placeholder="Masukkan username" required autofocus>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                </label>
                <button class="primary-button" type="submit">Masuk</button>
            </form>

            <div class="demo-accounts">
                <p>Akun awal</p>
                <div><span>Pimpinan</span><code>admin / admin123</code></div>
                <div><span>Teknisi</span><code>andi / teknisi123</code></div>
            </div>
        </section>
    </main>
</body>
</html>
