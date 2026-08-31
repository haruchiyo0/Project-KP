<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = require_login();

$types = ['PDA', 'IH', 'HSI', 'DATIN', 'MOK'];
$error = '';

$jobId = (int) ($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: dashboard.php');
    exit;
}

// Only the technician who created it (or admin) can edit
if ($user['role'] !== 'admin' && (int)$job['created_by'] !== (int)$user['id']) {
    flash('error', 'Anda tidak memiliki akses untuk mengedit pekerjaan ini.');
    header('Location: riwayat.php');
    exit;
}

// Check if there is an active pending request
try {
    $reqCheck = $db->prepare("SELECT request_type FROM job_requests WHERE job_id = ? AND status = 'pending' LIMIT 1");
    $reqCheck->execute([$jobId]);
    $pendingReq = $reqCheck->fetch();
    
    if ($user['role'] !== 'admin' && $pendingReq) {
        $typeLabel = $pendingReq['request_type'] === 'delete' ? 'HAPUS' : 'EDIT';
        flash('error', "Pekerjaan ini sedang dalam proses pengajuan $typeLabel ke Pimpinan. Harap tunggu persetujuan.");
        header('Location: riwayat.php');
        exit;
    }
} catch (PDOException $e) {
    // Ignore if table doesn't exist yet
}

$stmt = $db->prepare('SELECT technician_nik FROM job_technicians WHERE job_id = ? ORDER BY id ASC');
$stmt->execute([$jobId]);
$techs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$oldWorkOrder = $job['work_order'];

