<?php
// modules/circulation/return.php - Book Return & Automated Fine Calculation Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Return Book & Fine Processing";
$msg = '';
$error = '';
$issue_details = null;

// Search issued record by Accession Number or Issue ID
if (isset($_GET['search_accession']) && !empty($_GET['search_accession'])) {
    $search = sanitize_input($_GET['search_accession']);
    $sql = "SELECT i.*, b.BookName, b.BookNumber, m.MemberName, mt.IssueFineAmount
            FROM TransBookIssue i
            LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber
            LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
            LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
            LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
            WHERE (i.AccessionNumber = ? OR b.AccessionNumber = ?) AND i.IssueStatus = 1
            ORDER BY i.IssueID DESC LIMIT 1";
    $res = db_query($conn, $sql, "ss", [$search, $search]);
    if ($res && db_num_rows($res) > 0) {
        $issue_details = db_fetch_assoc($res);

        // Calculate Overdue Days and Accrued Fine
        $due = strtotime($issue_details['DueDate']);
        $today = strtotime(date('Y-m-d'));
        $overdue_days = 0;
        if ($today > $due) {
            $overdue_days = ceil(($today - $due) / (60 * 60 * 24));
        }
        $fine_rate = floatval($issue_details['IssueFineAmount'] ?? 1.0);
        $calculated_fine = $overdue_days * $fine_rate;

        $issue_details['overdue_days'] = $overdue_days;
        $issue_details['calculated_fine'] = $calculated_fine;
    } else {
        $error = "No active issued book found with Accession Number '$search'.";
    }
}

// Process Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_return') {
    $issue_id = intval($_POST['IssueID']);
    $book_num = intval($_POST['BookNumber']);
    $accession = sanitize_input($_POST['AccessionNumber']);
    $member_id = intval($_POST['MemberNumber']);
    $member_name = sanitize_input($_POST['MemberName']);
    $fine_amount = floatval($_POST['FineAmount'] ?? 0);
    $waive_amount = floatval($_POST['FineWaived'] ?? 0);
    $collected_amount = max(0, $fine_amount - $waive_amount);
    $return_date = date('Y-m-d H:i:s');

    // 1. Update TransBookIssue Record
    $sql_up = "UPDATE TransBookIssue
               SET ReturnDate = ?, IssueStatus = 2, FineAmount = ?, FineWaived = ?, FineCollected = ?
               WHERE IssueID = ?";
    $res_up = db_query($conn, $sql_up, "sdddi", [$return_date, $fine_amount, $waive_amount, $collected_amount, $issue_id]);

    if ($res_up !== false) {
        // 2. Reset Book Stock Status to Available (1)
        db_query($conn, "UPDATE MstrBookStock SET BookStatus = 1 WHERE AccessionNumber = ?", "s", [$accession]);

        // 3. Trigger Automated Financial Voucher Generation if Fine Collected
        if ($collected_amount > 0) {
            $fiscal = get_active_fiscal_year($conn);
            $year_num = intval($fiscal['YearNumber']);
            $today_date = date('Y-m-d');

            // Insert Transaction Header
            $res_h = db_query($conn, "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks, MemberNumber) VALUES (?, 14, ?, ?, ?)", "sisi", [$today_date, $year_num, "Book Fine Collection - $accession", $member_id]);
            $head_id = $res_h['insert_id'] ?? 1;

            // Debit Cash Account (Ledger 1)
            db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10012, ?, ?, ?)", "isidds", [$head_id, $today_date, $member_id, $collected_amount, "Book Fine Receipt"]);

            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, ?)", "isids", [$head_id, $today_date, $member_id, $collected_amount, "Cash Fine"]);

            // Credit Fine Income Account (Ledger 10012)
            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, ?)", "isids", [$head_id, $today_date, $member_id, $collected_amount, "Fine Income"]);
        }

        $msg = "Book returned successfully!" . ($collected_amount > 0 ? " Financial receipt voucher generated for fine collection of ₹" . number_format($collected_amount, 2) : "");
    } else {
        $error = "Failed to process book return.";
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fa-solid fa-rotate-left me-2"></i>Book Return & Overdue Fine Engine</h4>
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-circle-check me-2"></i><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Lookup Widget -->
                <form method="GET" action="return.php" class="row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Scan / Enter Accession Number *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" name="search_accession" class="form-control form-control-lg" placeholder="e.g. ACC1001" required value="<?php echo htmlspecialchars($_GET['search_accession'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fa-solid fa-search me-1"></i>Lookup Issue</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($issue_details): ?>
            <div class="card border-primary shadow">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fa-solid fa-circle-info me-2"></i>Active Checkout Summary
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Book Title:</strong> <?php echo htmlspecialchars($issue_details['BookName']); ?></p>
                            <p class="mb-1"><strong>Accession Number:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($issue_details['AccessionNumber']); ?></span></p>
                            <p class="mb-1"><strong>Borrower Member:</strong> <?php echo htmlspecialchars($issue_details['MemberName']); ?> (MEM-<?php echo str_pad($issue_details['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>)</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1"><strong>Issue Date:</strong> <?php echo date('d-M-Y', strtotime($issue_details['IssueDate'])); ?></p>
                            <p class="mb-1"><strong>Due Date:</strong> <?php echo date('d-M-Y', strtotime($issue_details['DueDate'])); ?></p>
                            <p class="mb-1">
                                <strong>Overdue Status:</strong>
                                <?php if ($issue_details['overdue_days'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $issue_details['overdue_days']; ?> Days Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-success">On Time</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <form method="POST" action="return.php">
                        <input type="hidden" name="action" value="process_return">
                        <input type="hidden" name="IssueID" value="<?php echo $issue_details['IssueID']; ?>">
                        <input type="hidden" name="BookNumber" value="<?php echo $issue_details['BookNumber']; ?>">
                        <input type="hidden" name="AccessionNumber" value="<?php echo $issue_details['AccessionNumber']; ?>">
                        <input type="hidden" name="MemberNumber" value="<?php echo $issue_details['MemberNumber']; ?>">
                        <input type="hidden" name="MemberName" value="<?php echo htmlspecialchars($issue_details['MemberName']); ?>">

                        <div class="row g-3 bg-light p-3 rounded mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Calculated Fine (₹)</label>
                                <input type="number" step="0.01" name="FineAmount" class="form-control fw-bold text-danger" value="<?php echo number_format($issue_details['calculated_fine'], 2, '.', ''); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Fine Waived Amount (₹)</label>
                                <input type="number" step="0.01" name="FineWaived" class="form-control text-warning" value="0.00" min="0" max="<?php echo $issue_details['calculated_fine']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Collectable Cash Fine (₹)</label>
                                <input type="text" class="form-control fw-bold text-success" value="₹<?php echo number_format($issue_details['calculated_fine'], 2); ?>" readonly>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold" onclick="return confirm('Confirm book return and voucher posting?');">
                                <i class="fa-solid fa-receipt me-2"></i>Complete Return & Post Accounting Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
