<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = require_login();
$flash = pull_flash();

$allTypes = ['PDA', 'IH', 'HSI', 'DATIN', 'MOK'];

$cycle = get_cycle_dates();
$startDate = $cycle['start'];
$endDate = $cycle['end'];

if ($user['role'] === 'admin') {
    $statement = $db->prepare(
        'SELECT j.*,
            GROUP_CONCAT(CONCAT(jt.technician_name, " (", jt.technician_nik, ")") SEPARATOR " | ") AS technicians,
            COUNT(jt.id) AS technician_count
         FROM jobs j
         LEFT JOIN job_technicians jt ON jt.job_id = j.id
         WHERE j.ps_date >= :start_date AND j.ps_date <= :end_date
         GROUP BY j.id
         ORDER BY j.ps_date DESC, j.id DESC'
    );
    $statement->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $jobs = $statement->fetchAll();
    
    $jobCount = count($jobs);
    $totalIncome = array_sum(array_column($jobs, 'base_amount'));
    $technicianCount = (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "teknisi"')->fetchColumn();


    $typeCounts = [];
    foreach ($allTypes as $t) { $typeCounts[$t] = 0; }
    foreach ($jobs as $job) { $typeCounts[$job['work_type']] = ($typeCounts[$job['work_type']] ?? 0) + 1; }
    $maxTypeCount = max(1, max($typeCounts));


    $techPerformanceStmt = $db->prepare('
        SELECT u.name, COUNT(jt.id) as job_count, SUM(jt.share_amount) as total_revenue
        FROM job_technicians jt
        JOIN jobs j ON j.id = jt.job_id
        JOIN users u ON u.nik = jt.technician_nik
        WHERE j.ps_date >= :start_date AND j.ps_date <= :end_date
        GROUP BY u.nik
        ORDER BY job_count DESC
    ');
    $techPerformanceStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $techPerformance = $techPerformanceStmt->fetchAll();


    $monthlyTrendStmt = $db->query('
        SELECT DATE_FORMAT(ps_date, "%Y-%m") as month, COUNT(*) as total_jobs
        FROM jobs
        GROUP BY month
        ORDER BY month DESC
        LIMIT 6
    ');
    $monthlyTrendRaw = $monthlyTrendStmt->fetchAll();
    $monthlyTrend = array_reverse($monthlyTrendRaw); // Chronological order
} else {
    $statement = $db->prepare(
        'SELECT j.*, COALESCE(jt.share_amount, 0) AS share_amount,
            GROUP_CONCAT(CONCAT(all_jt.technician_name, " (", all_jt.technician_nik, ")") SEPARATOR " | ") AS technicians,
            COUNT(all_jt.id) AS technician_count
         FROM jobs j
         LEFT JOIN job_technicians jt ON jt.job_id = j.id AND jt.technician_nik = :nik
         JOIN job_technicians all_jt ON all_jt.job_id = j.id
         WHERE (j.created_by = :userId OR jt.id IS NOT NULL)
           AND j.ps_date >= :start_date AND j.ps_date <= :end_date
         GROUP BY j.id, jt.share_amount
         ORDER BY j.ps_date DESC, j.id DESC'
    );
    $statement->execute([
        'nik' => $user['nik'], 
        'userId' => $user['id'],
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
    $jobs = $statement->fetchAll();
    $jobCount = count($jobs);
    $totalIncome = array_sum(array_column($jobs, 'share_amount'));
    $technicianCount = 1;


    $avgIncome = $jobCount > 0 ? intdiv($totalIncome, $jobCount) : 0;
}

function typeClass(string $type): string {
    return match($type) {
        'PDA' => 'type-pda',
        'IH' => 'type-ih',
        'HSI' => 'type-hsi',
        'DATIN' => 'type-datin',
        'MOK' => 'type-mok',
        default => 'type-pda',
    };
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dashboard monitoring pekerjaan dan pendapatan tim teknisi IndiHome.">
    <title>Dashboard | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="page-transition-overlay pt-enter" id="pt-overlay"></div>

<div class="mobile-topbar">
    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open Menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <a class="brand" href="dashboard.php">
        <div class="premium-logo"><div class="logo-ring"></div><div class="logo-text">I<strong>H</strong></div></div>
    </a>
    <div style="width:40px"></div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <a class="brand" href="dashboard.php">
                <div class="premium-logo">
                    <div class="logo-ring"></div>
                    <div class="logo-text">I<strong>H</strong></div>
                </div>
                <span>
                    <strong>IndiHome Field</strong>
                    <small>Monitor tim lapangan</small>
                </span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>
        <nav>
            <a class="active" href="dashboard.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                <span class="nav-text">Ringkasan</span>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="reports.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span class="nav-text">Laporan</span>
                </a>
            <?php else: ?>
                <a href="job-create.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <span class="nav-text">Input</span>
                </a>
                <a href="riwayat.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    <span class="nav-text">Riwayat</span>
                </a>
            <?php endif; ?>
        </nav>
        <div class="side-user">
            <span class="avatar <?= $user['role'] === 'admin' ? 'avatar-red' : 'avatar-blue' ?>"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></span>
            <div>
                <strong><?= e($user['name']) ?></strong>
                <small><?= e($user['nik']) ?></small>
                <span class="role-badge <?= $user['role'] === 'admin' ? 'role-badge-admin' : 'role-badge-teknisi' ?>"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>
    </aside>

    <main class="main-content">

    <?php if ($flash): ?>
        <div style="padding: 20px 48px 0;">
            <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'admin'): ?>
    <!-- ================= ADMIN DASHBOARD ================= -->

    <div class="admin-hero">
        <div class="admin-hero-top">
            <div>
                <p class="eyebrow">DASHBOARD PIMPINAN</p>
                <h1><span id="dynGreeting">Selamat datang</span>, <?= e(explode(' ', $user['name'])[0]) ?></h1>
                <p class="hero-sub">Pantau seluruh aktivitas pekerjaan dan performa tim teknisi lapangan Anda.</p>
            </div>
            <div class="header-actions">
                <div id="liveClock" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); font-size: 13px; color: #94a3b8;">
                    <span id="clockDate" style="font-weight: 500;">Memuat...</span>
                    <span style="color: rgba(255,255,255,0.15);">|</span>
                    <strong id="clockTime" style="color: #e2e8f0; font-family: 'Outfit', sans-serif; font-weight: 600; letter-spacing: 0.5px;">--:--:--</strong>
                </div>
                <a id="btn-logout" class="secondary-button" href="logout.php">Keluar</a>
                <a id="btn-reports" class="primary-button" href="reports.php">Laporan</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat highlight">
                <span>Total pekerjaan</span>
                <strong><?= $jobCount ?></strong>
                <small>Bulan ini (26 ke 25)</small>
            </div>
            <div class="hero-stat money">
                <span>Total pendapatan</span>
                <strong><?= rupiah($totalIncome) ?></strong>
                <small>Bulan ini (26 ke 25)</small>
            </div>
            <div class="hero-stat">
                <span>Teknisi aktif</span>
                <strong><?= $technicianCount ?></strong>
                <small>Terdaftar di sistem</small>
            </div>
        </div>
        <div class="hero-edge-fade"></div>
    </div>

    <div class="admin-body">
        <!-- Charts Section -->
        <section class="chart-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
            <!-- Bar Chart Panel -->
            <div style="background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); transition: box-shadow 0.3s ease, transform 0.3s ease;" onmouseenter="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.04), 0 12px 32px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)'" onmouseleave="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.06)'; this.style.transform='translateY(0)'">
                <div style="background: linear-gradient(135deg, #991b1b 0%, #b91c1c 100%); padding: 18px 24px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <h2 style="margin: 0; color: rgba(255,255,255,0.95); font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 600;">Pekerjaan Terbanyak Bulan Ini</h2>
                </div>
                <div style="padding: 24px;">
                    <div style="position: relative; height: 280px; width: 100%;">
                        <canvas id="techChart"></canvas>
                    </div>
                </div>
                <!-- Summary cards below bar chart -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; background: #f1f5f9; border-top: 1px solid #f1f5f9;">
                    <?php 
                    $topTwo = array_slice($techPerformance, 0, 2);
                    foreach ($topTwo as $i => $tp): 
                    ?>
                    <div style="background: #fff; padding: 16px 20px;">
                        <strong style="display: block; font-size: 1.5rem; font-family: 'Outfit', sans-serif; font-weight: 800; color: #0f172a;"><?= (int) $tp['job_count'] ?></strong>
                        <span style="font-size: 13px; color: #64748b;"><?= e($tp['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Doughnut Chart Panel -->
            <div style="background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); transition: box-shadow 0.3s ease, transform 0.3s ease;" onmouseenter="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.04), 0 12px 32px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)'" onmouseleave="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.06)'; this.style.transform='translateY(0)'">
                <div style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); padding: 18px 24px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                    <h2 style="margin: 0; color: rgba(255,255,255,0.95); font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 600;">Distribusi Pekerjaan per Teknisi</h2>
                </div>
                <div style="padding: 24px;">
                    <div style="position: relative; height: 280px; width: 100%;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <!-- Summary cards below doughnut -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; background: #f1f5f9; border-top: 1px solid #f1f5f9;">
                    <?php foreach (array_slice($techPerformance, 0, 4) as $tp): ?>
                    <div style="background: #fff; padding: 14px 20px;">
                        <strong style="display: block; font-size: 1.3rem; font-family: 'Outfit', sans-serif; font-weight: 800; color: #0f172a;"><?= (int) $tp['job_count'] ?></strong>
                        <span style="font-size: 12px; color: #64748b;"><?= e(explode(' ', $tp['name'])[0]) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Leaderboard Teknisi -->
        <section class="panel table-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">LEADERBOARD</p>
                    <h2>Performa Teknisi (Total Pekerjaan / PSB)</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Teknisi</th>
                        <th>Total Pekerjaan (PSB)</th>
                        <th>Estimasi Bagian Pendapatan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $rank = 1;
                    $topJobCount = isset($techPerformance[0]) ? $techPerformance[0]['job_count'] : 1;
                    foreach ($techPerformance as $tech): 
                        $isTop3 = $rank <= 3;
                        $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
                    ?>
                        <tr style="<?= $isTop3 ? 'background: rgba(56, 189, 248, 0.03);' : '' ?>">
                            <td style="font-weight: bold; font-size: 1.1rem; color: <?= $isTop3 ? 'var(--primary)' : 'inherit' ?>;">
                                <?= $medal ?> #<?= $rank ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span class="avatar avatar-blue" style="width: 32px; height: 32px; font-size: 12px;">
                                        <?= e(strtoupper(substr($tech['name'], 0, 2))) ?>
                                    </span>
                                    <strong><?= e($tech['name']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <strong><?= $tech['job_count'] ?></strong>
                                    <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; max-width: 150px;">
                                        <div style="height: 100%; border-radius: 3px; background: var(--primary); width: <?= min(100, ($tech['job_count']/max(1, $topJobCount)) * 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><strong class="money"><?= rupiah((int)$tech['total_revenue']) ?></strong></td>
                        </tr>
                    <?php 
                    $rank++;
                    endforeach; 
                    ?>
                    <?php if (!$techPerformance): ?><tr><td colspan="4" class="empty">Belum ada data performa bulan ini.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Distribution cards -->
        <section class="distribution-section">
            <p class="eyebrow">DISTRIBUSI PEKERJAAN</p>
            <h2>Berdasarkan jenis layanan</h2>
            <div class="distribution-grid">
                <?php foreach ($typeCounts as $type => $count): ?>
                <div class="dist-card">
                    <div class="dist-label"><?= e($type) ?></div>
                    <strong class="dist-count"><?= $count ?></strong>
                    <span class="dist-bar"><span class="dist-bar-fill" style="width: <?= $maxTypeCount > 0 ? round(($count / $maxTypeCount) * 100) : 0 ?>%"></span></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Full table -->
        <section class="panel table-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">DATA LENGKAP</p>
                    <h2>Pekerjaan tim bulan ini</h2>
                </div>
                <a class="text-link" href="reports.php">Lihat semua →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Work Order / Tanggal PS</th>
                        <th>Pelanggan</th>
                        <th>Jenis</th>
                        <th>Teknisi</th>
                        <th>Pembagian / Teknisi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr class="job-row" data-json="<?= e(json_encode($job)) ?>">
                            <td><strong><?= e($job['work_order']) ?></strong><small><?= e(date('d M Y', strtotime($job['ps_date']))) ?></small></td>
                            <td><strong><?= e($job['customer_name']) ?></strong><small>Pelapor: <?= e($job['reporter_name']) ?> (<?= e($job['reporter_nik']) ?>)</small></td>
                            <td><span class="type-badge <?= typeClass($job['work_type']) ?>"><?= e($job['work_type']) ?></span></td>
                            <td><?= e($job['technicians']) ?></td>
                            <td><strong class="money"><?= rupiah((int) $job['base_amount'] / max(1, (int) $job['technician_count'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$jobs): ?><tr><td colspan="5" class="empty">Belum ada pekerjaan yang tercatat.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <?php else: ?>
    <!-- ================= TEKNISI DASHBOARD ================= -->

    <div class="tech-hero">
        <!-- Decorative transparent illustrations -->
        <svg class="tech-hero-deco tech-hero-deco-1" width="220" height="220" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
        <svg class="tech-hero-deco tech-hero-deco-2" width="180" height="180" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>

        <div class="tech-hero-top">
            <div class="tech-profile">
                <div class="tech-avatar"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></div>
                <div class="tech-profile-info">
                    <h1><?= e($user['name']) ?></h1>
                    <p><?= e($user['nik']) ?></p>
                    <span class="role-badge-tech">Teknisi Aktif</span>
                </div>
            </div>
            <div class="header-actions" style="display: flex; align-items: center; gap: 12px;">
                <div id="liveClock" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); font-size: 13px; color: #94a3b8;">
                    <span id="clockDate" style="font-weight: 500;">Memuat...</span>
                    <span style="color: rgba(255,255,255,0.15);">|</span>
                    <strong id="clockTime" style="color: #e2e8f0; font-family: 'Outfit', sans-serif; font-weight: 600; letter-spacing: 0.5px;">--:--:--</strong>
                </div>
                <a id="btn-logout" class="secondary-button" href="logout.php" style="color: #cbd5e1; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); border-radius: 10px;">Keluar</a>
                <a id="btn-add-job" class="primary-button" href="job-create.php">+ Catat pekerjaan</a>
            </div>
        </div>

        <div class="tech-earnings-row">
            <div class="earning-card earning-highlight">
                <span>Total pendapatan Anda</span>
                <strong><?= rupiah($totalIncome) ?></strong>
                <small>Bulan ini (26 ke 25)</small>
            </div>
            <div class="earning-card earning-jobs">
                <span>Pekerjaan Anda</span>
                <strong><?= $jobCount ?></strong>
                <small>Bulan ini (26 ke 25)</small>
            </div>
            <div class="earning-card">
                <span>Rata-rata per pekerjaan</span>
                <strong><?= rupiah($avgIncome) ?></strong>
                <small>Bulan ini (26 ke 25)</small>
            </div>
        </div>
        <div class="hero-edge-fade"></div>
    </div>

    <div class="tech-body">
        <div class="job-cards-header">
            <div>
                <p class="eyebrow">RIWAYAT PEKERJAAN</p>
                <h2>Pekerjaan & pendapatan Anda</h2>
            </div>
            <a class="text-link" href="job-create.php">+ Tambah data</a>
        </div>

        <?php if ($jobs): ?>
        <div class="job-cards-grid">
            <?php foreach ($jobs as $job): ?>
            <div class="job-card" data-json="<?= e(json_encode($job)) ?>">
                <div class="job-card-top">
                    <div>
                        <div class="job-card-wo"><?= e($job['work_order']) ?></div>
                        <div class="job-card-date"><?= e(date('d M Y', strtotime($job['ps_date']))) ?></div>
                    </div>
                    <span class="job-card-type <?= typeClass($job['work_type']) ?>"><?= e($job['work_type']) ?></span>
                </div>
                <div class="job-card-details">
                    <div class="job-card-detail">
                        <label><span>Pelanggan</span><strong><?= e($job['customer_name']) ?></strong></label>
                    </div>
                    <div class="job-card-detail">
                        <label><span>Teknisi</span><strong><?= (int) $job['technician_count'] ?> orang</strong></label>
                    </div>
                </div>
                <div class="job-card-earning">
                    <span>Pendapatan Anda</span>
                    <strong><?= rupiah((int) $job['share_amount']) ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>Belum ada pekerjaan</h3>
            <p>Anda belum memiliki riwayat pekerjaan. Mulai catat pekerjaan pertama Anda.</p>
            <a class="primary-button" href="job-create.php">+ Catat pekerjaan baru</a>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    </main>
</div>
<script>
(function(){
    /* Sidebar Toggle */
    var toggleBtn = document.getElementById('sidebarToggleBtn');
    var layout = document.querySelector('.app-layout');
    if (toggleBtn && layout) {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            layout.classList.add('collapsed');
        }
        toggleBtn.addEventListener('click', function() {
            layout.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', layout.classList.contains('collapsed'));
        });
    }

    /* Page Transition */
    var pt = document.getElementById('pt-overlay');
    document.addEventListener('click', function(e){
        var a = e.target.closest('a[href]');
        if (!a) return;
        var h = a.getAttribute('href');
        if (!h || h.charAt(0)==='#' || h.indexOf('javascript')===0 || a.target==='_blank') return;
        e.preventDefault();
        pt.className = 'page-transition-overlay pt-exit';
        setTimeout(function(){ window.location.href = h; }, 300);
    });
    window.addEventListener('pageshow', function(e){
        if (e.persisted) pt.className = 'page-transition-overlay pt-enter';
    });

    /* Animated Counters */
    function countUp(el){
        var raw = el.textContent.trim();
        var isRp = raw.indexOf('Rp') > -1;
        var target = parseInt(raw.replace(/[^0-9]/g,''),10) || 0;
        if (!target) return;
        var dur = 1800, t0 = null;
        el.textContent = isRp ? 'Rp 0' : '0';
        function frame(ts){
            if (!t0) t0 = ts;
            var p = Math.min((ts-t0)/dur, 1);
            p = 1 - Math.pow(1-p, 3);
            var v = Math.round(target * p);
            el.textContent = isRp ? 'Rp ' + v.toLocaleString('id-ID') : v.toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(frame);
            else el.classList.add('counter-done');
        }
        requestAnimationFrame(frame);
    }
    document.querySelectorAll('.hero-stat strong, .earning-card strong, .dist-count').forEach(countUp);

    /* 3D Card Tilt */
    document.querySelectorAll('.dist-card, .job-card, .earning-card').forEach(function(c){
        c.addEventListener('mousemove', function(e){
            var r = c.getBoundingClientRect();
            var x = (e.clientX - r.left)/r.width - .5;
            var y = (e.clientY - r.top)/r.height - .5;
            c.style.transform = 'perspective(600px) rotateY('+x*8+'deg) rotateX('+(-y*8)+'deg) translateY(-3px)';
            c.style.boxShadow = '0 20px 30px rgba(0,0,0,.1)';
        });
        c.addEventListener('mouseleave', function(){
            c.style.transform = '';
            c.style.boxShadow = '';
        });
    });

    /* Scroll Reveal */
    var sr = document.querySelectorAll('.admin-body .dist-card, .admin-body .table-panel, .tech-body .job-card');
    if ('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(en){
                if (en.isIntersecting){ en.target.classList.add('in-view'); io.unobserve(en.target); }
            });
        },{ threshold:.15 });
        sr.forEach(function(el){ io.observe(el); });
    } else {
        sr.forEach(function(el){ el.classList.add('in-view'); });
    }

    /* Toast Notification */
    var alertEl = document.querySelector('.alert');
    if (alertEl){
        var ok = alertEl.classList.contains('success');
        var msg = alertEl.textContent.trim();
        alertEl.parentElement.style.display = 'none';
        var tc = document.createElement('div');
        tc.className = 'toast-container';
        var t = document.createElement('div');
        t.className = 'toast ' + (ok ? 'success' : 'error');
        t.innerHTML =
            '<div class="toast-body"><div class="toast-icon">'+(ok?'✓':'✕')+'</div>'+
            '<div class="toast-text"><strong>'+(ok?'Berhasil!':'Terjadi Kesalahan')+'</strong>'+
            '<p>'+msg+'</p></div></div>'+
            '<button class="toast-dismiss" aria-label="Tutup">×</button>'+
            '<div class="toast-timer"><div class="toast-timer-bar"></div></div>';
        tc.appendChild(t);
        document.body.appendChild(tc);
        function dismiss(){ t.classList.add('toast-exit'); setTimeout(function(){ tc.remove(); },400); }
        t.querySelector('.toast-dismiss').addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);
    }

    /* Gradient Mesh */
    var hero = document.querySelector('.admin-hero');
    if (hero){
        var mesh = document.createElement('div');
        mesh.className = 'mesh-bg';
        mesh.innerHTML = '<div class="mesh-blob"></div><div class="mesh-blob"></div><div class="mesh-blob"></div>';
        hero.insertBefore(mesh, hero.firstChild);
    }

    /* Real-time Clock */
    var clockEl = document.getElementById('liveClock');
    if (clockEl){
        var ct = clockEl.querySelector('#clockTime');
        var cd = clockEl.querySelector('#clockDate');
        var days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        function tick(){
            var n = new Date();
            if(ct) ct.textContent = String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
            if(cd) cd.textContent = days[n.getDay()] + ', ' + n.getDate() + ' ' + months[n.getMonth()];
        }
        tick(); setInterval(tick, 1000);
    }

    /* Dynamic Greeting */
    var gEl = document.getElementById('dynGreeting');
    if (gEl){
        var hr = new Date().getHours();
        gEl.textContent = hr<11?'Selamat Pagi':hr<15?'Selamat Siang':hr<18?'Selamat Sore':'Selamat Malam';
    }

    /* Button Ripple */
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('mobileMenuBtn');
    var closeBtn = document.getElementById('sidebarToggleBtn');
    var overlay = document.getElementById('sidebarOverlay');
    function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
    if(toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
    if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if(overlay) overlay.addEventListener('click', toggleSidebar);
});

    /* Chart.js Setup */
    <?php if ($user['role'] === 'admin'): ?>
    <?php $top10Tech = array_slice($techPerformance, 0, 10); ?>
    const techLabels = <?= json_encode(array_column($top10Tech, 'name')) ?>;
    const techData = <?= json_encode(array_column($top10Tech, 'job_count')) ?>;
    const chartColors = ['#b91c1c', '#2563eb', '#059669', '#7c3aed', '#d97706', '#db2777', '#0d9488', '#c2410c', '#4f46e5', '#6d28d9'];
    
    if (document.getElementById('techChart')) {
        new Chart(document.getElementById('techChart'), {
            type: 'bar',
            data: {
                labels: techLabels.map(l => l.split(' ')[0]),
                datasets: [{
                    label: 'Jumlah Pekerjaan',
                    data: techData,
                    backgroundColor: chartColors.slice(0, techData.length),
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: 'Outfit', weight: '600' },
                        bodyFont: { family: 'Inter' },
                        padding: 12,
                        cornerRadius: 10
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false }, 
                        ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 }, stepSize: 1 },
                        border: { display: false }
                    },
                    x: { 
                        grid: { display: false }, 
                        ticks: { color: '#475569', font: { family: 'Inter', size: 12, weight: '500' } },
                        border: { display: false }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            },
            plugins: [{
                afterDatasetsDraw: function(chart) {
                    var ctx = chart.ctx;
                    chart.data.datasets.forEach(function(dataset, i) {
                        var meta = chart.getDatasetMeta(i);
                        meta.data.forEach(function(bar, index) {
                            var data = dataset.data[index];
                            ctx.fillStyle = '#334155';
                            ctx.font = "bold 13px 'Outfit', sans-serif";
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(data, bar.x, bar.y - 6);
                        });
                    });
                }
            }]
        });
    }

    if (document.getElementById('trendChart')) {
        new Chart(document.getElementById('trendChart'), {
            type: 'doughnut',
            data: {
                labels: techLabels.map(l => l.split(' ')[0]),
                datasets: [{
                    label: 'Total Pekerjaan',
                    data: techData,
                    backgroundColor: chartColors.slice(0, techData.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true, 
                        position: 'bottom',
                        labels: { 
                            color: '#475569', 
                            font: { family: 'Inter', size: 12, weight: '500' },
                            padding: 16,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: 'Outfit', weight: '600' },
                        bodyFont: { family: 'Inter' },
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                var pct = ((ctx.raw / total) * 100).toFixed(1);
                                return ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '60%',
                animation: { animateRotate: true, duration: 1200 }
            }
        });
    }
    <?php endif; ?>
</script>

<?php require 'job-modal.php'; ?>
</body>
</html>