$allTechnicians = $db->query('SELECT name, nik FROM users WHERE role = "teknisi" ORDER BY name ASC')->fetchAll();
$techMap = [];
foreach ($allTechnicians as $t) {
    $techMap[$t['nik']] = $t['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_job']) && $_POST['delete_job'] == '1') {
        if ($user['role'] === 'teknisi') {
            try {
                $req = $db->prepare("INSERT INTO job_requests (job_id, requester_id, request_type, status) VALUES (?, ?, 'delete', 'pending')");
                $req->execute([$jobId, $user['id']]);
                flash('success', 'Pengajuan HAPUS telah dikirim ke Pimpinan. Menunggu persetujuan.');
                header('Location: riwayat.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal mengajukan penghapusan. Pastikan Pimpinan sudah membuat tabel persetujuan di pengaturan.';
            }
        } else {
            // ADMIN langsung hapus
            try {
                // Hapus di Google Sheets secara otomatis
                send_to_google_sheets([
                    'action' => 'delete',
                    'id' => $job['work_order']
                ]);

                $del = $db->prepare('DELETE FROM jobs WHERE id = ?');
                $del->execute([$jobId]);
                flash('success', 'Pekerjaan berhasil dihapus dari website dan Google Sheets secara otomatis!');
                header('Location: reports.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal menghapus pekerjaan.';
            }
        }
    }

    $reporterName = trim((string) ($_POST['reporter_name'] ?? ''));
    $reporterNik = trim((string) ($_POST['reporter_nik'] ?? ''));
    $workType = trim((string) ($_POST['work_type'] ?? ''));
    $workOrder = trim((string) ($_POST['work_order'] ?? ''));
    $noInet = trim((string) ($_POST['no_inet'] ?? ''));
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $psDate = trim((string) ($_POST['ps_date'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
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
    if (!$error && $workOrder !== $oldWorkOrder) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM jobs WHERE work_order = ? AND id != ?');
        $stmt->execute([$workOrder, $jobId]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Nomor Work Order / No ODP ini sudah pernah terdaftar, silakan periksa kembali.';
        }
    }

    if (!$error) {
        if ($user['role'] === 'teknisi') {
            try {
                $proposedData = json_encode([
                    'reporter_name' => $reporterName,
                    'reporter_nik' => $reporterNik,
                    'work_type' => $workType,
                    'work_order' => $workOrder,
                    'no_inet' => $noInet,
                    'customer_name' => $customerName,
                    'ps_date' => $psDate,
                    'description' => $description,
                    'technicians' => $technicians // array
                ]);
                $req = $db->prepare("INSERT INTO job_requests (job_id, requester_id, request_type, proposed_data, status) VALUES (?, ?, 'edit', ?, 'pending')");
                $req->execute([$jobId, $user['id'], $proposedData]);
                flash('success', 'Pengajuan EDIT telah dikirim ke Pimpinan. Menunggu persetujuan.');
                header('Location: riwayat.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal mengajukan edit. Pastikan tabel persetujuan sudah dibuat oleh admin.';
            }
        } else {
            // ADMIN langsung simpan
            try {
                $db->beginTransaction();
                $baseAmount = ($workType === 'MOK') ? 30000 : JOB_VALUE;
                
                $updateJob = $db->prepare(
                    'UPDATE jobs SET reporter_name=?, reporter_nik=?, work_type=?, work_order=?, no_inet=?, customer_name=?, ps_date=?, description=?, base_amount=?
                     WHERE id=?'
                );
                $updateJob->execute([$reporterName, $reporterNik, $workType, $workOrder, $noInet, $customerName, $psDate, $description, $baseAmount, $jobId]);
                
                $db->prepare('DELETE FROM job_technicians WHERE job_id = ?')->execute([$jobId]);
                
                $share = intdiv($baseAmount, count($technicians));
                $insertTechnician = $db->prepare('INSERT INTO job_technicians (job_id, technician_name, technician_nik, share_amount) VALUES (?, ?, ?, ?)');
                foreach ($technicians as $technician) {
                    $insertTechnician->execute([$jobId, $technician['name'], $technician['nik'], $share]);
                }
                $db->commit();

                send_to_google_sheets([
                    'action' => 'update',
                    'oldId' => $oldWorkOrder,
                    'id' => $workOrder,
                    'date' => date('d M Y', strtotime($psDate)),
                    'customer' => $customerName,
                    'type' => $workType,
                    'status' => 'Selesai',
                    'technician1Name' => $technicians[0]['name'] ?? '',
                    'technician1Nik' => $technicians[0]['nik'] ?? '',
                    'technician2Name' => $technicians[1]['name'] ?? '',
                    'technician2Nik' => $technicians[1]['nik'] ?? '',
                    'incomePerTech' => $share
                ]);

                flash('success', 'Data pekerjaan berhasil diperbarui ke Database & Google Sheets!');
                header('Location: reports.php');
                exit;
            } catch (PDOException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'Nomor work order sudah pernah digunakan.' : 'Data gagal disimpan. Silakan coba lagi.';
            }
        }
    }
}

function old(string $key, $default = ''): string
{
    return e((string) ($_POST[$key] ?? $default));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Form pencatatan pekerjaan baru teknisi KedatonGas.">
    <title>Edit Pekerjaan | KedatonGas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page-transition-overlay pt-enter" id="pt-overlay"></div>

<div class="mobile-topbar">
    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open Menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <a class="brand" href="dashboard.php">
        <div class="premium-logo"><div class="logo-ring"></div><div class="logo-text">K<strong>G</strong></div></div>
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
                    <div class="logo-text">K<strong>G</strong></div>
                </div>
                <span>
                    <strong>KedatonGas</strong>
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
        <div class="form-hero">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
                <div>
                    <p class="eyebrow">EDIT PEKERJAAN</p>
                    <h1>Perbaiki Data Pekerjaan</h1>
                </div>
                <a id="btn-back" class="secondary-button" href="riwayat.php">← Kembali</a>
            </div>
        </div>

        <div class="form-body">
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

            <div class="form-layout">
                <form method="post" class="panel work-form" id="job-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="form-section"><span>01</span><div><h2>Data pelapor</h2><p>Identitas orang yang memasukkan laporan pekerjaan.</p></div></div>
                    <div class="form-grid">
                        <label><span>Nama Pelapor</span><input id="input-reporter-name" name="reporter_name" value="<?= old('reporter_name', $job['reporter_name']) ?>" required></label>
                        <label><span>NIK Pelapor</span><input id="input-reporter-nik" name="reporter_nik" value="<?= old('reporter_nik', $job['reporter_nik']) ?>" required></label>
                        <label class="full"><span>Jenis Pekerjaan</span><select id="select-work-type" name="work_type" required><?php foreach ($types as $type): ?><option <?= old('work_type', $job['work_type']) === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label>
                    </div>

                    <div class="form-section"><span>02</span><div><h2>Data teknisi</h2><p>Teknisi kedua boleh dikosongkan jika pekerjaan dilakukan seorang diri.</p></div></div>
                    <div class="form-grid">
                        <label class="full"><span>Teknisi 1 (Wajib)</span>
                            <select name="technician_1_nik" id="select-tech-1" required>
                                <option value="">-- PilKG Teknisi --</option>
                                <?php foreach ($allTechnicians as $tech): ?>
                                    <option value="<?= e($tech['nik']) ?>" <?= old('technician_1_nik', $techs[0] ?? '') === $tech['nik'] ? 'selected' : '' ?>><?= e($tech['name']) ?> (<?= e($tech['nik']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="full"><span>Teknisi 2 (Opsional)</span>
                            <select name="technician_2_nik" id="select-tech-2">
                                <option value="">-- Tidak ada (Sendiri) --</option>
                                <?php foreach ($allTechnicians as $tech): ?>
                                    <option value="<?= e($tech['nik']) ?>" <?= old('technician_2_nik', $techs[1] ?? '') === $tech['nik'] ? 'selected' : '' ?>><?= e($tech['name']) ?> (<?= e($tech['nik']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="form-section"><span>03</span><div><h2>Data pelanggan & PS</h2><p>Pastikan nomor work order belum pernah digunakan sebelumnya.</p></div></div>
                    <div class="form-grid">
                        <label id="lbl-wo"><span>Work Order</span><input id="input-work-order" name="work_order" value="<?= old('work_order', $job['work_order']) ?>" placeholder="Contoh: WO-260720-019" required></label>
                        <label id="lbl-no-inet"><span>No. Inet</span><input id="input-no-inet" name="no_inet" value="<?= old('no_inet', $job['no_inet']) ?>" placeholder="Contoh: 122xxx"></label>
                        <label><span>Nama Pelanggan</span><input id="input-customer-name" name="customer_name" value="<?= old('customer_name', $job['customer_name']) ?>" required></label>
                        <label class="full"><span>Tanggal PS</span><input id="input-ps-date" type="date" name="ps_date" value="<?= old('ps_date', date('Y-m-d', strtotime($job['ps_date']))) ?>" required></label>
                        <label class="full" style="grid-column: 1 / -1;"><span>Keterangan / Kendala (Opsional)</span><textarea id="input-description" name="description" rows="3" placeholder="Tuliskan kendala di lapangan, material yang digunakan, dll." style="width:100%; padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: #fff; font-family: inherit; resize: vertical;"><?= old('description', $job['description']) ?></textarea></label>
                    </div>

                    <div class="form-footer">
                        <?php if ($user['role'] === 'admin'): ?>
                            <p>Data pekerjaan akan langsung diperbarui.</p>
                            <button id="btn-submit-job" class="primary-button" type="submit">Simpan Perubahan</button>
                        <?php else: ?>
                            <p>Perubahan akan diajukan ke Pimpinan untuk disetujui terlebih dahulu.</p>
                            <button id="btn-submit-job" class="primary-button" type="submit" style="background-color: #f59e0b; border-color: #f59e0b; color: white;">Ajukan Perubahan</button>
                        <?php endif; ?>
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
                        <p>Pendapatan teknisi dKGitung berdasarkan NIK yang tercatat pada setiap pekerjaan. Pastikan data NIK sudah benar sebelum menyimpan.</p>
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
</body>
</html>
