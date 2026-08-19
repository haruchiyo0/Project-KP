<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

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

// MySQL & SQLite Configuration options (Can be set via env or modified below)
$dbDriver = getenv('DB_DRIVER') ?: 'sqlite'; // Change to 'mysql' to use MySQL Server / phpMyAdmin
$dbHost   = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort   = getenv('DB_PORT') ?: '3306';
$dbName   = getenv('DB_NAME') ?: 'indihome_field';
$dbUser   = getenv('DB_USER') ?: 'root';
$dbPass   = getenv('DB_PASS') ?: '';

if ($dbDriver === 'mysql') {
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $db = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]);
    } catch (PDOException $e) {
        // Fallback to SQLite if MySQL is not available
        $db = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'indihome-field.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
    }
} else {
    $db = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'indihome-field.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
}

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

// Auto-migrate additional user fields if missing
try { $db->exec('ALTER TABLE users ADD COLUMN email TEXT DEFAULT ""'); } catch (PDOException $e) {}
try { $db->exec('ALTER TABLE users ADD COLUMN phone TEXT DEFAULT ""'); } catch (PDOException $e) {}
try { $db->exec('ALTER TABLE users ADD COLUMN status TEXT DEFAULT "active" CHECK(status IN ("active", "inactive"))'); } catch (PDOException $e) {}

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

// Auto-migrate additional job fields if missing
try { $db->exec('ALTER TABLE jobs ADD COLUMN no_inet TEXT DEFAULT ""'); } catch (PDOException $e) {}
try { $db->exec('ALTER TABLE jobs ADD COLUMN status TEXT DEFAULT "Selesai"'); } catch (PDOException $e) {}
try { $db->exec('ALTER TABLE jobs ADD COLUMN location_notes TEXT DEFAULT ""'); } catch (PDOException $e) {}

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

$db->exec(
    'CREATE TABLE IF NOT EXISTS work_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        base_tariff INTEGER NOT NULL DEFAULT 125000,
        description TEXT DEFAULT "",
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        no_inet TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        address TEXT DEFAULT "",
        phone TEXT DEFAULT "",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        user_name TEXT NOT NULL,
        action TEXT NOT NULL,
        details TEXT NOT NULL,
        ip_address TEXT DEFAULT "",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS app_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL,
        description TEXT DEFAULT ""
    )'
);

$userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
    $seed = $db->prepare('INSERT INTO users (name, nik, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $seed->execute(['Dewi Larasati', 'ADM-001', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'active']);
    $seed->execute(['Andi Pratama', 'TK-24017', 'andi', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi', 'active']);
    $seed->execute(['Rizky Saputra', 'TK-24022', 'rizky', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi', 'active']);
}

$workTypeCount = (int) $db->query('SELECT COUNT(*) FROM work_types')->fetchColumn();
if ($workTypeCount === 0) {
    $seedwt = $db->prepare('INSERT INTO work_types (code, name, base_tariff, description, is_active) VALUES (?, ?, ?, ?, 1)');
    $seedwt->execute(['PDA', 'Pasang Baru PDA', 125000, 'Pekerjaan Pasang Baru PDA']);
    $seedwt->execute(['IH', 'IndiHome Standard', 125000, 'Pemasangan Layanan IndiHome']);
    $seedwt->execute(['HSI', 'High Speed Internet', 125000, 'Pemasangan Internet HSI']);
    $seedwt->execute(['DATIN', 'Data & Internet Corporate', 125000, 'Layanan Datin Korporat']);
    $seedwt->execute(['MOK', 'Migrasi OK / Perbaikan', 30000, 'Migrasi atau Perbaikan Kabel/Perangkat']);

}

function log_activity(PDO $db, ?int $userId, string $userName, string $action, string $details): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare('INSERT INTO activity_logs (user_id, user_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $userName, $action, $details, $ip]);
    } catch (\Throwable $t) {
        // Silent fallback
    }
}

function get_all_work_types(PDO $db, bool $onlyActive = true): array
{
    $query = $onlyActive 
        ? 'SELECT * FROM work_types WHERE is_active = 1 ORDER BY code ASC'
        : 'SELECT * FROM work_types ORDER BY code ASC';
    return $db->query($query)->fetchAll();
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

function get_cycle_dates(?int $month = null, ?int $year = null): array
{
    $currentDay = (int) date('d');
    $currentMonth = (int) date('m');
    $currentYear = (int) date('Y');

    if ($month === null || $year === null) {
        if ($currentDay >= 26) {
            $month = $currentMonth + 1;
            $year = $currentYear;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        } else {
            $month = $currentMonth;
            $year = $currentYear;
        }
    }

    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }

    return [
        'start' => sprintf('%04d-%02d-26', $prevYear, $prevMonth),
        'end'   => sprintf('%04d-%02d-25', $year, $month),
        'month' => $month,
        'year'  => $year
    ];
}

const GOOGLE_SHEETS_URL = 'https://script.google.com/macros/s/AKfycbyo6wiwsHb4V5ylZnNy5xabXtEba7SaOVB93615r7HZoCXggY8WoEfQ-TnKiveJ4Th0/exec';

function send_to_google_sheets(array $payload): void
{
    try {
        if (empty(GOOGLE_SHEETS_URL)) {
            return;
        }
        $ch = curl_init(GOOGLE_SHEETS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $t) {
        // Log error silently
    }
}
