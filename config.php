<?php
date_default_timezone_set('Asia/Jakarta');

const JOB_VALUE = 125000;

session_start();

$dbHost   = 'sql202.infinityfree.com';
$dbPort   = '3306';
$dbName   = 'if0_42707757_indihomefield';
$dbUser   = 'if0_42707757';
$dbPass   = 'PASSWORD_ANDA_DISINI'; 

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ]);
} catch (PDOException $e) {
    die("<h1>KONEKSI DATABASE GAGAL</h1><p>Pastikan Username, Password, Nama Database, dan Host sudah benar.</p><br>Error: " . $e->getMessage());
}
try {
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) {
    die("<h1>TABEL BELUM ADA</h1><p>Koneksi ke database SUKSES, tapi tabel 'users' tidak ditemukan. Anda belum meng-import file <b>database.sql</b> ke dalam phpMyAdmin!</p><br>Error: " . $e->getMessage());
}

if ($userCount === 0) {
    $seed = $db->prepare('INSERT INTO users (name, nik, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $seed->execute(['Dewi Larasati', 'ADM-001', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'active']);
    $seed->execute(['Andi Pratama', 'TK-24017', 'andi', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi', 'active']);
    $seed->execute(['Rizky Saputra', 'TK-24022', 'rizky', password_hash('teknisi123', PASSWORD_DEFAULT), 'teknisi', 'active']);
    
    $seedwt = $db->prepare('INSERT INTO work_types (code, name, base_tariff, description, is_active) VALUES (?, ?, ?, ?, 1)');
    $seedwt->execute(['PDA', 'Pasang Baru PDA', 125000, 'Pekerjaan Pasang Baru PDA']);
    $seedwt->execute(['IH', 'IndiHome Standard', 125000, 'Pemasangan Layanan IndiHome']);
    $seedwt->execute(['HSI', 'High Speed Internet', 125000, 'Pemasangan Internet HSI']);
    $seedwt->execute(['DATIN', 'Data & Internet Corporate', 125000, 'Layanan Datin Korporat']);
    $seedwt->execute(['MOK', 'Migrasi OK / Perbaikan', 30000, 'Migrasi atau Perbaikan Kabel/Perangkat']);
}

function log_activity($db, $userId, $userName, $action, $details) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare('INSERT INTO activity_logs (user_id, user_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $userName, $action, $details, $ip]);
    } catch (\Throwable $t) {}
}

function get_all_work_types($db, $onlyActive = true) {
    $query = $onlyActive 
        ? 'SELECT * FROM work_types WHERE is_active = 1 ORDER BY code ASC'
        : 'SELECT * FROM work_types ORDER BY code ASC';
    return $db->query($query)->fetchAll();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    $user = current_user();
    if ($user === null) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function get_cycle_dates($month = null, $year = null) {
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

const GOOGLE_SHEETS_URL = 'https://script.google.com/macros/s/AKfycbyo6wiwsHb4V5ylZnNy5xabXtEba7SaOVB93615r7HZoCXggY8WoEfQ-TnKiveJ4Th0/exec'; // <-- Ganti dengan URL baru jika diperlukan

function send_to_google_sheets($payload) {
    try {
        if (empty(GOOGLE_SHEETS_URL)) return;
        $ch = curl_init(GOOGLE_SHEETS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $t) {}
}
?>
