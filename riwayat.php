<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = require_login();

// Admin diarahkan ke reports, riwayat hanya untuk teknisi
if ($user['role'] === 'admin') {
    header('Location: reports.php');
    exit;
}

$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : null;
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : null;

$cycle = get_cycle_dates($selectedMonth, $selectedYear);
$startDate = $cycle['start'];
$endDate = $cycle['end'];
$selectedMonth = $cycle['month'];
$selectedYear = $cycle['year'];

// Nama bulan untuk UI
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$selectedPeriodName = $monthNames[$selectedMonth] . ' ' . $selectedYear;

// Fetch Data Pekerjaan berdasarkan rentang tanggal
$statement = $db->prepare(
    'SELECT j.*, COALESCE(jt.share_amount, 0) AS share_amount,
        (SELECT COUNT(*) FROM job_technicians WHERE job_id = j.id) AS technician_count,
        (SELECT GROUP_CONCAT(technician_name || " (" || technician_nik || ")", " | ") FROM job_technicians WHERE job_id = j.id) AS technicians
     FROM jobs j
     JOIN job_technicians jt ON jt.job_id = j.id AND jt.technician_nik = :nik
     WHERE j.ps_date >= :start_date AND j.ps_date <= :end_date
     ORDER BY j.ps_date DESC, j.id DESC'
);
$statement->execute([
    'nik' => $user['nik'],
    'start_date' => $startDate,
    'end_date' => $endDate
]);
$jobs = $statement->fetchAll();

$jobCount = count($jobs);
$totalIncome = array_sum(array_column($jobs, 'share_amount'));
$avgIncome = $jobCount > 0 ? intdiv($totalIncome, $jobCount) : 0;

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
    <meta name="description" content="Riwayat pendapatan teknisi IndiHome.">
    <title>Riwayat Pendapatan | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
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
            <a href="dashboard.php">
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
                <a class="active" href="riwayat.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    <span class="nav-text">Riwayat</span>
                </a>
            <?php endif; ?>
        </nav>
        <div class="side-user">
            <span class="avatar avatar-blue"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></span>
            <div>
                <strong><?= e($user['name']) ?></strong>
                <small><?= e($user['nik']) ?></small>
                <span class="role-badge role-badge-teknisi"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="tech-hero">
            <div class="tech-hero-top" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
                <div>
                    <p class="eyebrow" style="color: #38bdf8;">REKAPITULASI PENDAPATAN</p>
                    <h1>Riwayat Pekerjaan</h1>
                    <p class="hero-sub">Periode cut-off gaji: <strong><?= e(date('d M Y', strtotime($startDate))) ?></strong> s/d <strong><?= e(date('d M Y', strtotime($endDate))) ?></strong></p>
                </div>
                
                <!-- Filter Form -->
                <form method="get" class="filter-form" style="display:flex; gap:10px; align-items:flex-end;">
                    <label style="display:flex; flex-direction:column; font-size:12px; font-weight:600; color:#94a3b8;">
                        Bulan
                        <select name="month" style="padding:10px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.3); color:#fff; min-width: 120px;">
                            <?php foreach ($monthNames as $mNum => $mName): ?>
                                <option value="<?= $mNum ?>" <?= $mNum === $selectedMonth ? 'selected' : '' ?>><?= $mName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex; flex-direction:column; font-size:12px; font-weight:600; color:#94a3b8;">
                        Tahun
                        <select name="year" style="padding:10px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.3); color:#fff; min-width: 100px;">
                            <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <button type="submit" class="secondary-button" style="padding:0 20px; height: 42px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: #fff;">Filter</button>
                </form>
            </div>

            <div class="tech-earnings-row">
                <div class="earning-card earning-highlight">
                    <span>Total gaji <?= e($monthNames[$selectedMonth]) ?></span>
                    <strong><?= rupiah($totalIncome) ?></strong>
                    <small>Periode 26 ke 25</small>
                </div>
                <div class="earning-card earning-jobs">
                    <span>Total pekerjaan</span>
                    <strong><?= $jobCount ?></strong>
                    <small>Work order diselesaikan</small>
                </div>
                <div class="earning-card">
                    <span>Rata-rata pendapatan</span>
                    <strong><?= rupiah($avgIncome) ?></strong>
                    <small>Per work order</small>
                </div>
            </div>
        </div>

        <div class="tech-body">
            <section class="panel table-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">RINCIAN PEKERJAAN</p>
                        <h2>Daftar WO (<?= e($selectedPeriodName) ?>)</h2>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Work Order / Tanggal PS</th>
                            <th>Pelanggan</th>
                            <th>Jenis Pekerjaan</th>
                            <th>Jml Teknisi</th>
                            <th>Bagian Anda</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr class="job-row" data-json="<?= e(json_encode($job)) ?>">
                                <td><strong><?= e($job['work_order']) ?></strong><small><?= e(date('d M Y', strtotime($job['ps_date']))) ?></small></td>
                                <td><strong><?= e($job['customer_name']) ?></strong><small>No Inet: <?= e($job['no_inet'] ?: '-') ?></small></td>
                                <td><span class="type-badge <?= typeClass($job['work_type']) ?>"><?= e($job['work_type']) ?></span></td>
                                <td><?= (int) $job['technician_count'] ?> Orang</td>
                                <td><strong class="money"><?= rupiah((int) $job['share_amount']) ?></strong></td>
                                <td style="text-align: right;">
                                    <a href="job-edit.php?id=<?= $job['id'] ?>" class="text-link" style="font-size: 12px; font-weight: 600;">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$jobs): ?><tr><td colspan="6" class="empty">Belum ada pekerjaan pada periode cut-off ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
<script>
(function(){
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

    function countUp(el){
        var raw = el.textContent.trim();
        var isRp = raw.indexOf('Rp') > -1;
        var target = parseInt(raw.replace(/[^0-9]/g,''),10) || 0;
        if (!target) return;
        var dur = 1200, t0 = null;
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
    document.querySelectorAll('.earning-card strong').forEach(countUp);
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
</script>

<?php require 'job-modal.php'; ?>
</body>
</html>
