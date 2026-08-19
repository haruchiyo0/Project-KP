<?php
declare(strict_types=1);

// Disable direct HTML output for API endpoint
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// Read JSON input from Google Sheets Webhook / Apps Script
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    // Support $_POST fallback
    $data = $_POST;
}

$action    = strtoupper(trim((string) ($data['action'] ?? '')));
$workOrder = trim((string) ($data['work_order'] ?? $data['id'] ?? ''));

if (empty($workOrder)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter work_order / id tidak ditemukan.'
    ]);
    exit;
}

if ($action === 'DELETE' || $action === 'REMOVE') {
    try {
        // Find job to get details before deletion
        $stmt = $db->prepare('SELECT id, reporter_name, customer_name FROM jobs WHERE work_order = ?');
        $stmt->execute([$workOrder]);
        $job = $stmt->fetch();

        if ($job) {
            // Delete job (FOREIGN KEY ON DELETE CASCADE will automatically clean job_technicians)
            $del = $db->prepare('DELETE FROM jobs WHERE work_order = ?');
            $del->execute([$workOrder]);

            log_activity($db, null, 'Google Sheets Sync', 'JOB_DELETE_SYNC', "Pekerjaan WO: $workOrder ({$job['customer_name']}) dihapus via sinkronisasi Spreadsheet");

            echo json_encode([
                'status' => 'success',
                'action' => 'DELETE',
                'work_order' => $workOrder,
                'message' => "Pekerjaan $workOrder berhasil dihapus dari database & riwayat teknisi."
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 'not_found',
                'message' => "Pekerjaan dengan WO $workOrder tidak ditemukan di database."
            ]);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus data di database: ' . $e->getMessage()
        ]);
        exit;
    }
} elseif ($action === 'UPDATE') {
    $customerName = trim((string) ($data['customer_name'] ?? $data['customer'] ?? ''));
    $status       = trim((string) ($data['status'] ?? 'Selesai'));

    try {
        $upd = $db->prepare('UPDATE jobs SET customer_name = ?, status = ? WHERE work_order = ?');
        $upd->execute([$customerName, $status, $workOrder]);

        log_activity($db, null, 'Google Sheets Sync', 'JOB_UPDATE_SYNC', "Pekerjaan WO: $workOrder diperbarui via sinkronisasi Spreadsheet");

        echo json_encode([
            'status' => 'success',
            'action' => 'UPDATE',
            'work_order' => $workOrder,
            'message' => "Pekerjaan $workOrder berhasil diperbarui di database."
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memperbarui data di database: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Aksi tidak dikenal. Gunakan action DELETE atau UPDATE.'
    ]);
    exit;
}
