<?php
// modules/inventory/scanner.php - Barcode / RFID Fast Scanner Input Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Hardware Barcode / RFID Fast Scanner";
$msg = '';
$error = '';
$scanned_item = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim(sanitize_input($_POST['barcode_input'] ?? ''));
    $mode = sanitize_input($_POST['scanner_mode'] ?? 'lookup'); // lookup, fast_checkin, fast_checkout
    $member_id = intval($_POST['member_id'] ?? 0);

    if (!empty($barcode)) {
        // Query item by AccessionNumber, ISBNumber, or BookCode
        $sql = "SELECT b.*, a.AuthorName, p.PublisherName, c.CategoryName
                FROM MstrBookStock b
                LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                WHERE b.AccessionNumber = ? OR b.ISBNumber = ? OR b.BookCode = ? LIMIT 1";
        $res = db_query($conn, $sql, "sss", [$barcode, $barcode, $barcode]);

        if ($res && db_num_rows($res) > 0) {
            $scanned_item = db_fetch_assoc($res);

            if ($mode === 'fast_checkin') {
                if ($scanned_item['BookStatus'] == 2) {
                    // Perform Return
                    $issue_res = db_query($conn, "SELECT * FROM TransBookIssue WHERE AccessionNumber = ? AND IssueStatus = 1 ORDER BY IssueID DESC LIMIT 1", "s", [$scanned_item['AccessionNumber']]);
                    if ($issue_res && db_num_rows($issue_res) > 0) {
                        $issue = db_fetch_assoc($issue_res);
                        db_query($conn, "UPDATE TransBookIssue SET ReturnDate = datetime('now'), IssueStatus = 2 WHERE IssueID = ?", "i", [$issue['IssueID']]);
                        db_query($conn, "UPDATE MstrBookStock SET BookStatus = 1 WHERE BookNumber = ?", "i", [$scanned_item['BookNumber']]);
                        $msg = "FAST CHECK-IN COMPLETE: '{$scanned_item['BookName']}' returned to stock.";
                        $scanned_item['BookStatus'] = 1;
                    }
                } else {
                    $error = "Item '{$scanned_item['BookName']}' is already in stock / not checked out.";
                }
            } elseif ($mode === 'fast_checkout') {
                if ($member_id > 0) {
                    if ($scanned_item['BookStatus'] == 1) {
                        $mem_res = db_query($conn, "SELECT MemberName FROM MstrMember WHERE MemberNumber = ? LIMIT 1", "i", [$member_id]);
                        $m_name = ($mem_res && db_num_rows($mem_res) > 0) ? db_fetch_assoc($mem_res)['MemberName'] : 'Member';
                        $due = date('Y-m-d 23:59:59', strtotime('+14 days'));
                        db_query($conn, "INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, AccessionNumber, MemberNumber, MemberName, IssueStatus) VALUES (datetime('now'), ?, ?, ?, ?, ?, 1)", "sssis", [$due, $scanned_item['BookNumber'], $scanned_item['AccessionNumber'], $member_id, $m_name]);
                        db_query($conn, "UPDATE MstrBookStock SET BookStatus = 2 WHERE BookNumber = ?", "i", [$scanned_item['BookNumber']]);
                        $msg = "FAST CHECK-OUT COMPLETE: '{$scanned_item['BookName']}' issued to $m_name.";
                        $scanned_item['BookStatus'] = 2;
                    } else {
                        $error = "Item '{$scanned_item['BookName']}' is currently not available.";
                    }
                } else {
                    $error = "Please select a Member for Fast Check-out mode.";
                }
            }
        } else {
            $error = "No inventory item found matching Barcode / RFID tag '$barcode'.";
        }
    }
}

$members = db_query($conn, "SELECT MemberNumber, MemberName FROM MstrMember ORDER BY MemberName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / CATALOG & INVENTORY / SCANNER</div>
<h1 class="page-header-title"><i class="fa-solid fa-barcode text-primary me-2"></i>Hardware Barcode & RFID Fast Scanner Terminal</h1>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show fw-bold fs-5 py-3"><i class="fa-solid fa-circle-check me-2"></i><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show fw-bold fs-5 py-3"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="POST" action="scanner.php" id="scannerForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Scan Action Mode</label>
                    <select name="scanner_mode" id="scannerMode" class="form-select form-select-lg fw-bold text-primary">
                        <option value="lookup" selected>🔍 Lookup Item</option>
                        <option value="fast_checkin">📥 Fast Check-in (Return)</option>
                        <option value="fast_checkout">📤 Fast Check-out (Issue)</option>
                    </select>
                </div>
                <div class="col-md-4" id="memberSelectCol" style="display: none;">
                    <label class="form-label fw-bold">Select Borrower Member</label>
                    <select name="member_id" class="form-select form-select-lg">
                        <option value="">-- Choose Member --</option>
                        <?php if ($members && db_num_rows($members) > 0): ?>
                            <?php while ($m = db_fetch_assoc($members)): ?>
                                <option value="<?php echo $m['MemberNumber']; ?>">MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($m['MemberName']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Barcode / RFID Scanner Input *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-dark text-white"><i class="fa-solid fa-barcode"></i></span>
                        <input type="text" name="barcode_input" id="barcodeInput" class="form-control form-control-lg fw-bold" placeholder="Scan hardware barcode..." autofocus required>
                        <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="fa-solid fa-bolt me-1"></i>Scan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($scanned_item): ?>
    <div class="card border-primary shadow">
        <div class="card-header bg-primary text-white fw-bold fs-5">
            <i class="fa-solid fa-book-open me-2"></i>Scanned Inventory Item Detail
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-8">
                    <h3><?php echo htmlspecialchars($scanned_item['BookName']); ?></h3>
                    <p class="text-muted fs-5">Author: <strong><?php echo htmlspecialchars($scanned_item['AuthorName'] ?? 'Unassigned'); ?></strong> | Publisher: <strong><?php echo htmlspecialchars($scanned_item['PublisherName'] ?? 'N/A'); ?></strong></p>
                    <div class="d-flex gap-3">
                        <div><strong>Accession #:</strong> <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($scanned_item['AccessionNumber']); ?></span></div>
                        <div><strong>ISBN:</strong> <span class="badge bg-light text-dark border fs-6"><?php echo htmlspecialchars($scanned_item['ISBNumber'] ?? 'N/A'); ?></span></div>
                        <div><strong>Price:</strong> <span class="badge bg-success fs-6">₹<?php echo number_format($scanned_item['BookPrice'] ?? 0, 2); ?></span></div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <h5>Current Item Status:</h5>
                    <?php if ($scanned_item['BookStatus'] == 1): ?>
                        <span class="badge bg-success fs-4 p-2">AVAILABLE IN STOCK</span>
                    <?php elseif ($scanned_item['BookStatus'] == 2): ?>
                        <span class="badge bg-warning text-dark fs-4 p-2">CHECKED OUT</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-4 p-2">INACTIVE / WRITTEN OFF</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.getElementById('scannerMode').addEventListener('change', function() {
    var col = document.getElementById('memberSelectCol');
    if (this.value === 'fast_checkout') {
        col.style.display = 'block';
    } else {
        col.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
