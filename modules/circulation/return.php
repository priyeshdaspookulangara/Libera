<?php
// modules/circulation/return.php - Book Return & Automated Fine Calculation Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Return Book & Fine Processing";
$msg = '';
$error = '';
$issue_details = null;
$member_issues = [];

$search_type = sanitize_input($_GET['search_type'] ?? 'accession');
$search_query = sanitize_input($_GET['search_query'] ?? '');

// Search issued record by selected search type (accession, member_number, member_name, book_title)
if (!empty($search_query)) {
    if ($search_type === 'accession') {
        $sql = "SELECT i.*, b.BookName, b.BookNumber, m.MemberName, mt.IssueFineAmount
                FROM TransBookIssue i
                LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
                LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
                LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                WHERE (i.AccessionNumber = ? OR b.AccessionNumber = ?) AND i.IssueStatus = 1
                ORDER BY i.IssueID DESC LIMIT 1";
        $res = db_query($conn, $sql, "ss", [$search_query, $search_query]);
        if ($res && db_num_rows($res) > 0) {
            $issue_details = db_fetch_assoc($res);
        } else {
            $error = "No active issued book found with Accession Number '$search_query'.";
        }
    } else {
        // Multi-record search (by Member Number, Member Name, or Book Title)
        $where = "i.IssueStatus = 1";
        $params = [];
        $types = "";

        if ($search_type === 'member_number') {
            $where .= " AND (i.MemberNumber = ? OR m.MemberNumber = ?)";
            $params = [intval($search_query), intval($search_query)];
            $types = "ii";
        } elseif ($search_type === 'member_name') {
            $where .= " AND (m.MemberName LIKE ? OR i.MemberName LIKE ?)";
            $sq = "%$search_query%";
            $params = [$sq, $sq];
            $types = "ss";
        } elseif ($search_type === 'book_title') {
            $where .= " AND b.BookName LIKE ?";
            $sq = "%$search_query%";
            $params = [$sq];
            $types = "s";
        }

        $sql = "SELECT i.*, b.BookName, b.BookNumber, m.MemberName, mt.IssueFineAmount
                FROM TransBookIssue i
                LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
                LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
                LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                WHERE $where ORDER BY i.IssueID DESC LIMIT 20";
        $res = db_query($conn, $sql, $types, $params);
        if ($res && db_num_rows($res) > 0) {
            while ($r = db_fetch_assoc($res)) {
                $due = strtotime($r['DueDate']);
                $today = strtotime(date('Y-m-d'));
                $overdue_days = ($today > $due) ? ceil(($today - $due) / (60 * 60 * 24)) : 0;
                $fine_rate = floatval($r['IssueFineAmount'] ?? 1.0);
                $r['overdue_days'] = $overdue_days;
                $r['calculated_fine'] = $overdue_days * $fine_rate;
                $member_issues[] = $r;
            }
            if (count($member_issues) === 1) {
                $issue_details = $member_issues[0];
            }
        } else {
            $error = "No active issued books found matching criteria.";
        }
    }

    if ($issue_details) {
        $due = strtotime($issue_details['DueDate']);
        $today = strtotime(date('Y-m-d'));
        $overdue_days = ($today > $due) ? ceil(($today - $due) / (60 * 60 * 24)) : 0;
        $fine_rate = floatval($issue_details['IssueFineAmount'] ?? 1.0);
        $issue_details['overdue_days'] = $overdue_days;
        $issue_details['calculated_fine'] = $overdue_days * $fine_rate;
    }
}

