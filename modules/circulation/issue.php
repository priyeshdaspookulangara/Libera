<?php
// modules/circulation/issue.php - Book Issuance Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Issue Book";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
    $member_id = intval($_POST['MemberNumber'] ?? 0);
    $issue_date = sanitize_input($_POST['IssueDate'] ?? date('Y-m-d'));

    if (empty($accession) || $member_id <= 0) {
        $error = "Please select both a valid Member and enter an Accession Number.";
    } else {
        // 1. Validate Book Stock
        $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
        if (!$book_res || db_num_rows($book_res) === 0) {
            $error = "Book with Accession Number '$accession' not found in inventory.";
        } else {
            $book = db_fetch_assoc($book_res);
            if ($book['BookStatus'] != 1) {
                $error = "Book '{$book['BookName']}' is currently not available for issue (Status: Issued/Inactive).";
            } else {
                // 2. Validate Member & Borrowing Policy Limits
                $mem_res = db_query($conn, "SELECT m.*, mt.IssueBookCount, mt.IssueAllowedDays
                                           FROM MstrMember m
                                           LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                                           LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                                           WHERE m.MemberNumber = ? LIMIT 1", "i", [$member_id]);
                if (!$mem_res || db_num_rows($mem_res) === 0) {
                    $error = "Member ID $member_id not found.";
                } else {
                    $member = db_fetch_assoc($mem_res);
                    $max_limit = intval($member['IssueBookCount'] ?? 3);
                    $allowed_days = intval($member['IssueAllowedDays'] ?? 14);

                    // Check active checkouts count
                    $active_res = db_query($conn, "SELECT COUNT(*) as active_cnt FROM TransBookIssue WHERE MemberNumber = ? AND IssueStatus = 1", "i", [$member_id]);
                    $active_cnt = db_fetch_assoc($active_res)['active_cnt'] ?? 0;

                    if ($active_cnt >= $max_limit) {
                        $error = "Member '{$member['MemberName']}' has reached the maximum borrowing limit of $max_limit books (Currently borrowed: $active_cnt).";
                    } else {
                        // 3. Issue Book & Update Ledgers
                        $due_date = date('Y-m-d 23:59:59', strtotime($issue_date . " + $allowed_days days"));
                        $issue_datetime = date('Y-m-d H:i:s', strtotime($issue_date));

                        // Record in TransBookIssue
                        $sql_issue = "INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, AccessionNumber, MemberNumber, MemberName, IssueStatus) VALUES (?, ?, ?, ?, ?, ?, 1)";
                        $res_issue = db_query($conn, $sql_issue, "ssssis", [$issue_datetime, $due_date, $book['BookNumber'], $book['AccessionNumber'], $member_id, $member['MemberName']]);

                        if ($res_issue) {
                            // Update Stock Status to 2 (Issued)
                            db_query($conn, "UPDATE MstrBookStock SET BookStatus = 2 WHERE BookNumber = ?", "i", [$book['BookNumber']]);
                            $msg = "Book '{$book['BookName']}' issued successfully to {$member['MemberName']}! Due Date: " . date('d-M-Y', strtotime($due_date));
                        } else {
                            $error = "Failed to process book issuance.";
                        }
                    }
                }
            }
        }
    }
}

// Fetch active members for selection dropdown
$members = db_query($conn, "SELECT MemberNumber, MemberName, HouseName FROM MstrMember ORDER BY MemberName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-hand-holding-hand me-2"></i>Issue Book Engine</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($msg): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fa-solid fa-circle-check me-2"></i><?php echo $msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="issue.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Member *</label>
                        <select name="MemberNumber" class="form-select form-select-lg" required>
                            <option value="">-- Choose Member --</option>
                            <?php if ($members && db_num_rows($members) > 0): ?>
                                <?php while ($m = db_fetch_assoc($members)): ?>
                                    <option value="<?php echo $m['MemberNumber']; ?>" <?php echo (isset($_POST['MemberNumber']) && $_POST['MemberNumber'] == $m['MemberNumber']) ? 'selected' : ''; ?>>
                                        MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($m['MemberName']); ?> (<?php echo htmlspecialchars($m['HouseName'] ?? 'No Address'); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Book Accession Number *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" name="AccessionNumber" class="form-control" placeholder="Scan or type Accession No. (e.g. ACC1001)" required value="<?php echo htmlspecialchars($_POST['AccessionNumber'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Issue Date</label>
                        <input type="date" name="IssueDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold">
                            <i class="fa-solid fa-check-circle me-2"></i>Process Book Issuance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
