<?php
// modules/inventory/writeoff.php - Damaged & Write-off Item Workflow
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Damaged & Lost Item Write-offs";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'writeoff') {
    $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
    $reason = sanitize_input($_POST['WriteOffReason'] ?? 'Damaged');
    $remarks = sanitize_input($_POST['Remarks'] ?? '');

    if (empty($accession)) {
        $error = "Accession Number is required.";
    } else {
        $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
        if (!$book_res || db_num_rows($book_res) === 0) {
            $error = "Item with Accession Number '$accession' not found in catalog.";
        } else {
            $book = db_fetch_assoc($book_res);
            $amount = floatval($book['BookPrice'] ?? 0.0);
            $today = date('Y-m-d H:i:s');
            $trans_date = date('Y-m-d');

            db_begin_transaction($conn);
            try {
                // 1. Post Balancing Entry in Double-Entry Ledger if write-off amount > 0
                // Dr Write-off Expense (Ledger 603), Cr Asset / Book Purchase Account (Ledger 10017)
                $head_id = null;
                if ($amount > 0) {
                    $fiscal = get_active_fiscal_year($conn);
                    $year_num = intval($fiscal['YearNumber']);

                    $res_h = db_query($conn, "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks) VALUES (?, 13, ?, ?)", "sis", [$trans_date, $year_num, "Item Write-off ($reason) - $accession"]);
                    $head_id = $res_h['insert_id'] ?? null;

                    db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, TransactionAmount, TransactionRemarks) VALUES (?, 13, ?, 603, 10017, ?, ?)", "isds", [$head_id, $trans_date, $amount, "Write-off ($reason)"]);
                    db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 13, ?, ?, 0, 'Dr Write-off Expense')", "isd", [$head_id, $trans_date, $amount]);
                    db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 13, ?, 0, ?, 'Cr Book Inventory Asset')", "isd", [$head_id, $trans_date, $amount]);
                }

                // 2. Insert BookWriteOffs Record
                db_query($conn, "INSERT INTO BookWriteOffs (BookNumber, AccessionNumber, WriteOffReason, WriteOffAmount, WriteOffDate, TransHeadNumber, Remarks, UserNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", "issdsisi", [$book['BookNumber'], $accession, $reason, $amount, $today, $head_id, $remarks, $_SESSION['user_id'] ?? 1]);

                // 3. Mark BookStatus = 0 (Inactive/Written Off)
                db_query($conn, "UPDATE MstrBookStock SET BookStatus = 0 WHERE BookNumber = ?", "i", [$book['BookNumber']]);

                // Audit Log
                db_query($conn, "INSERT INTO AuditLogs (ActionType, Module, ReferenceID, UserNumber, UserName, ActionTimestamp, DetailsJSON) VALUES ('ITEM_WRITEOFF', 'INVENTORY', ?, ?, ?, ?, ?)", "sisss", [$accession, $_SESSION['user_id'] ?? 1, $_SESSION['username'] ?? 'Admin', $today, json_encode(['reason' => $reason, 'amount' => $amount])]);

                db_commit($conn);
                $msg = "Item '{$book['BookName']}' ($accession) written off successfully! Balancing journal entry posted for ₹" . number_format($amount, 2);
            } catch (Exception $e) {
                db_rollback($conn);
                $error = "Write-off failed: " . $e->getMessage();
            }
        }
    }
}

$writeoffs = db_query($conn, "SELECT w.*, b.BookName FROM BookWriteOffs w LEFT JOIN MstrBookStock b ON w.BookNumber = b.BookNumber ORDER BY w.WriteOffID DESC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / CATALOG & INVENTORY / WRITE-OFFS</div>
<h1 class="page-header-title"><i class="fa-solid fa-dumpster text-danger me-2"></i>Damaged, Lost & Withdrawn Item Write-offs</h1>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Process Item Write-off</h5>
            </div>
            <div class="card-body">
                <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <form method="POST" action="writeoff.php">
                    <input type="hidden" name="action" value="writeoff">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Accession Number *</label>
                        <input type="text" name="AccessionNumber" class="form-control form-control-lg fw-bold" placeholder="e.g. ACC1001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Write-off Reason *</label>
                        <select name="WriteOffReason" class="form-select" required>
                            <option value="Damaged">Damaged / Destroyed</option>
                            <option value="Lost">Lost by Member / Staff</option>
                            <option value="Withdrawn">Withdrawn from Catalog</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small">Remarks / Explanation</label>
                        <input type="text" name="Remarks" class="form-control" placeholder="Optional audit details">
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold" onclick="return confirm('Write off item and adjust inventory & ledgers?');">
                        <i class="fa-solid fa-dumpster me-1"></i>Execute Write-off & Post Entry
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa-solid fa-list me-2"></i>Write-Off History Log</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Acc #</th>
                                <th>Book Title</th>
                                <th>Reason</th>
                                <th>Asset Value</th>
                                <th>Date</th>
                                <th>Linked Voucher #</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($writeoffs && db_num_rows($writeoffs) > 0): ?>
                                <?php while ($w = db_fetch_assoc($writeoffs)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($w['AccessionNumber']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($w['BookName'] ?? 'N/A'); ?></strong></td>
                                        <td><span class="badge bg-danger"><?php echo htmlspecialchars($w['WriteOffReason']); ?></span></td>
                                        <td class="fw-bold text-danger">₹<?php echo number_format($w['WriteOffAmount'], 2); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($w['WriteOffDate'])); ?></td>
                                        <td>
                                            <?php if ($w['TransHeadNumber']): ?>
                                                <span class="badge bg-primary">VOU-<?php echo $w['TransHeadNumber']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No inventory write-off records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
