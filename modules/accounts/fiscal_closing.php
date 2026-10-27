<?php
// modules/accounts/fiscal_closing.php - Fiscal Year Locking & Year-End Closing Wizard
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Fiscal Year Closing & Roll Forward Wizard";
$msg = '';
$error = '';

$current_fiscal = get_active_fiscal_year($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_fiscal_year') {
    $old_year_num = intval($current_fiscal['YearNumber']);
    $new_year_num = $old_year_num + 1;
    $new_year_desc = sanitize_input($_POST['NewYearDescription'] ?? '2025-2026');
    $new_start = sanitize_input($_POST['NewStartDate'] ?? '2025-04-01');
    $new_stop = sanitize_input($_POST['NewStopDate'] ?? '2026-03-31');

    db_begin_transaction($conn);
    try {
        // 1. Lock Old Fiscal Year
        db_query($conn, "UPDATE MstrFiscalYear SET CurrentYear = 0, YearLocked = 1 WHERE YearNumber = ?", "i", [$old_year_num]);

        // 2. Insert New Active Fiscal Year
        db_query($conn, "INSERT INTO MstrFiscalYear (YearNumber, YearDescription, YearStartDate, YearStopDate, CurrentYear, YearLocked, YearRemarks) VALUES (?, ?, ?, ?, 1, 0, 'Rolled Forward Fiscal Year')", "isss", [$new_year_num, $new_year_desc, $new_start, $new_stop]);

        // 3. Roll Balance Sheet Accounts Forward into TransLedgerOpening
        $ledgers = db_query($conn, "SELECT l.LedgerNumber, lg.LedgerGroupNumber,
                    COALESCE((SELECT SUM(TransactionAmount) FROM AccTransaction WHERE LedgerNumberBook = l.LedgerNumber), 0) as DrAmt,
                    COALESCE((SELECT SUM(TransactionAmount) FROM AccTransaction WHERE LedgerNumberHead = l.LedgerNumber), 0) as CrAmt
                    FROM MstrLedger l LEFT JOIN MstrLedgerGroup lg ON l.LedgerGroupNumber = lg.LedgerGroupNumber");

        if ($ledgers && db_num_rows($ledgers) > 0) {
            while ($l = db_fetch_assoc($ledgers)) {
                $g = intval($l['LedgerGroupNumber']);
                // Assets, Liabilities, Equity (Groups 1, 2, 5, 10, 11, 12, 14)
                if (in_array($g, [1, 2, 5, 10, 11, 12, 14])) {
                    $dr = floatval($l['DrAmt']);
                    $cr = floatval($l['CrAmt']);
                    if ($dr > 0 || $cr > 0) {
                        db_query($conn, "INSERT INTO TransLedgerOpening (CompanyNumber, YearNumber, LedgerNumber, DebitAmount, CreditAmount) VALUES (1, ?, ?, ?, ?)", "iidd", [$new_year_num, $l['LedgerNumber'], $dr, $cr]);
                    }
                }
            }
        }

        // 4. Audit Log
        db_query($conn, "INSERT INTO AuditLogs (ActionType, Module, ReferenceID, UserNumber, UserName, ActionTimestamp, DetailsJSON) VALUES ('FISCAL_CLOSE', 'ACCOUNTS', ?, ?, ?, ?, ?)", "sisss", ["FY-$old_year_num", $_SESSION['user_id'] ?? 1, $_SESSION['username'] ?? 'Admin', date('Y-m-d H:i:s'), json_encode(['closed_year' => $current_fiscal['YearDescription'], 'new_year' => $new_year_desc])]);

        db_commit($conn);
        $msg = "Financial Year {$current_fiscal['YearDescription']} closed and locked! New period '$new_year_desc' active with opening balances rolled forward.";
        $current_fiscal = get_active_fiscal_year($conn);
    } catch (Exception $e) {
        db_rollback($conn);
        $error = "Fiscal year closing failed: " . $e->getMessage();
    }
}

$all_years = db_query($conn, "SELECT * FROM MstrFiscalYear ORDER BY YearNumber DESC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / ACCOUNTS / FISCAL CLOSING</div>
<h1 class="page-header-title"><i class="fa-solid fa-lock text-primary me-2"></i>Fiscal Year Locking & Period Closing Wizard</h1>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-calendar-check me-2"></i>Close Current Financial Period</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <div class="alert alert-info py-2 small mb-4">
                    Active Year: <strong><?php echo htmlspecialchars($current_fiscal['YearDescription']); ?></strong><br>
                    Closing this period will lock all posted vouchers and roll asset/liability balances into the new fiscal year.
                </div>

                <form method="POST" action="fiscal_closing.php">
                    <input type="hidden" name="action" value="close_fiscal_year">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">New Fiscal Year Title *</label>
                        <input type="text" name="NewYearDescription" class="form-control" value="2025-2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">New Period Start Date *</label>
                        <input type="date" name="NewStartDate" class="form-control" value="2025-04-01" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small">New Period End Date *</label>
                        <input type="date" name="NewStopDate" class="form-control" value="2026-03-31" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" onclick="return confirm('ARE YOU SURE? Closing the fiscal year will lock all prior accounting transactions permanently.');">
                        <i class="fa-solid fa-lock-open me-2"></i>Lock Period & Roll Forward Balances
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa-solid fa-list me-2"></i>Financial Period History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Year #</th>
                                <th>Fiscal Description</th>
                                <th>Start Date</th>
                                <th>Stop Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_years && db_num_rows($all_years) > 0): ?>
                                <?php while ($y = db_fetch_assoc($all_years)): ?>
                                    <tr>
                                        <td><code>FY-<?php echo $y['YearNumber']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($y['YearDescription']); ?></strong></td>
                                        <td><?php echo date('d-M-Y', strtotime($y['YearStartDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($y['YearStopDate'])); ?></td>
                                        <td>
                                            <?php if ($y['CurrentYear'] == 1): ?>
                                                <span class="badge bg-success">ACTIVE YEAR</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">LOCKED</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
