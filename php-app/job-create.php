<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
$types = ['PDA', 'IH', 'HSI', 'PT2', 'EXPAND ODP'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reporterName = trim((string) ($_POST['reporter_name'] ?? ''));
    $reporterNik = trim((string) ($_POST['reporter_nik'] ?? ''));
    $workType = trim((string) ($_POST['work_type'] ?? ''));
    $workOrder = trim((string) ($_POST['work_order'] ?? ''));
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $psDate = trim((string) ($_POST['ps_date'] ?? ''));
    $technicians = [];

    for ($number = 1; $number <= 2; $number++) {
        $name = trim((string) ($_POST["technician_{$number}_name"] ?? ''));
        $nik = trim((string) ($_POST["technician_{$number}_nik"] ?? ''));
        if ($name !== '' || $nik !== '') {
            if ($name === '' || $nik === '') {
                $error = "Nama dan NIK Teknisi {$number} harus diisi lengkap.";
                break;
            }
            $technicians[] = ['name' => $name, 'nik' => $nik];
        }
    }

    if (!$error && (!$reporterName || !$reporterNik || !$workOrder || !$customerName || !$psDate || !in_array($workType, $types, true))) {
        $error = 'Lengkapi seluruh data pekerjaan yang wajib diisi.';
    }
    if (!$error && count($technicians) === 0) {
        $error = 'Minimal satu teknisi harus diisi.';
    }
    if (!$error && count(array_unique(array_column($technicians, 'nik'))) !== count($technicians)) {
        $error = 'NIK Teknisi 1 dan Teknisi 2 tidak boleh sama.';
    }

    if (!$error) {
        try {
            $db->beginTransaction();
            $insertJob = $db->prepare(
                'INSERT INTO jobs (reporter_name, reporter_nik, work_type, work_order, customer_name, ps_date, base_amount, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertJob->execute([$reporterName, $reporterNik, $workType, $workOrder, $customerName, $psDate, JOB_VALUE, $user['id']]);
            $jobId = (int) $db->lastInsertId();
            $share = intdiv(JOB_VALUE, count($technicians));
            $insertTechnician = $db->prepare('INSERT INTO job_technicians (job_id, technician_name, technician_nik, share_amount) VALUES (?, ?, ?, ?)');
            foreach ($technicians as $technician) {
                $insertTechnician->execute([$jobId, $technician['name'], $technician['nik'], $share]);
            }
            $db->commit();
            flash('success', 'Pekerjaan berhasil disimpan. Setiap teknisi mendapatkan ' . rupiah($share) . '.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'Nomor work order sudah pernah digunakan.' : 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_POST[$key] ?? $default));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Pekerjaan | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php"><span class="brand-box">IH</span><span><strong>IndiHome Field</strong><small>Monitor tim lapangan</small></span></a>
        <nav><a href="dashboard.php"><span>01</span>Ringkasan</a><a class="active" href="job-create.php"><span>02</span>Input pekerjaan</a></nav>
        <div class="side-user"><span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['nik']) ?> - <?= e(ucfirst($user['role'])) ?></small></div></div>
    </aside>

    <main class="main-content">
        <header class="topbar"><div><p class="eyebrow">FORM PEKERJAAN</p><h1>Catat pekerjaan baru</h1></div><a class="secondary-button" href="dashboard.php">Kembali</a></header>

        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <div class="form-layout">
            <form method="post" class="panel work-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-section"><span>01</span><div><h2>Data pelapor</h2><p>Identitas orang yang memasukkan laporan.</p></div></div>
                <div class="form-grid">
                    <label><span>Nama</span><input name="reporter_name" value="<?= old('reporter_name', $user['name']) ?>" required></label>
                    <label><span>NIK</span><input name="reporter_nik" value="<?= old('reporter_nik', $user['nik']) ?>" required></label>
                    <label class="full"><span>Jenis</span><select name="work_type" required><?php foreach ($types as $type): ?><option <?= old('work_type', 'PDA') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label>
                </div>

                <div class="form-section"><span>02</span><div><h2>Data teknisi</h2><p>Teknisi kedua boleh dikosongkan jika pekerjaan dilakukan sendiri.</p></div></div>
                <div class="form-grid">
                    <label><span>Teknisi 1 - Nama</span><input name="technician_1_name" value="<?= old('technician_1_name', $user['role'] === 'teknisi' ? $user['name'] : '') ?>" required></label>
                    <label><span>Teknisi 1 - NIK</span><input name="technician_1_nik" value="<?= old('technician_1_nik', $user['role'] === 'teknisi' ? $user['nik'] : '') ?>" required></label>
                    <label><span>Teknisi 2 - Nama</span><input name="technician_2_name" value="<?= old('technician_2_name') ?>" placeholder="Opsional"></label>
                    <label><span>Teknisi 2 - NIK</span><input name="technician_2_nik" value="<?= old('technician_2_nik') ?>" placeholder="Opsional"></label>
                </div>

                <div class="form-section"><span>03</span><div><h2>Data pelanggan dan PS</h2><p>Pastikan nomor work order belum pernah digunakan.</p></div></div>
                <div class="form-grid">
                    <label><span>Work Order</span><input name="work_order" value="<?= old('work_order') ?>" placeholder="Contoh: WO-260720-019" required></label>
                    <label><span>Nama Pelanggan</span><input name="customer_name" value="<?= old('customer_name') ?>" required></label>
                    <label class="full"><span>Tanggal PS</span><input type="date" name="ps_date" value="<?= old('ps_date', date('Y-m-d')) ?>" required></label>
                </div>

                <div class="form-footer"><p>Pendapatan teknisi dihitung otomatis setelah data disimpan.</p><button class="primary-button" type="submit">Simpan pekerjaan</button></div>
            </form>

            <aside class="form-aside">
                <article class="panel calculator-card"><p class="eyebrow">REKAP OTOMATIS</p><h2>Pembagian pendapatan</h2><p class="helper-copy">Sistem menghitung bagian setiap teknisi berdasarkan jumlah teknisi yang tercatat.</p></article>
                <article class="info-card"><strong>Catatan</strong><p>Pendapatan teknisi dihitung dari NIK yang tercatat pada pekerjaan.</p></article>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
