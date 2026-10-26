<?php
// modules/inventory/audit.php - Physical Barcode / RFID Inventory Stock Audit Terminal
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Physical Inventory Stock Audit";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_session') {
    $session_name = sanitize_input($_POST['SessionName'] ?? 'Stock Audit ' . date('d-M-Y'));
    $now = date('Y-m-d H:i:s');

    $res = db_query($conn, "INSERT INTO StockAudits (SessionName, StartDate, AuditStatus, TotalScanned, DiscrepancyCount) VALUES (?, ?, 'In-Progress', 0, 0)", "ss", [$session_name, $now]);
    if ($res) {
        $msg = "Physical inventory audit session '$session_name' initialized.";
    } else {
        $error = "Failed to start audit session.";
    }
}

$sessions = db_query($conn, "SELECT * FROM StockAudits ORDER BY AuditSessionID DESC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / CATALOG & INVENTORY / PHYSICAL AUDIT</div>
<h1 class="page-header-title"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Physical Stock Audit & Reconciliation</h1>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa-solid fa-play me-2"></i>Start Stock Audit Session</h5>
            </div>
            <div class="card-body">
                <?php if ($msg): ?><div class="alert alert-success py-2 small"><?php echo $msg; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2 small"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST" action="audit.php">
                    <input type="hidden" name="action" value="start_session">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Audit Session Name *</label>
                        <input type="text" name="SessionName" class="form-control" value="Annual Audit <?php echo date('Y'); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-plus me-1"></i>Initialize Audit Session</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-list-check me-2"></i>Physical Inventory Audit Sessions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Session ID</th>
                                <th>Audit Title</th>
                                <th>Started On</th>
                                <th>Status</th>
                                <th>Scanned Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sessions && db_num_rows($sessions) > 0): ?>
                                <?php while ($s = db_fetch_assoc($sessions)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">AUD-<?php echo $s['AuditSessionID']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($s['SessionName']); ?></strong></td>
                                        <td><?php echo date('d-M-Y H:i', strtotime($s['StartDate'])); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo $s['AuditStatus']; ?></span></td>
                                        <td><span class="badge bg-dark"><?php echo $s['TotalScanned']; ?> Items</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No physical stock audit sessions created.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
