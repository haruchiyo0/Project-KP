<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();

// Admin only — redirect teknisi to dashboard
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$allTypes = ['PDA', 'IH', 'HSI', 'PT2', 'EXPAND ODP'];
$monthNames = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',   '09' => 'September',
    '10' => 'Oktober', '11' => 'November',  '12' => 'Desember',
];

// Get all registered technicians for filter dropdown
$technicians = $db->query('SELECT name, nik FROM users WHERE role = "teknisi" ORDER BY name')->fetchAll();

// Get available years from existing data
$availableYears = $db->query('SELECT DISTINCT strftime("%Y", ps_date) AS year FROM jobs ORDER BY year DESC')->fetchAll();

// Read filter parameters from URL
$filterMonth      = trim((string) ($_GET['month'] ?? ''));
$filterYear       = trim((string) ($_GET['year'] ?? ''));
$filterTechnician = trim((string) ($_GET['technician'] ?? ''));
$filterType       = trim((string) ($_GET['type'] ?? ''));

// Build dynamic WHERE clause
$where  = [];
$params = [];

if ($filterYear !== '') {
    $where[]  = "strftime('%Y', j.ps_date) = ?";
    $params[] = $filterYear;
}
if ($filterMonth !== '') {
    $where[]  = "strftime('%m', j.ps_date) = ?";
    $params[] = $filterMonth;
}
if ($filterTechnician !== '') {
    $where[]  = 'j.id IN (SELECT job_id FROM job_technicians WHERE technician_nik = ?)';
    $params[] = $filterTechnician;
}
if ($filterType !== '' && in_array($filterType, $allTypes, true)) {
    $where[]  = 'j.work_type = ?';
    $params[] = $filterType;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT j.*,
        GROUP_CONCAT(jt.technician_name || ' (' || jt.technician_nik || ')', ' | ') AS technicians,
        COUNT(jt.id) AS technician_count
     FROM jobs j
     LEFT JOIN job_technicians jt ON jt.job_id = j.id
     $whereClause
     GROUP BY j.id
     ORDER BY j.ps_date DESC, j.id DESC"
);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Calculate filtered summary stats
$filteredCount  = count($jobs);
$filteredIncome = array_sum(array_map(static fn(array $j): int => (int) $j['base_amount'], $jobs));
$filteredAvg    = $filteredCount > 0 ? intdiv($filteredIncome, $filteredCount) : 0;

$hasFilter = $filterMonth !== '' || $filterYear !== '' || $filterTechnician !== '' || $filterType !== '';

