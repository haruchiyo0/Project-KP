<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();

// Only technicians can input jobs — redirect admin to dashboard
if ($user['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

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
    <meta name="description" content="Form pencatatan pekerjaan baru teknisi IndiHome.">
    <title>Input Pekerjaan | IndiHome Field</title>
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
            <a class="active" href="job-create.php"><span>02</span>Input pekerjaan</a>
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
                        <label><span>Teknisi 1 — Nama</span><input id="input-tech1-name" name="technician_1_name" value="<?= old('technician_1_name', $user['role'] === 'teknisi' ? $user['name'] : '') ?>" required></label>
                        <label><span>Teknisi 1 — NIK</span><input id="input-tech1-nik" name="technician_1_nik" value="<?= old('technician_1_nik', $user['role'] === 'teknisi' ? $user['nik'] : '') ?>" required></label>
                        <label><span>Teknisi 2 — Nama</span><input id="input-tech2-name" name="technician_2_name" value="<?= old('technician_2_name') ?>" placeholder="Opsional"></label>
                        <label><span>Teknisi 2 — NIK</span><input id="input-tech2-nik" name="technician_2_nik" value="<?= old('technician_2_nik') ?>" placeholder="Opsional"></label>
                    </div>

                    <div class="form-section"><span>03</span><div><h2>Data pelanggan & PS</h2><p>Pastikan nomor work order belum pernah digunakan sebelumnya.</p></div></div>
                    <div class="form-grid">
                        <label><span>Work Order</span><input id="input-work-order" name="work_order" value="<?= old('work_order') ?>" placeholder="Contoh: WO-260720-019" required></label>
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
                            <strong><?= rupiah(JOB_VALUE) ?></strong>
                        </div>
                        <div class="live-calc-row">
                            <span>Jika 1 teknisi</span>
                            <strong><?= rupiah(JOB_VALUE) ?></strong>
                        </div>
                        <div class="live-calc-row total">
                            <span>Jika 2 teknisi</span>
                            <strong><?= rupiah(intdiv(JOB_VALUE, 2)) ?> / orang</strong>
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
