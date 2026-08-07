<?php
declare(strict_types=1);

const JOB_VALUE = 125000;

$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

$sessionDir = $dataDir . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}
session_save_path($sessionDir);
session_start();

$db = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'indihome-field.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        nik TEXT NOT NULL UNIQUE,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ("admin", "teknisi")),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reporter_name TEXT NOT NULL,
        reporter_nik TEXT NOT NULL,
        work_type TEXT NOT NULL,
        work_order TEXT NOT NULL UNIQUE,
        customer_name TEXT NOT NULL,
        ps_date TEXT NOT NULL,
        base_amount INTEGER NOT NULL DEFAULT 125000,
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(created_by) REFERENCES users(id)
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS job_technicians (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INTEGER NOT NULL,
        technician_name TEXT NOT NULL,
        technician_nik TEXT NOT NULL,
        share_amount INTEGER NOT NULL,
        FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
    )'
);

$userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
    $seed = $db->prepare('INSERT INTO users (name, nik, username, password_hash, role) VALUES (?, ?, ?, ?, ?)');
    $seed->execute(['Dewi Larasati', 'ADM-001', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    $seed->execute(['Andi Pratama', 'TK-24017', 'andi', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi']);
    $seed->execute(['Rizky Saputra', 'TK-24022', 'rizky', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi']);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah(int|float $value): string
{
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
