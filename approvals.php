<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
$user = require_login();
$flash = pull_flash();

if ($user['role'] !== 'admin') {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk Admin.');
    header('Location: dashboard.php');
    exit;
}

// 1. SETUP TABEL (KHUSUS PERTAMA KALI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_table'])) {
    verify_csrf();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `job_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `job_id` INT NOT NULL,
            `requester_id` INT NOT NULL,
            `request_type` ENUM('edit', 'delete') NOT NULL,
            `proposed_data` JSON NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        flash('success', 'Tabel Pengajuan (job_requests) berhasil dibuat/diverifikasi di Database.');
    } catch (PDOException $e) {
        flash('error', 'Gagal membuat tabel: ' . $e->getMessage());
    }
    header('Location: approvals.php');
    exit;
}

// 2. HANDLE APPROVE / REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request'], $_POST['request_id'])) {
    verify_csrf();
    $requestId = (int) $_POST['request_id'];
    $action = $_POST['action_request']; // 'approve' or 'reject'

    try {
        $stmt = $db->prepare('SELECT * FROM job_requests WHERE id = ? AND status = "pending"');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'reject') {
                $db->prepare("UPDATE job_requests SET status = 'rejected' WHERE id = ?")->execute([$requestId]);
                flash('success', 'Pengajuan telah ditolak.');
            } elseif ($action === 'approve') {
                $jobId = $req['job_id'];
                
                // Fetch the original job
                $jobStmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
                $jobStmt->execute([$jobId]);
                $originalJob = $jobStmt->fetch();

                if ($originalJob) {
                    $db->beginTransaction();
                    
                    if ($req['request_type'] === 'delete') {
                        // Hapus di GS & Web
                        send_to_google_sheets([
                            'action' => 'delete',
                            'id' => $originalJob['work_order']
                        ]);
                        $db->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
                        
                        $db->prepare("UPDATE job_requests SET status = 'approved' WHERE id = ?")->execute([$requestId]);
                        $db->commit();
                        flash('success', 'Pengajuan Hapus disetujui. Data telah dihapus dari Website & Google Sheets.');
                    } elseif ($req['request_type'] === 'edit') {
                        $proposed = json_decode($req['proposed_data'], true);
                        
                        $baseAmount = ($proposed['work_type'] === 'MOK') ? 30000 : JOB_VALUE;
                        $updateJob = $db->prepare(
                            'UPDATE jobs SET reporter_name=?, reporter_nik=?, work_type=?, work_order=?, no_inet=?, customer_name=?, ps_date=?, description=?, base_amount=? WHERE id=?'
                        );
                        $updateJob->execute([
                            $proposed['reporter_name'], $proposed['reporter_nik'], $proposed['work_type'], $proposed['work_order'], 
                            $proposed['no_inet'], $proposed['customer_name'], $proposed['ps_date'], $proposed['description'], $baseAmount, $jobId
                        ]);
                        
                        $db->prepare('DELETE FROM job_technicians WHERE job_id = ?')->execute([$jobId]);
                        
                        $technicians = $proposed['technicians'];
                        $share = intdiv($baseAmount, count($technicians));
                        $insertTechnician = $db->prepare('INSERT INTO job_technicians (job_id, technician_name, technician_nik, share_amount) VALUES (?, ?, ?, ?)');
                        foreach ($technicians as $tech) {
                            $insertTechnician->execute([$jobId, $tech['name'], $tech['nik'], $share]);
                        }
                        
                        $db->prepare("UPDATE job_requests SET status = 'approved' WHERE id = ?")->execute([$requestId]);
                        $db->commit();

                        send_to_google_sheets([
                            'action' => 'update',
                            'oldId' => $originalJob['work_order'],
                            'id' => $proposed['work_order'],
                            'date' => date('d M Y', strtotime($proposed['ps_date'])),
                            'customer' => $proposed['customer_name'],
                            'type' => $proposed['work_type'],
                            'status' => 'Selesai',
                            'technician1Name' => $technicians[0]['name'] ?? '',
                            'technician1Nik' => $technicians[0]['nik'] ?? '',
                            'technician2Name' => $technicians[1]['name'] ?? '',
                            'technician2Nik' => $technicians[1]['nik'] ?? '',
                            'incomePerTech' => $share
                        ]);

                        flash('success', 'Pengajuan Edit disetujui dan telah diupdate di Website & Google Sheets.');
                    }
                } else {
                    // Job already deleted
                    $db->prepare("UPDATE job_requests SET status = 'rejected' WHERE id = ?")->execute([$requestId]);
                    flash('error', 'Pekerjaan asli sudah tidak ada di database.');
                }
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Terjadi kesalahan saat memproses pengajuan: ' . $e->getMessage());
    }
    header('Location: approvals.php');
    exit;
}

// 3. FETCH REQUESTS
$requests = [];
$tableExists = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'job_requests'");
    if ($check->rowCount() > 0) {
        $tableExists = true;
        // Fetch all pending requests first, then history
        $stmt = $db->query("
            SELECT r.*, j.work_order, j.customer_name, j.work_type, u.name AS requester_name 
            FROM job_requests r
            JOIN jobs j ON r.job_id = j.id
            LEFT JOIN users u ON r.requester_id = u.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $requests = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    echo "<div style='color:red; background:#fff; padding:10px;'>ERROR FETCHING REQUESTS: " . e($e->getMessage()) . "</div>";
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Persetujuan | KedatonGas</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-approved { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-rejected { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-edit { background: linear-gradient(45deg, #3b82f6, #2563eb); color: #fff; border: none; box-shadow: 0 2px 10px rgba(59,130,246,0.3); }
        .badge-delete { background: linear-gradient(45deg, #ef4444, #dc2626); color: #fff; border: none; box-shadow: 0 2px 10px rgba(239,68,68,0.3); }
        
        .request-card { 
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px; 
            padding: 24px; 
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .request-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .request-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; }
        .request-body { font-size: 14px; color: #475569; line-height: 1.6; }
        .request-actions { display: flex; gap: 12px; margin-top: 24px; justify-content: flex-end; padding-top: 16px; border-top: 1px dashed #e2e8f0; }
        
        .setup-box { background: rgba(239, 68, 68, 0.05); border: 1px dashed #ef4444; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 24px; }
        
        /* Diff Styles */
        .diff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .diff-col { 
            background: #f8fafc; 
            padding: 16px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
        }
        .diff-title { font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 12px; font-weight: 700; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
        .diff-item { margin-bottom: 12px; font-size: 14px; color: #1e293b; background: #ffffff; padding: 8px 12px; border-radius: 8px; border: 1px solid #f1f5f9; }
        .diff-item span { color: #94a3b8; display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        
        .empty-state {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 20px;
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .empty-icon-wrap {
            width: 80px;
            height: 80px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 0 30px rgba(0,0,0,0.2) inset, 0 8px 20px rgba(0,0,0,0.2);
            color: #cbd5e1;
        }
    </style>
</head>
<body>
<div class="page-transition-overlay pt-enter" id="pt-overlay"></div>

<div class="mobile-topbar">
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
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
                <?php $pendingCount = get_pending_requests_count($db); ?>
                <a class="active" href="approvals.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span class="nav-text" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        Persetujuan
                        <?php if ($pendingCount > 0): ?>
                            <span style="background: #ef4444; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px; font-weight: bold;"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </span>
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
        <div class="admin-hero">
            <div class="admin-hero-top">
                <div>
                    <p class="eyebrow" style="color: #fbbf24;">KONTROL & MONITORING</p>
                    <h1>Persetujuan (Approvals)</h1>
                    <p class="hero-sub" style="opacity: 0.8;">Tinjau, setujui, atau tolak pengajuan edit dan penghapusan data pekerjaan dari teknisi.</p>
                </div>
                <div class="header-actions">
                    <span style="color: rgba(255,255,255,0.6); font-size: 14px; background: rgba(0,0,0,0.2); padding: 8px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);"><?= date('l, d M Y') ?></span>
                </div>
            </div>
        </div>

        <div class="content-wrapper" style="background: #ffffff; padding: 32px 48px; min-height: calc(100vh - 200px);">
            <?php if ($flash): ?>
                <div style="padding: 0 48px 20px;">
                    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!$tableExists): ?>
                <div class="setup-box">
                    <h3 style="color: #fff; margin-bottom: 8px;">Tabel Persetujuan Belum Dibuat</h3>
                    <p style="color: #cbd5e1; margin-bottom: 16px; font-size: 14px;">Fitur persetujuan edit/hapus teknisi memerlukan tabel <code>job_requests</code> di database. Klik tombol di bawah ini untuk membuatnya secara otomatis.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" name="setup_table" value="1" class="primary-button">Buat Tabel Sekarang</button>
                    </form>
                </div>
            <?php else: ?>
                
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <div class="empty-icon-wrap">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        </div>
                        <h3 style="color: #0f172a; margin-bottom: 8px; font-weight: 600;">Belum Ada Pengajuan</h3>
                        <p style="font-size: 15px; max-width: 400px; line-height: 1.6;">Tidak ada pekerjaan yang menunggu persetujuan Anda saat ini. Pengajuan edit atau hapus dari teknisi akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    
                    <?php foreach ($requests as $r): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div>
                                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
                                        <span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span>
                                        <span class="badge badge-<?= $r['request_type'] ?>"><?= $r['request_type'] === 'delete' ? 'PENGAJUAN HAPUS' : 'PENGAJUAN EDIT' ?></span>
                                    </div>
                                    <h3 style="color: #0f172a; margin: 0; font-size: 1.1rem;"><?= e($r['work_order']) ?> - <?= e($r['customer_name']) ?></h3>
                                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Diajukan oleh <strong style="color: #0ea5e9;"><?= e($r['requester_name']) ?></strong> pada <?= date('d M Y, H:i', strtotime((string)($r['created_at'] ?? 'now'))) ?></p>
                                </div>
                            </div>
                            
                            <div class="request-body">
                                <?php if ($r['request_type'] === 'delete'): ?>
                                    <p style="color: #ef4444; background: rgba(239, 68, 68, 0.05); padding: 12px; border-radius: 8px; border: 1px dashed rgba(239, 68, 68, 0.2);">
                                        Teknisi mengajukan penghapusan permanen untuk pekerjaan ini.
                                    </p>
                                <?php else: ?>
                                    <?php 
                                        $proposed = json_decode($r['proposed_data'] ?? '{}', true) ?: []; 
                                        
                                        // Ambil teknisi lama (karena dari join tabel tidak ada teknisi list)
                                        $techStmt = $db->prepare('SELECT technician_name FROM job_technicians WHERE job_id = ?');
                                        $techStmt->execute([$r['job_id']]);
                                        $oldTechs = $techStmt->fetchAll(PDO::FETCH_COLUMN);
                                        $oldTechsStr = implode(' | ', $oldTechs);

                                        $newTechs = [];
                                        if (isset($proposed['technicians']) && is_array($proposed['technicians'])) {
                                            foreach ($proposed['technicians'] as $t) {
                                                $newTechs[] = $t['name'] ?? 'Unknown';
                                            }
                                        }
                                        $newTechsStr = implode(' | ', $newTechs);
                                    ?>
                                    <div class="diff-grid">
                                        <div class="diff-col">
                                            <div class="diff-title">Data Lama Saat Ini</div>
                                            <div class="diff-item"><span>Pelanggan</span> <?= e($r['customer_name']) ?></div>
                                            <div class="diff-item"><span>Jenis Layanan</span> <?= e($r['work_type']) ?></div>
                                            <div class="diff-item"><span>Tim Teknisi</span> <?= e($oldTechsStr) ?></div>
                                        </div>
                                        <div class="diff-col" style="border-color: rgba(59, 130, 246, 0.3);">
                                            <div class="diff-title" style="color: #3b82f6;">Data Baru Yang Diajukan</div>
                                            <div class="diff-item"><span>Pelanggan</span> <?= e($proposed['customer_name'] ?? '-') ?></div>
                                            <div class="diff-item"><span>Jenis Layanan</span> <?= e($proposed['work_type'] ?? '-') ?></div>
                                            <div class="diff-item"><span>Tim Teknisi</span> <?= e($newTechsStr ?: '-') ?></div>
                                        </div>
                                    </div>
                                    <div style="margin-top: 12px; font-size: 13px;">
                                        <strong style="color: #64748b;">Keterangan Baru:</strong><br>
                                        <?= e($proposed['description'] ?? '-') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($r['status'] === 'pending'): ?>
                            <div class="request-actions">
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('TOLAK pengajuan ini?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action_request" value="reject" class="secondary-button" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">Tolak</button>
                                </form>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('SETUJUI pengajuan ini? Data pekerjaan akan langsung diupdate/dihapus.');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action_request" value="approve" class="primary-button" style="background: #10b981; border-color: #10b981;">Setujui (Approve)</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </main>
</div>
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
