<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = require_login();

// Only technicians can input jobs — redirect admin to dashboard
if ($user['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$types = ['PDA', 'IH', 'HSI', 'DATIN', 'MOK', 'EXPAND ODP'];
$error = '';

$allTechnicians = $db->query('SELECT name, nik FROM users WHERE role = "teknisi" ORDER BY name ASC')->fetchAll();
$techMap = [];
foreach ($allTechnicians as $t) {
    $techMap[$t['nik']] = $t['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reporterName = trim((string) ($_POST['reporter_name'] ?? ''));
    $reporterNik = trim((string) ($_POST['reporter_nik'] ?? ''));
    $workType = trim((string) ($_POST['work_type'] ?? ''));
    $workOrder = trim((string) ($_POST['work_order'] ?? ''));
    $noInet = trim((string) ($_POST['no_inet'] ?? ''));
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $psDate = trim((string) ($_POST['ps_date'] ?? ''));
    $technicians = [];

    for ($number = 1; $number <= 2; $number++) {
        $nik = trim((string) ($_POST["technician_{$number}_nik"] ?? ''));
        if ($nik !== '') {
            if (!isset($techMap[$nik])) {
                $error = "Teknisi {$number} tidak valid.";
                break;
            }
            $technicians[] = ['name' => $techMap[$nik], 'nik' => $nik];
        }
    }

    if (!$error && (!$reporterName || !$reporterNik || !$workOrder || !$customerName || !$psDate || !in_array($workType, $types, true))) {
        $error = 'Lengkapi seluruh data pekerjaan yang wajib diisi.';
    }
    if (!$error && $workType !== 'DATIN' && $noInet === '') {
        $error = 'No. Inet wajib diisi untuk jenis pekerjaan ini.';
    }
    if (!$error && count($technicians) === 0) {
        $error = 'Minimal satu teknisi harus diisi.';
    }
    if (!$error && count(array_unique(array_column($technicians, 'nik'))) !== count($technicians)) {
        $error = 'NIK Teknisi 1 dan Teknisi 2 tidak boleh sama.';
    }
    if (!$error) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM jobs WHERE work_order = ?');
        $stmt->execute([$workOrder]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Nomor Work Order / No ODP ini sudah pernah terdaftar, silakan periksa kembali.';
        }
    }

    if (!$error) {
        try {
            $db->beginTransaction();
            $baseAmount = ($workType === 'MOK') ? 30000 : JOB_VALUE;
            $insertJob = $db->prepare(
                'INSERT INTO jobs (reporter_name, reporter_nik, work_type, work_order, no_inet, customer_name, ps_date, base_amount, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertJob->execute([$reporterName, $reporterNik, $workType, $workOrder, $noInet, $customerName, $psDate, $baseAmount, $user['id']]);
            $jobId = (int) $db->lastInsertId();
            $share = intdiv($baseAmount, count($technicians));
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
    <meta name="description" content="Form pencatatan pekerjaan baru teknisi IndiHome.">
    <title>Input Pekerjaan | IndiHome Field</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page-transition-overlay pt-enter" id="pt-overlay"></div>
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
            <a href="dashboard.php"><span>01</span><span class="nav-text">Ringkasan</span></a>
            <a class="active" href="job-create.php"><span>02</span><span class="nav-text">Input pekerjaan</span></a>
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
        <div class="form-hero">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
                <div>
                    <p class="eyebrow">FORM PEKERJAAN</p>
                    <h1>Catat pekerjaan baru</h1>
                </div>
                <a id="btn-back" class="secondary-button" href="dashboard.php">← Kembali</a>
            </div>
        </div>

        <div class="form-body">
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

            <div class="form-layout">
                <form method="post" class="panel work-form" id="job-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="form-section"><span>01</span><div><h2>Data pelapor</h2><p>Identitas orang yang memasukkan laporan pekerjaan.</p></div></div>
                    <div class="form-grid">
                        <label><span>Nama Pelapor</span><input id="input-reporter-name" name="reporter_name" value="<?= old('reporter_name', $user['name']) ?>" required></label>
                        <label><span>NIK Pelapor</span><input id="input-reporter-nik" name="reporter_nik" value="<?= old('reporter_nik', $user['nik']) ?>" required></label>
                        <label class="full"><span>Jenis Pekerjaan</span><select id="select-work-type" name="work_type" required><?php foreach ($types as $type): ?><option <?= old('work_type', 'PDA') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label>
                    </div>

                    <div class="form-section"><span>02</span><div><h2>Data teknisi</h2><p>Teknisi kedua boleh dikosongkan jika pekerjaan dilakukan seorang diri.</p></div></div>
                    <div class="form-grid">
                        <label class="full"><span>Teknisi 1 (Wajib)</span>
                            <select name="technician_1_nik" id="select-tech-1" required>
                                <option value="">-- Pilih Teknisi --</option>
                                <?php foreach ($allTechnicians as $tech): ?>
                                    <option value="<?= e($tech['nik']) ?>" <?= old('technician_1_nik', $user['role'] === 'teknisi' ? $user['nik'] : '') === $tech['nik'] ? 'selected' : '' ?>><?= e($tech['name']) ?> (<?= e($tech['nik']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="full"><span>Teknisi 2 (Opsional)</span>
                            <select name="technician_2_nik" id="select-tech-2">
                                <option value="">-- Tidak ada (Sendiri) --</option>
                                <?php foreach ($allTechnicians as $tech): ?>
                                    <option value="<?= e($tech['nik']) ?>" <?= old('technician_2_nik') === $tech['nik'] ? 'selected' : '' ?>><?= e($tech['name']) ?> (<?= e($tech['nik']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="form-section"><span>03</span><div><h2>Data pelanggan & PS</h2><p>Pastikan nomor work order belum pernah digunakan sebelumnya.</p></div></div>
                    <div class="form-grid">
                        <label id="lbl-wo"><span>Work Order</span><input id="input-work-order" name="work_order" value="<?= old('work_order') ?>" placeholder="Contoh: WO-260720-019" required></label>
                        <label id="lbl-no-inet"><span>No. Inet</span><input id="input-no-inet" name="no_inet" value="<?= old('no_inet') ?>" placeholder="Contoh: 122xxx"></label>
                        <label><span>Nama Pelanggan</span><input id="input-customer-name" name="customer_name" value="<?= old('customer_name') ?>" required></label>
                        <label class="full"><span>Tanggal PS</span><input id="input-ps-date" type="date" name="ps_date" value="<?= old('ps_date', date('Y-m-d')) ?>" required></label>
                    </div>

                    <div class="form-footer">
                        <p>Pendapatan teknisi dihitung otomatis setelah data disimpan.</p>
                        <button id="btn-submit-job" class="primary-button" type="submit">Simpan pekerjaan</button>
                    </div>
                </form>

                <aside class="form-aside">
                    <!-- Live calculator card -->
                    <div class="live-calc">
                        <p class="eyebrow">KALKULASI LIVE</p>
                        <h2>Estimasi pendapatan</h2>
                        <div class="live-calc-row">
                            <span>Nilai pekerjaan</span>
                            <strong id="calc-val"><?= rupiah(JOB_VALUE) ?></strong>
                        </div>
                        <div class="live-calc-row">
                            <span>Jika 1 teknisi</span>
                            <strong id="calc-1"><?= rupiah(JOB_VALUE) ?></strong>
                        </div>
                        <div class="live-calc-row total">
                            <span>Jika 2 teknisi</span>
                            <strong id="calc-2"><?= rupiah(intdiv(JOB_VALUE, 2)) ?> / orang</strong>
                        </div>
                    </div>

                    <article class="panel calculator-card">
                        <p class="eyebrow">INFORMASI</p>
                        <h2>Pembagian pendapatan</h2>
                        <p class="helper-copy">Sistem menghitung bagian setiap teknisi secara otomatis berdasarkan jumlah teknisi yang tercatat dalam pekerjaan.</p>
                    </article>

                    <article class="info-card">
                        <strong>Catatan penting</strong>
                        <p>Pendapatan teknisi dihitung berdasarkan NIK yang tercatat pada setiap pekerjaan. Pastikan data NIK sudah benar sebelum menyimpan.</p>
                    </article>
                </aside>
            </div>
        </div>
    </main>
</div>
<script>
(function(){
    /* ── Sidebar Toggle ── */
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

    /* ── Live Calculator Update ── */
    var selType = document.getElementById('select-work-type');
    if (selType) {
        selType.addEventListener('change', function() {
            var val = this.value === 'MOK' ? 30000 : <?= JOB_VALUE ?>;
            var halff = Math.floor(val / 2);
            var fmt = function(v) { return 'Rp' + v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); };
            var eVal = document.getElementById('calc-val');
            var e1 = document.getElementById('calc-1');
            var e2 = document.getElementById('calc-2');
            if (eVal) eVal.textContent = fmt(val);
            if (e1) e1.textContent = fmt(val);
            if (e2) e2.textContent = fmt(halff) + ' / orang';

            var inet = document.getElementById('lbl-no-inet');
            if (inet) {
                if (this.value === 'DATIN') {
                    inet.style.display = 'none';
                    inet.querySelector('input').removeAttribute('required');
                } else {
                    inet.style.display = '';
                    inet.querySelector('input').setAttribute('required', 'required');
                }
            }
            var lblWo = document.getElementById('lbl-wo');
            if (lblWo) {
                var span = lblWo.querySelector('span');
                var inp = lblWo.querySelector('input');
                if (this.value === 'EXPAND ODP') {
                    span.textContent = 'No ODP';
                    inp.placeholder = 'Contoh: ODP-...';
                } else {
                    span.textContent = 'Work Order';
                    inp.placeholder = 'Contoh: WO-...';
                }
            }
        });
        selType.dispatchEvent(new Event('change'));
    }

    /* ── Prevent Duplicate Technician Selection ── */
    var t1 = document.getElementById('select-tech-1');
    var t2 = document.getElementById('select-tech-2');
    if (t1 && t2) {
        function syncTechs() {
            var val1 = t1.value;
            var val2 = t2.value;
            Array.from(t2.options).forEach(function(opt) {
                if (opt.value !== '' && opt.value === val1) {
                    opt.style.display = 'none';
                    opt.disabled = true;
                } else {
                    opt.style.display = '';
                    opt.disabled = false;
                }
            });
            Array.from(t1.options).forEach(function(opt) {
                if (opt.value !== '' && opt.value === val2) {
                    opt.style.display = 'none';
                    opt.disabled = true;
                } else {
                    opt.style.display = '';
                    opt.disabled = false;
                }
            });
        }
        t1.addEventListener('change', syncTechs);
        t2.addEventListener('change', syncTechs);
        syncTechs();
    }

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
    document.querySelectorAll('form').forEach(function(f){
        f.addEventListener('submit', function(){
            pt.className = 'page-transition-overlay pt-exit';
        });
    });
    window.addEventListener('pageshow', function(e){
        if (e.persisted) pt.className = 'page-transition-overlay pt-enter';
    });

    /* ── Form Micro-interactions ── */
    document.querySelectorAll('.work-form input, .work-form select').forEach(function(inp){
        function check(){
            if (inp.value && inp.value.trim()) inp.classList.add('input-filled');
            else inp.classList.remove('input-filled');
        }
        inp.addEventListener('input', check);
        inp.addEventListener('change', check);
        check();
    });

    /* ── Toast for Errors ── */
    var alertEl = document.querySelector('.alert');
    if (alertEl){
        var ok = alertEl.classList.contains('success');
        var msg = alertEl.textContent.trim();
        alertEl.style.display = 'none';
        var tc = document.createElement('div');
        tc.className = 'toast-container';
        var t = document.createElement('div');
        t.className = 'toast ' + (ok ? 'success' : 'error');
        t.innerHTML =
            '<div class="toast-body"><div class="toast-icon">'+(ok?'✓':'✕')+'</div>'+
            '<div class="toast-text"><strong>'+(ok?'Berhasil!':'Data Tidak Lengkap')+'</strong>'+
            '<p>'+msg+'</p></div></div>'+
            '<button class="toast-dismiss" aria-label="Tutup">×</button>'+
            '<div class="toast-timer"><div class="toast-timer-bar"></div></div>';
        tc.appendChild(t);
        document.body.appendChild(tc);
        function dismiss(){ t.classList.add('toast-exit'); setTimeout(function(){ tc.remove(); },400); }
        t.querySelector('.toast-dismiss').addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);
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