// Select specific Issue ID from search results
if (isset($_GET['select_issue_id'])) {
    $issue_id = intval($_GET['select_issue_id']);
    $sql = "SELECT i.*, b.BookName, b.BookNumber, m.MemberName, mt.IssueFineAmount
            FROM TransBookIssue i
            LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
            LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
            LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
            LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
            WHERE i.IssueID = ? AND i.IssueStatus = 1 LIMIT 1";
    $res = db_query($conn, $sql, "i", [$issue_id]);
    if ($res && db_num_rows($res) > 0) {
        $issue_details = db_fetch_assoc($res);
        $due = strtotime($issue_details['DueDate']);
        $today = strtotime(date('Y-m-d'));
        $overdue_days = ($today > $due) ? ceil(($today - $due) / (60 * 60 * 24)) : 0;
        $fine_rate = floatval($issue_details['IssueFineAmount'] ?? 1.0);
        $issue_details['overdue_days'] = $overdue_days;
        $issue_details['calculated_fine'] = $overdue_days * $fine_rate;
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
            db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10012, ?, ?, ?)", "isids", [$head_id, $today_date, $member_id, $collected_amount, "Book Fine Receipt"]);

            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, ?)", "isids", [$head_id, $today_date, $member_id, $collected_amount, "Cash Fine"]);

            // Credit Fine Income Account (Ledger 10012)
            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, ?)", "isids", [$head_id, $today_date, $member_id, $collected_amount, "Fine Income"]);
        }

        $msg = "Book returned successfully!" . ($collected_amount > 0 ? " Financial receipt voucher generated for fine collection of ₹" . number_format($collected_amount, 2) : "");
        $issue_details = null;
        $member_issues = [];
    } else {
        $error = "Failed to process book return.";
    }
}

// Fetch all active checkouts for quick selection table
$all_active = db_query($conn, "SELECT i.*, b.BookName, m.MemberName
                               FROM TransBookIssue i
                               LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
                               LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
                               WHERE i.IssueStatus = 1 ORDER BY i.IssueID DESC LIMIT 15");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
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

                <!-- Lookup Widget with Multiple Search Options -->
                <form method="GET" action="return.php" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Search By</label>
                        <select name="search_type" class="form-select">
                            <option value="accession" <?php echo $search_type === 'accession' ? 'selected' : ''; ?>>Accession Number</option>
                            <option value="member_number" <?php echo $search_type === 'member_number' ? 'selected' : ''; ?>>Member Number</option>
                            <option value="member_name" <?php echo $search_type === 'member_name' ? 'selected' : ''; ?>>Member Name</option>
                            <option value="book_title" <?php echo $search_type === 'book_title' ? 'selected' : ''; ?>>Book Title</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Search Term / Value *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" name="search_query" class="form-control" placeholder="Enter Accession No, Member ID, Name or Title..." required value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-search me-1"></i>Lookup Active Checkouts</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (count($member_issues) > 1 && !$issue_details): ?>
            <!-- Multiple Results View -->
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="fa-solid fa-list-check me-2"></i>Multiple Active Checkouts Found (<?php echo count($member_issues); ?>)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Accession #</th>
                                    <th>Book Title</th>
                                    <th>Member Name</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Fine Amount</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($member_issues as $mi): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($mi['AccessionNumber'] ?? $mi['BookNumber']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($mi['BookName'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mi['MemberName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($mi['IssueDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($mi['DueDate'])); ?></td>
                                        <td>
                                            <?php if ($mi['overdue_days'] > 0): ?>
                                                <span class="badge bg-danger">₹<?php echo number_format($mi['calculated_fine'], 2); ?> (<?php echo $mi['overdue_days']; ?> days)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">₹0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="return.php?select_issue_id=<?php echo $mi['IssueID']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fa-solid fa-arrow-right me-1"></i>Select & Process
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($issue_details): ?>
            <!-- Active Checkout Return Form -->
            <div class="card border-primary shadow mb-4">
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

        <!-- Active Checkouts Quick Pick Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Active Checkouts</h5>
                <a href="/modules/circulation/issued_list.php" class="btn btn-sm btn-outline-light">View All Checkouts</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th>Issue #</th>
                                <th>Accession #</th>
                                <th>Book Title</th>
                                <th>Borrower Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th class="text-end">Quick Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_active && db_num_rows($all_active) > 0): ?>
                                <?php while ($ac = db_fetch_assoc($all_active)): ?>
                                    <tr>
                                        <td><code>#<?php echo $ac['IssueID']; ?></code></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ac['AccessionNumber'] ?? $ac['BookNumber']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($ac['BookName'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ac['MemberName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($ac['IssueDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($ac['DueDate'])); ?></td>
                                        <td class="text-end">
                                            <a href="return.php?select_issue_id=<?php echo $ac['IssueID']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fa-solid fa-rotate-left me-1"></i>Process Return
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No active checkouts currently outstanding.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
