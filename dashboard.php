<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
$flash = pull_flash();

$allTypes = ['PDA', 'IH', 'HSI', 'PT2', 'EXPAND ODP'];

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

    // Count per type for distribution cards
    $typeCounts = [];
    foreach ($allTypes as $t) { $typeCounts[$t] = 0; }
    foreach ($jobs as $job) { $typeCounts[$job['work_type']] = ($typeCounts[$job['work_type']] ?? 0) + 1; }
    $maxTypeCount = max(1, max($typeCounts));
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

    // Average per job
    $avgIncome = $jobCount > 0 ? intdiv($totalIncome, $jobCount) : 0;
}

function typeClass(string $type): string {
    return match($type) {
        'PDA' => 'type-pda',
        'IH' => 'type-ih',
        'HSI' => 'type-hsi',
        'PT2' => 'type-pt2',
        'EXPAND ODP' => 'type-expand',
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
            <a class="active" href="dashboard.php"><span>01</span>Ringkasan</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="reports.php"><span>02</span>Laporan pekerjaan</a>
            <?php else: ?>
                <a href="job-create.php"><span>02</span>Input pekerjaan</a>
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
                <div class="live-clock" id="liveClock"><span class="clock-dot"></span><span class="clock-time">--:--:--</span></div>
                <a id="btn-logout" class="secondary-button" href="logout.php">Keluar</a>
                <a id="btn-reports" class="primary-button" href="reports.php">📊 Laporan</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat highlight">
                <span>Total pekerjaan</span>
                <strong><?= $jobCount ?></strong>
                <small>Seluruh tim</small>
            </div>
            <div class="hero-stat money">
                <span>Total pendapatan</span>
                <strong><?= rupiah($totalIncome) ?></strong>
                <small>Akumulasi semua WO</small>
            </div>
            <div class="hero-stat">
                <span>Teknisi aktif</span>
                <strong><?= $technicianCount ?></strong>
                <small>Terdaftar di sistem</small>
            </div>
        </div>
    </div>

    <div class="admin-body">
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
                    <h2>Seluruh pekerjaan tim</h2>
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
                        <tr>
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
        <div class="tech-hero-top">
            <div class="tech-profile">
                <div class="tech-avatar"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></div>
                <div class="tech-profile-info">
                    <h1><?= e($user['name']) ?></h1>
                    <p><?= e($user['nik']) ?></p>
                    <span class="role-pill">Teknisi Aktif</span>
                </div>
            </div>
            <div class="header-actions">
                <div class="live-clock live-clock--light" id="liveClock"><span class="clock-dot"></span><span class="clock-time">--:--:--</span></div>
                <a id="btn-logout" class="secondary-button" href="logout.php">Keluar</a>
                <a id="btn-add-job" class="primary-button" href="job-create.php">+ Catat pekerjaan</a>
            </div>
        </div>

        <div class="tech-earnings-row">
            <div class="earning-card earning-highlight">
                <span>Total pendapatan Anda</span>
                <strong><?= rupiah($totalIncome) ?></strong>
                <small>Akumulasi dari semua pekerjaan</small>
            </div>
            <div class="earning-card earning-jobs">
                <span>Pekerjaan Anda</span>
                <strong><?= $jobCount ?></strong>
                <small>Work order yang Anda kerjakan</small>
            </div>
            <div class="earning-card">
                <span>Rata-rata per pekerjaan</span>
                <strong><?= rupiah($avgIncome) ?></strong>
                <small>Pendapatan per work order</small>
            </div>
        </div>
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
            <div class="job-card">
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
                        <label><span>Teknisi</span><strong><?= e($job['technician_count']) ?> orang</strong></label>
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
    document.querySelectorAll('.hero-stat strong, .earning-card strong, .dist-count').forEach(countUp);

    /* ── 3D Card Tilt ── */
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

    /* ── Scroll Reveal ── */
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

    /* ── Toast Notification ── */
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

    /* ── Gradient Mesh ── */
    var hero = document.querySelector('.admin-hero');
    if (hero){
        var mesh = document.createElement('div');
        mesh.className = 'mesh-bg';
        mesh.innerHTML = '<div class="mesh-blob"></div><div class="mesh-blob"></div><div class="mesh-blob"></div>';
        hero.insertBefore(mesh, hero.firstChild);
    }

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

    /* ── Dynamic Greeting ── */
    var gEl = document.getElementById('dynGreeting');
    if (gEl){
        var hr = new Date().getHours();
        gEl.textContent = hr<11?'Selamat Pagi':hr<15?'Selamat Siang':hr<18?'Selamat Sore':'Selamat Malam';
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
