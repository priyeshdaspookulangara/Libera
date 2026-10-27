<?php
// modules/accounts/distributors.php - Distributor Vendor Master Management
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Book Distributors Master";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = sanitize_input($_POST['DistributorName'] ?? '');
        $address = sanitize_input($_POST['DistributorAddress'] ?? '');
        $place = sanitize_input($_POST['DistributorPlace'] ?? '');
        $phone = sanitize_input($_POST['DistributorPhone'] ?? '');

        if (empty($name)) {
            $error = "Distributor Name is required.";
        } else {
            $sql = "INSERT INTO MstrDistributor (DistributorName, DistributorStatus, DistributorAddress, DistributorPlace, DistributorPhone) VALUES (?, 1, ?, ?, ?)";
            $res = db_query($conn, $sql, "ssss", [$name, $address, $place, $phone]);

            if ($res && isset($res['insert_id'])) {
                $dist_id = $res['insert_id'];

                // Automatically create a corresponding Creditor Ledger in MstrLedger under Sundry Creditors (Group 14)
                $ledger_num = 30000 + $dist_id;
                $ledger_code = "DIST" . str_pad($dist_id, 3, '0', STR_PAD_LEFT);
                $ledger_name = "Distributor - " . $name;

                db_query($conn, "INSERT INTO MstrLedger (LedgerNumber, LedgerCode, LedgerName, LedgerStatus, LedgerGroupNumber, LedgerRemarks) VALUES (?, ?, ?, 1, 14, 'Vendor Creditor Ledger')", "iss", [$ledger_num, $ledger_code, $ledger_name]);

                $msg = "Distributor vendor registered successfully! Linked Creditor Ledger #$ledger_num created.";
            } else {
                $error = "Failed to register distributor.";
            }
        }
    }
}

$distributors = db_query($conn, "SELECT * FROM MstrDistributor ORDER BY DistributorName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-truck-field text-primary me-2"></i>Book Distributors & Vendor Accounts</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDistModal">
        <i class="fa-solid fa-plus me-1"></i>Add New Distributor
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Distributor #</th>
                        <th>Vendor Name</th>
                        <th>Address</th>
                        <th>Place</th>
                        <th>Phone</th>
                        <th>Linked Ledger #</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($distributors && db_num_rows($distributors) > 0): ?>
                        <?php while ($d = db_fetch_assoc($distributors)): ?>
                            <tr>
                                <td><span class="badge bg-secondary">DIST-<?php echo str_pad($d['DistributorNumber'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($d['DistributorName']); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['DistributorAddress'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($d['DistributorPlace'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($d['DistributorPhone'] ?? 'N/A'); ?></td>
                                <td><code><?php echo 30000 + $d['DistributorNumber']; ?></code></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No distributor vendors recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addDistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="distributors.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Distributor Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Vendor Name *</label>
                        <input type="text" name="DistributorName" class="form-control" placeholder="e.g. Kerala Book House" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Phone Number</label>
                        <input type="text" name="DistributorPhone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Place / City</label>
                        <input type="text" name="DistributorPlace" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Address</label>
                        <input type="text" name="DistributorAddress" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Distributor Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
