<?php
// modules/accounts/vouchers.php - Double-Entry Journal Voucher Posting Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Post Double-Entry Voucher";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trans_type = intval($_POST['TransTypeNumber'] ?? 11);
    $trans_date = sanitize_input($_POST['TransactionDate'] ?? date('Y-m-d'));
    $remarks = sanitize_input($_POST['TransactionRemarks'] ?? '');
    $debit_ledger = intval($_POST['DebitLedgerNumber'] ?? 0);
    $credit_ledger = intval($_POST['CreditLedgerNumber'] ?? 0);
    $amount = floatval($_POST['TransactionAmount'] ?? 0);

    // Double-Entry Balance Validation: Total Debit Must Equal Total Credit
    if ($amount <= 0) {
        $error = "Transaction amount must be greater than zero.";
    } elseif ($debit_ledger <= 0 || $credit_ledger <= 0) {
        $error = "Please select valid Debit and Credit ledger accounts.";
    } elseif ($debit_ledger === $credit_ledger) {
        $error = "Debit and Credit ledger accounts cannot be identical.";
    } else {
        $fiscal = get_active_fiscal_year($conn);
        $year_num = intval($fiscal['YearNumber']);

        // Insert Voucher Header in TransLedgerHead
        $sql_head = "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks) VALUES (?, ?, ?, ?)";
        $res_head = db_query($conn, $sql_head, "siis", [$trans_date, $trans_type, $year_num, $remarks]);

        if ($res_head && isset($res_head['insert_id'])) {
            $head_id = $res_head['insert_id'];

            // Post AccTransaction Header Record
            $sql_acc = "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, TransactionAmount, TransactionRemarks) VALUES (?, ?, ?, ?, ?, ?, ?)";
            db_query($conn, $sql_acc, "iissids", [$head_id, $trans_type, $trans_date, $debit_ledger, $credit_ledger, $amount, $remarks]);

            // Post Debit Line Item (LedgerDebitAmount = Amount, LedgerCreditAmount = 0)
            $sql_dr = "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, ?, ?, ?, 0, ?)";
            db_query($conn, $sql_dr, "iisds", [$head_id, $trans_type, $trans_date, $amount, "Debit - " . $remarks]);

            // Post Credit Line Item (LedgerDebitAmount = 0, LedgerCreditAmount = Amount)
            $sql_cr = "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, ?, ?, 0, ?, ?)";
            db_query($conn, $sql_cr, "iisds", [$head_id, $trans_type, $trans_date, $amount, "Credit - " . $remarks]);

            $msg = "Double-entry voucher posted successfully! Header ID: #$head_id. Debits = Credits = ₹" . number_format($amount, 2);
        } else {
            $error = "Failed to create voucher header.";
        }
    }
}

// Fetch Master Ledgers & Transaction Types
$ledgers = db_query($conn, "SELECT l.*, lg.LedgerGroupName FROM MstrLedger l LEFT JOIN MstrLedgerGroup lg ON l.LedgerGroupNumber = lg.LedgerGroupNumber ORDER BY l.LedgerName ASC");
$trans_types = db_query($conn, "SELECT * FROM ConfigTransType WHERE TransTypeStatus = 1 ORDER BY TransTypeName ASC");

// Fetch Recent Posted Vouchers
$recent_vouchers = db_query($conn, "SELECT h.*, t.TransTypeName
                                   FROM TransLedgerHead h
                                   LEFT JOIN ConfigTransType t ON h.TransTypeNumber = t.TransTypeNumber
                                   ORDER BY h.TransHeadNumber DESC LIMIT 15");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-receipt me-2"></i>Post Double-Entry Voucher</h4>
            </div>
            <div class="card-body">
                <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <form method="POST" action="vouchers.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Voucher / Transaction Type</label>
                        <select name="TransTypeNumber" class="form-select" required>
                            <?php if ($trans_types && db_num_rows($trans_types) > 0): ?>
                                <?php while ($tt = db_fetch_assoc($trans_types)): ?>
                                    <option value="<?php echo $tt['TransTypeNumber']; ?>"><?php echo htmlspecialchars($tt['TransTypeName']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Transaction Date *</label>
                        <input type="date" name="TransactionDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger"><i class="fa-solid fa-arrow-down-long me-1"></i>Debit Account (Dr.) *</label>
                        <select name="DebitLedgerNumber" class="form-select" required>
                            <option value="">-- Choose Debit Account --</option>
                            <?php if ($ledgers && db_num_rows($ledgers) > 0): ?>
                                <?php db_data_seek($ledgers, 0); ?>
                                <?php while ($l = db_fetch_assoc($ledgers)): ?>
                                    <option value="<?php echo $l['LedgerNumber']; ?>"><?php echo htmlspecialchars($l['LedgerName']); ?> (<?php echo htmlspecialchars($l['LedgerGroupName'] ?? ''); ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-success"><i class="fa-solid fa-arrow-up-long me-1"></i>Credit Account (Cr.) *</label>
                        <select name="CreditLedgerNumber" class="form-select" required>
                            <option value="">-- Choose Credit Account --</option>
                            <?php if ($ledgers && db_num_rows($ledgers) > 0): ?>
                                <?php db_data_seek($ledgers, 0); ?>
                                <?php while ($l = db_fetch_assoc($ledgers)): ?>
                                    <option value="<?php echo $l['LedgerNumber']; ?>"><?php echo htmlspecialchars($l['LedgerName']); ?> (<?php echo htmlspecialchars($l['LedgerGroupName'] ?? ''); ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Amount (₹) *</label>
                        <input type="number" step="0.01" name="TransactionAmount" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small">Narration / Remarks</label>
                        <input type="text" name="TransactionRemarks" class="form-control" placeholder="e.g. Purchased new books from distributor">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold">
                            <i class="fa-solid fa-check-double me-2"></i>Validate & Post Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Posted Financial Vouchers</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Voucher #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_vouchers && db_num_rows($recent_vouchers) > 0): ?>
                                <?php while ($v = db_fetch_assoc($recent_vouchers)): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">VOU-<?php echo $v['TransHeadNumber']; ?></span></td>
                                        <td><?php echo date('d-M-Y', strtotime($v['TransactionDate'])); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($v['TransTypeName'] ?? 'Voucher'); ?></span></td>
                                        <td><small><?php echo htmlspecialchars($v['TransactionRemarks'] ?? 'N/A'); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No vouchers posted yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
