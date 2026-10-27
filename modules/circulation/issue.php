<?php
// modules/circulation/issue.php - Book Issuance Engine with Advanced Book Finder
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// AJAX Search Handler
if (isset($_GET['action']) && $_GET['action'] === 'search_books') {
    header('Content-Type: application/json');

    $q = sanitize_input($_GET['q'] ?? '');
    $book_num = sanitize_input($_GET['BookNumber'] ?? '');
    $book_name = sanitize_input($_GET['BookName'] ?? '');
    $book_remarks = sanitize_input($_GET['BookRemarks'] ?? '');
    $author_name = sanitize_input($_GET['AutherName'] ?? '');
    $book_class = sanitize_input($_GET['BookClass'] ?? '');
    $book_code = sanitize_input($_GET['BookCode'] ?? '');
    $call_num = sanitize_input($_GET['CallNumber'] ?? '');
    $book_price = sanitize_input($_GET['BookPrice'] ?? '');

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($q)) {
        $where[] = "(b.BookNumber LIKE ? OR b.BookName LIKE ? OR b.BookRemarks LIKE ? OR a.AuthorName LIKE ? OR c.ClassName LIKE ? OR b.BookCode LIKE ? OR b.CallNumber LIKE ? OR b.AccessionNumber LIKE ?)";
        $searchTerm = "%$q%";
        for ($i = 0; $i < 8; $i++) {
            $params[] = $searchTerm;
            $types .= "s";
        }
    }

    if (!empty($book_num)) {
        $where[] = "b.BookNumber = ?";
        $params[] = intval($book_num);
        $types .= "i";
    }

    if (!empty($book_name)) {
        $where[] = "b.BookName LIKE ?";
        $params[] = "%$book_name%";
        $types .= "s";
    }

    if (!empty($book_remarks)) {
        $where[] = "b.BookRemarks LIKE ?";
        $params[] = "%$book_remarks%";
        $types .= "s";
    }

    if (!empty($author_name)) {
        $where[] = "a.AuthorName LIKE ?";
        $params[] = "%$author_name%";
        $types .= "s";
    }

    if (!empty($book_class)) {
        $where[] = "(c.ClassName LIKE ? OR c.ClassCode LIKE ?)";
        $params[] = "%$book_class%";
        $params[] = "%$book_class%";
        $types .= "ss";
    }

    if (!empty($book_code)) {
        $where[] = "b.BookCode LIKE ?";
        $params[] = "%$book_code%";
        $types .= "s";
    }

    if (!empty($call_num)) {
        $where[] = "b.CallNumber LIKE ?";
        $params[] = "%$call_num%";
        $types .= "s";
    }

    if (!empty($book_price) && is_numeric($book_price)) {
        $where[] = "b.BookPrice <= ?";
        $params[] = floatval($book_price);
        $types .= "d";
    }

    $sql = "SELECT b.BookNumber, b.BookName, b.BookRemarks, b.BookCode, b.CallNumber, b.AccessionNumber, b.BookPrice, b.BookStatus,
                   COALESCE(a.AuthorName, 'Unknown') as AutherName,
                   COALESCE(c.ClassName, 'General') as BookClass
            FROM MstrBookStock b
            LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
            LEFT JOIN MstrClassification c ON b.ClassNumber = c.ClassNumber
            WHERE " . implode(" AND ", $where) . " ORDER BY b.BookNumber DESC LIMIT 50";

    $res = db_query($conn, $sql, $types, $params);
    $results = [];

    if ($res && db_num_rows($res) > 0) {
        while ($row = db_fetch_assoc($res)) {
            $results[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $results]);
    exit;
}

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
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fa-solid fa-hand-holding-hand me-2"></i>Issue Book Engine</h4>
                <button type="button" class="btn btn-light btn-sm fw-bold text-primary" data-bs-toggle="modal" data-bs-target="#bookSearchModal">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Advanced Book Finder
                </button>
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
                            <input type="text" id="AccessionNumberInput" name="AccessionNumber" class="form-control" placeholder="Scan or type Accession No. (e.g. ACC1001)" required value="<?php echo htmlspecialchars($_POST['AccessionNumber'] ?? ''); ?>">
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bookSearchModal">
                                <i class="fa-solid fa-search me-1"></i> Finder
                            </button>
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

