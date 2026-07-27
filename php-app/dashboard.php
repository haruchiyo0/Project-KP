<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
$flash = pull_flash();

if ($user['role'] === 'admin') {
    $jobs = $db->query(
        'SELECT j.*,
            GROUP_CONCAT(jt.technician_name || " (" || jt.technician_nik || ")", " | ") AS technicians,
            COUNT(jt.id) AS technician_count
         FROM jobs j
         LEFT JOIN job_technicians jt ON jt.job_id = j.id
         GROUP BY j.id
         ORDER BY j.ps_date DESC, j.id DESC'
    )->fetchAll();
    $jobCount = (int) $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $totalIncome = (int) $db->query('SELECT COALESCE(SUM(base_amount), 0) FROM jobs')->fetchColumn();
    $technicianCount = (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "teknisi"')->fetchColumn();
} else {
    $statement = $db->prepare(
        'SELECT j.*, jt.share_amount,
            GROUP_CONCAT(all_jt.technician_name || " (" || all_jt.technician_nik || ")", " | ") AS technicians,
            COUNT(all_jt.id) AS technician_count
         FROM jobs j
         JOIN job_technicians jt ON jt.job_id = j.id AND jt.technician_nik = ?
         JOIN job_technicians all_jt ON all_jt.job_id = j.id
         GROUP BY j.id, jt.share_amount
         ORDER BY j.ps_date DESC, j.id DESC'
    );
    $statement->execute([$user['nik']]);
    $jobs = $statement->fetchAll();
    $jobCount = count($jobs);
    $totalIncome = array_sum(array_map(static fn(array $job): int => (int) $job['share_amount'], $jobs));
    $technicianCount = 1;
}

$typeCounts = [];
foreach ($jobs as $job) {
    $typeCounts[$job['work_type']] = ($typeCounts[$job['work_type']] ?? 0) + 1;
}
arsort($typeCounts);
$topType = array_key_first($typeCounts) ?? '-';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php"><span class="brand-box">IH</span><span><strong>IndiHome Field</strong><small>Monitor tim lapangan</small></span></a>
        <nav>
            <a class="active" href="dashboard.php"><span>01</span>Ringkasan</a>
            <a href="job-create.php"><span>02</span>Input pekerjaan</a>
        </nav>
        <div class="side-user">
            <span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></span>
            <div><strong><?= e($user['name']) ?></strong><small><?= e($user['nik']) ?> - <?= e(ucfirst($user['role'])) ?></small></div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div><p class="eyebrow">DASHBOARD <?= e(strtoupper($user['role'])) ?></p><h1>Selamat datang, <?= e(explode(' ', $user['name'])[0]) ?></h1></div>
            <div class="header-actions"><a class="secondary-button" href="logout.php">Keluar</a><a class="primary-button" href="job-create.php">+ Catat pekerjaan</a></div>
        </header>

        <?php if ($flash): ?>
            <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <section class="stats-grid">
            <article class="stat-card featured"><span>Pekerjaan tercatat</span><strong><?= $jobCount ?></strong><small><?= $user['role'] === 'admin' ? 'Seluruh tim' : 'Pekerjaan yang Anda kerjakan' ?></small></article>
            <article class="stat-card"><span><?= $user['role'] === 'admin' ? 'Total pendapatan tim' : 'Pendapatan Anda' ?></span><strong><?= rupiah($totalIncome) ?></strong><small>Dihitung dari pekerjaan yang tercatat</small></article>
            <article class="stat-card"><span>Jenis terbanyak</span><strong><?= e($topType) ?></strong><small>Dari lima kategori pekerjaan</small></article>
            <article class="stat-card"><span><?= $user['role'] === 'admin' ? 'Teknisi terdaftar' : 'Status akun' ?></span><strong><?= $user['role'] === 'admin' ? $technicianCount : 'Aktif' ?></strong><small>Data tersimpan di database</small></article>
        </section>

        <section class="panel table-panel">
            <div class="panel-heading"><div><p class="eyebrow">REKAP PEKERJAAN</p><h2><?= $user['role'] === 'admin' ? 'Seluruh pekerjaan tim' : 'Pekerjaan dan pendapatan Anda' ?></h2></div><a class="text-link" href="job-create.php">Tambah data</a></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Work Order / Tanggal PS</th><th>Pelanggan</th><th>Jenis</th><th>Teknisi</th><th><?= $user['role'] === 'admin' ? 'Pembagian' : 'Pendapatan Anda' ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><strong><?= e($job['work_order']) ?></strong><small><?= e(date('d M Y', strtotime($job['ps_date']))) ?></small></td>
                            <td><strong><?= e($job['customer_name']) ?></strong><small>Pelapor: <?= e($job['reporter_name']) ?> (<?= e($job['reporter_nik']) ?>)</small></td>
                            <td><span class="type-badge"><?= e($job['work_type']) ?></span></td>
                            <td><?= e($job['technicians']) ?></td>
                            <td><strong class="money"><?= rupiah($user['role'] === 'admin' ? ((int) $job['base_amount'] / max(1, (int) $job['technician_count'])) : (int) $job['share_amount']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$jobs): ?><tr><td colspan="5" class="empty">Belum ada pekerjaan yang tercatat.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