function typeClass(string $type): string {
    return match($type) {
        'PDA'        => 'type-pda',
        'IH'         => 'type-ih',
        'HSI'        => 'type-hsi',
        'PT2'        => 'type-pt2',
        'EXPAND ODP' => 'type-expand',
        default      => 'type-pda',
    };
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Laporan pekerjaan tim teknisi IndiHome — filter berdasarkan bulan, teknisi, dan jenis pekerjaan.">
    <title>Laporan Pekerjaan | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page-transition-overlay pt-enter" id="pt-overlay"></div>
<div class="app-layout">
    <aside class="sidebar">
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
        <nav>
            <a href="dashboard.php"><span>01</span>Ringkasan</a>
            <a class="active" href="reports.php"><span>02</span>Laporan pekerjaan</a>
        </nav>
        <div class="side-user">
            <span class="avatar avatar-red"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></span>
            <div>
                <strong><?= e($user['name']) ?></strong>
                <small><?= e($user['nik']) ?></small>
                <span class="role-badge role-badge-admin"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>
    </aside>

    <main class="main-content">

        <!-- ===== Report Hero ===== -->
        <div class="report-hero">
            <div class="report-hero-top">
                <div>
                    <p class="eyebrow">LAPORAN PEKERJAAN</p>
                    <h1>Data Pekerjaan Tim</h1>
                    <p class="hero-sub">Lihat dan filter seluruh laporan pekerjaan berdasarkan bulan, teknisi, atau jenis layanan.</p>
                </div>
                <div class="header-actions">
                    <div class="live-clock live-clock--light" id="liveClock"><span class="clock-dot"></span><span class="clock-time">--:--:--</span></div>
                    <a id="btn-logout" class="secondary-button" href="logout.php">Keluar</a>
                </div>
            </div>
        </div>

        <!-- ===== Filter Bar ===== -->
        <div class="report-filters">
            <form method="get" class="filter-form" id="report-filter-form">
                <div class="filter-group">
                    <label for="filter-year">Tahun</label>
                    <select id="filter-year" name="year">
                        <option value="">Semua tahun</option>
                        <?php foreach ($availableYears as $y): ?>
                            <option value="<?= e($y['year']) ?>" <?= $filterYear === $y['year'] ? 'selected' : '' ?>><?= e($y['year']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-month">Bulan</label>
                    <select id="filter-month" name="month">
                        <option value="">Semua bulan</option>
                        <?php foreach ($monthNames as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $filterMonth === $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-technician">Teknisi</label>
                    <select id="filter-technician" name="technician">
                        <option value="">Semua teknisi</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= e($tech['nik']) ?>" <?= $filterTechnician === $tech['nik'] ? 'selected' : '' ?>><?= e($tech['name']) ?> (<?= e($tech['nik']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-type">Jenis Pekerjaan</label>
                    <select id="filter-type" name="type">
                        <option value="">Semua jenis</option>
                        <?php foreach ($allTypes as $type): ?>
                            <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btn-filter" type="submit" class="primary-button">Terapkan</button>
                    <?php if ($hasFilter): ?>
                        <a id="btn-reset-filter" href="reports.php" class="secondary-button">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ===== Active Filter Tags ===== -->
        <?php if ($hasFilter): ?>
        <div class="active-filters">
            <span>Filter aktif:</span>
            <?php if ($filterYear !== ''): ?>
                <span class="filter-tag">Tahun: <?= e($filterYear) ?></span>
            <?php endif; ?>
            <?php if ($filterMonth !== ''): ?>
                <span class="filter-tag">Bulan: <?= e($monthNames[$filterMonth] ?? $filterMonth) ?></span>
            <?php endif; ?>
            <?php if ($filterTechnician !== ''): ?>
                <?php
                $techName = '';
                foreach ($technicians as $t) {
                    if ($t['nik'] === $filterTechnician) { $techName = $t['name']; break; }
                }
                ?>
                <span class="filter-tag">Teknisi: <?= e($techName) ?></span>
            <?php endif; ?>
            <?php if ($filterType !== ''): ?>
                <span class="filter-tag">Jenis: <?= e($filterType) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===== Summary Cards ===== -->
        <div class="report-summary">
            <div class="report-stat stat-highlight">
                <span>Total pekerjaan</span>
                <strong><?= $filteredCount ?></strong>
                <small><?= $hasFilter ? 'Sesuai filter' : 'Seluruh tim' ?></small>
            </div>
            <div class="report-stat stat-money">
                <span>Total pendapatan</span>
                <strong><?= rupiah($filteredIncome) ?></strong>
                <small><?= $hasFilter ? 'Sesuai filter' : 'Akumulasi semua WO' ?></small>
            </div>
            <div class="report-stat stat-avg">
                <span>Rata-rata per pekerjaan</span>
                <strong><?= rupiah($filteredAvg) ?></strong>
                <small>Pendapatan per work order</small>
            </div>
        </div>

        <!-- ===== Data Table ===== -->
        <div class="report-body">
            <section class="panel table-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $hasFilter ? 'HASIL FILTER' : 'DATA LENGKAP' ?></p>
                        <h2><?= $hasFilter ? 'Pekerjaan sesuai filter' : 'Seluruh pekerjaan tim' ?></h2>
                    </div>
                    <span class="result-count"><?= $filteredCount ?> data</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Work Order / Tanggal PS</th>
                            <th>Pelanggan</th>
                            <th>Jenis</th>
                            <th>Pelapor</th>
                            <th>Teknisi</th>
                            <th>Pendapatan / Teknisi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><strong><?= e($job['work_order']) ?></strong><small><?= e(date('d M Y', strtotime($job['ps_date']))) ?></small></td>
                                <td><strong><?= e($job['customer_name']) ?></strong></td>
                                <td><span class="type-badge <?= typeClass($job['work_type']) ?>"><?= e($job['work_type']) ?></span></td>
                                <td><?= e($job['reporter_name']) ?><small><?= e($job['reporter_nik']) ?></small></td>
                                <td><?= e($job['technicians']) ?></td>
                                <td><strong class="money"><?= rupiah((int) $job['base_amount'] / max(1, (int) $job['technician_count'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$jobs): ?>
                            <tr><td colspan="6" class="empty"><?= $hasFilter ? 'Tidak ada pekerjaan yang sesuai dengan filter.' : 'Belum ada pekerjaan yang tercatat.' ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </main>
</div>

<script>
(function(){
    /* ── Page Transition ── */
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

    /* ── Real-time Clock ── */
    var clockEl = document.getElementById('liveClock');
    if (clockEl){
        var ct = clockEl.querySelector('.clock-time');
        function tick(){
            var n = new Date();
            ct.textContent = String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
        }
        tick(); setInterval(tick, 1000);
    }

    /* ── Animated Counters ── */
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
    document.querySelectorAll('.report-stat strong').forEach(countUp);

    /* ── 3D Card Tilt on stat cards ── */
    document.querySelectorAll('.report-stat').forEach(function(c){
        c.addEventListener('mousemove', function(e){
            var r = c.getBoundingClientRect();
            var x = (e.clientX - r.left)/r.width - .5;
            var y = (e.clientY - r.top)/r.height - .5;
            c.style.transform = 'perspective(600px) rotateY('+x*6+'deg) rotateX('+(-y*6)+'deg) translateY(-3px)';
            c.style.boxShadow = '0 20px 30px rgba(0,0,0,.08)';
        });
        c.addEventListener('mouseleave', function(){
            c.style.transform = '';
            c.style.boxShadow = '';
        });
    });

    /* ── Scroll Reveal for table ── */
    var sr = document.querySelectorAll('.report-body .table-panel');
    if ('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(en){
                if (en.isIntersecting){ en.target.classList.add('in-view'); io.unobserve(en.target); }
            });
        },{ threshold:.1 });
        sr.forEach(function(el){ io.observe(el); });
    } else {
        sr.forEach(function(el){ el.classList.add('in-view'); });
    }

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