<!-- Advanced Book Finder Modal -->
<div class="modal fade" id="bookSearchModal" tabindex="-1" aria-labelledby="bookSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="bookSearchModalLabel"><i class="fa-solid fa-search me-2"></i>Advanced Book Catalog Finder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="advancedBookSearchForm" class="row g-3 mb-4 bg-light p-3 rounded border">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Keyword (Any Field)</label>
                        <input type="text" id="search_q" class="form-control" placeholder="Search title, author, code...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Book Number</label>
                        <input type="number" id="search_BookNumber" class="form-control" placeholder="e.g. 101">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Title (Name)</label>
                        <input type="text" id="search_BookName" class="form-control" placeholder="Book Title">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Author Name</label>
                        <input type="text" id="search_AutherName" class="form-control" placeholder="Author Name">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Class / Category</label>
                        <input type="text" id="search_BookClass" class="form-control" placeholder="Class / Category">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Book Code</label>
                        <input type="text" id="search_BookCode" class="form-control" placeholder="Code">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Call Number</label>
                        <input type="text" id="search_CallNumber" class="form-control" placeholder="Call No">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Remarks</label>
                        <input type="text" id="search_BookRemarks" class="form-control" placeholder="Remarks">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Max Price (₹)</label>
                        <input type="number" step="0.01" id="search_BookPrice" class="form-control" placeholder="Price">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" id="resetSearchBtn" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-undo me-1"></i>Reset</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-magnifying-glass me-1"></i>Search Catalog</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" id="searchResultsTable">
                        <thead class="table-secondary">
                            <tr>
                                <th>Book #</th>
                                <th>Accession #</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Class</th>
                                <th>Code / Call #</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Use the search form above to find books by any parameter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchForm = document.getElementById("advancedBookSearchForm");
    const resultsBody = document.getElementById("searchResultsBody");
    const resetBtn = document.getElementById("resetSearchBtn");
    const accessionInput = document.getElementById("AccessionNumberInput");

    function executeSearch() {
        const params = new URLSearchParams();
        params.append("action", "search_books");

        const fields = ["q", "BookNumber", "BookName", "BookRemarks", "AutherName", "BookClass", "BookCode", "CallNumber", "BookPrice"];
        fields.forEach(f => {
            const val = document.getElementById("search_" + f).value.trim();
            if (val) params.append(f, val);
        });

        resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> Loading catalog results...</td></tr>';

        fetch("issue.php?" + params.toString())
            .then(res => res.json())
            .then(data => {
                if (data.status === "success" && data.data.length > 0) {
                    let html = "";
                    data.data.forEach(book => {
                        const isAvailable = book.BookStatus == 1;
                        const statusBadge = isAvailable
                            ? '<span class="badge bg-success">Available</span>'
                            : '<span class="badge bg-danger">Not Available</span>';

                        const selectBtn = isAvailable
                            ? `<button type="button" class="btn btn-sm btn-primary select-book-btn" data-accession="${book.AccessionNumber}"><i class="fa-solid fa-check me-1"></i>Select</button>`
                            : `<button type="button" class="btn btn-sm btn-secondary" disabled>Unavailable</button>`;

                        html += `<tr>
                            <td><code>#${book.BookNumber}</code></td>
                            <td><strong>${book.AccessionNumber || 'N/A'}</strong></td>
                            <td class="fw-bold">${escapeHtml(book.BookName || '')}</td>
                            <td>${escapeHtml(book.AutherName || '')}</td>
                            <td><span class="badge bg-light text-dark border">${escapeHtml(book.BookClass || '')}</span></td>
                            <td><small>${escapeHtml(book.BookCode || '')} / ${escapeHtml(book.CallNumber || '')}</small></td>
                            <td>₹${parseFloat(book.BookPrice || 0).toFixed(2)}</td>
                            <td>${statusBadge}</td>
                            <td class="text-end">${selectBtn}</td>
                        </tr>`;
                    });
                    resultsBody.innerHTML = html;

                    // Attach click handlers to Select buttons
                    document.querySelectorAll(".select-book-btn").forEach(btn => {
                        btn.addEventListener("click", function() {
                            const accession = this.getAttribute("data-accession");
                            accessionInput.value = accession;
                            // Close modal
                            const modalElem = document.getElementById("bookSearchModal");
                            const modal = bootstrap.Modal.getInstance(modalElem);
                            if (modal) modal.hide();
                        });
                    });
                } else {
                    resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No matching books found in catalog.</td></tr>';
                }
            })
            .catch(err => {
                console.error(err);
                resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error fetching book search results.</td></tr>';
            });
    }

    searchForm.addEventListener("submit", function(e) {
        e.preventDefault();
        executeSearch();
    });

    resetBtn.addEventListener("click", function() {
        searchForm.reset();
        resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Use the search form above to find books by any parameter.</td></tr>';
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
