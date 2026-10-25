<?php
// modules/accounts/ledgers.php - Chart of Accounts & Ledger Master Management
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Chart of Accounts (Ledger Master)";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = sanitize_input($_POST['LedgerName'] ?? '');
        $code = sanitize_input($_POST['LedgerCode'] ?? '');
        $group_id = intval($_POST['LedgerGroupNumber'] ?? 1);
        $remarks = sanitize_input($_POST['LedgerRemarks'] ?? '');

        if (empty($name)) {
            $error = "Ledger Name is required.";
        } else {
            // Generate unique integer LedgerNumber if not provided
            $max_res = db_query($conn, "SELECT MAX(LedgerNumber) as max_num FROM MstrLedger");
            $max_num = db_fetch_assoc($max_res)['max_num'] ?? 20000;
            $new_num = max(20000, $max_num + 1);

            $sql = "INSERT INTO MstrLedger (LedgerNumber, LedgerCode, LedgerName, LedgerStatus, LedgerGroupNumber, LedgerRemarks) VALUES (?, ?, ?, 1, ?, ?)";
            $res = db_query($conn, $sql, "issis", [$new_num, $code, $name, $group_id, $remarks]);

            if ($res) {
                $msg = "New Ledger Account '$name' (#$new_num) created in Chart of Accounts.";
            } else {
                $error = "Failed to create ledger account.";
            }
        }
    }
}

$groups = db_query($conn, "SELECT * FROM MstrLedgerGroup ORDER BY LedgerGroupName ASC");
$ledgers = db_query($conn, "SELECT l.*, lg.LedgerGroupName
                           FROM MstrLedger l
                           LEFT JOIN MstrLedgerGroup lg ON l.LedgerGroupNumber = lg.LedgerGroupNumber
                           ORDER BY lg.LedgerGroupName ASC, l.LedgerName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-sitemap text-primary me-2"></i>Chart of Accounts & Ledgers</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLedgerModal">
        <i class="fa-solid fa-plus me-1"></i>Create New Ledger Account
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
                        <th>Ledger #</th>
                        <th>Ledger Code</th>
                        <th>Ledger Account Name</th>
                        <th>Hierarchical Parent Group</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ledgers && db_num_rows($ledgers) > 0): ?>
                        <?php while ($l = db_fetch_assoc($ledgers)): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo $l['LedgerNumber']; ?></span></td>
                                <td><code><?php echo htmlspecialchars($l['LedgerCode'] ?? 'N/A'); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($l['LedgerName']); ?></strong></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($l['LedgerGroupName'] ?? 'General'); ?></span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($l['LedgerRemarks'] ?? 'N/A'); ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No accounts found in Chart of Accounts.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addLedgerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ledgers.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Create New Ledger Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Ledger Name *</label>
                        <input type="text" name="LedgerName" class="form-control" placeholder="e.g. Electricity Charges Account" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Ledger Code</label>
                        <input type="text" name="LedgerCode" class="form-control" placeholder="e.g. EXP02">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Account Group *</label>
                        <select name="LedgerGroupNumber" class="form-select" required>
                            <?php if ($groups && db_num_rows($groups) > 0): ?>
                                <?php while ($g = db_fetch_assoc($groups)): ?>
                                    <option value="<?php echo $g['LedgerGroupNumber']; ?>"><?php echo htmlspecialchars($g['LedgerGroupName']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Remarks</label>
                        <input type="text" name="LedgerRemarks" class="form-control" placeholder="Optional description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Ledger Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
