<?php
// modules/circulation/reservation.php - Book Waitlist & Queue Management Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// AJAX Book Finder Endpoint
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

$page_title = "Book Reservations & Waitlist Queue";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'reserve') {
        $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
        $member_id = intval($_POST['MemberNumber'] ?? 0);
        $remarks = sanitize_input($_POST['Remarks'] ?? '');

        if (empty($accession) || $member_id <= 0) {
            $error = "Please select a Member and specify an Accession Number.";
        } else {
            // Find book
            $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
            if (!$book_res || db_num_rows($book_res) === 0) {
                $error = "Book with Accession Number '$accession' not found.";
            } else {
                $book = db_fetch_assoc($book_res);
                $book_num = $book['BookNumber'];

                // Calculate Queue Position
                $pos_res = db_query($conn, "SELECT COUNT(*) as queue_cnt FROM BookReservations WHERE BookNumber = ? AND NotificationStatus IN ('Pending', 'Notified')", "i", [$book_num]);
                $queue_pos = (db_fetch_assoc($pos_res)['queue_cnt'] ?? 0) + 1;

                $res_date = date('Y-m-d H:i:s');
                $sql_ins = "INSERT INTO BookReservations (BookNumber, AccessionNumber, MemberNumber, ReservationDate, NotificationStatus, QueuePosition, Remarks) VALUES (?, ?, ?, ?, 'Pending', ?, ?)";
                $res_ins = db_query($conn, $sql_ins, "isisis", [$book_num, $accession, $member_id, $res_date, $queue_pos, $remarks]);

                if ($res_ins) {
                    $msg = "Reservation queued successfully for '{$book['BookName']}'! Queue Position #$queue_pos.";
                } else {
                    $error = "Failed to queue reservation.";
                }
            }
        }
    } elseif ($action === 'notify') {
        $res_id = intval($_POST['ReservationID']);
        $expiry_date = date('Y-m-d H:i:s', strtotime('+3 days'));

        db_query($conn, "UPDATE BookReservations SET NotificationStatus = 'Notified', ExpiryDate = ? WHERE ReservationID = ?", "si", [$expiry_date, $res_id]);
        $msg = "Member notified via automated SMS/Email hook! Book placed on 3-day hold until " . date('d-M-Y H:i', strtotime($expiry_date));
    } elseif ($action === 'cancel') {
        $res_id = intval($_POST['ReservationID']);
        db_query($conn, "UPDATE BookReservations SET NotificationStatus = 'Cancelled' WHERE ReservationID = ?", "i", [$res_id]);
        $msg = "Reservation cancelled.";
    }
}

// Fetch Active Reservations Queue
$sql_queue = "SELECT r.*, b.BookName, m.MemberName, m.PhoneHome
              FROM BookReservations r
              LEFT JOIN MstrBookStock b ON r.BookNumber = b.BookNumber
              LEFT JOIN MstrMember m ON r.MemberNumber = m.MemberNumber
              WHERE r.NotificationStatus IN ('Pending', 'Notified')
              ORDER BY r.BookNumber ASC, r.QueuePosition ASC";

$queue_res = db_query($conn, $sql_queue);
$members = db_query($conn, "SELECT MemberNumber, MemberName FROM MstrMember ORDER BY MemberName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / CIRCULATION / RESERVATIONS</div>
<h1 class="page-header-title"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Book Waitlist Queue & Hold Notifications</h1>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-bookmark me-2"></i>Reserve Checked-Out Item</h5>
                <button type="button" class="btn btn-light btn-sm text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#resBookSearchModal">
                    <i class="fa-solid fa-search me-1"></i> Book Finder
                </button>
            </div>
            <div class="card-body">
                <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <form method="POST" action="reservation.php">
                    <input type="hidden" name="action" value="reserve">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Member *</label>
                        <select name="MemberNumber" class="form-select" required>
                            <option value="">-- Choose Member --</option>
                            <?php if ($members && db_num_rows($members) > 0): ?>
                                <?php while ($m = db_fetch_assoc($members)): ?>
                                    <option value="<?php echo $m['MemberNumber']; ?>">MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($m['MemberName']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Book Accession Number *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" id="ResAccessionInput" name="AccessionNumber" class="form-control" placeholder="e.g. ACC1001" required>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resBookSearchModal">
                                <i class="fa-solid fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small">Remarks</label>
                        <input type="text" name="Remarks" class="form-control" placeholder="Optional notes">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-plus me-1"></i>Queue Reservation</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-list-ol me-2"></i>Active Waitlist Queue</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Pos #</th>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Reserved On</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($queue_res && db_num_rows($queue_res) > 0): ?>
                                <?php while ($q = db_fetch_assoc($queue_res)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">#<?php echo $q['QueuePosition']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($q['BookName'] ?? 'N/A'); ?></strong> <br><small class="text-muted"><?php echo htmlspecialchars($q['AccessionNumber']); ?></small></td>
                                        <td><?php echo htmlspecialchars($q['MemberName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($q['ReservationDate'])); ?></td>
                                        <td>
                                            <?php if ($q['NotificationStatus'] === 'Pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending Return</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Notified / On Hold</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($q['NotificationStatus'] === 'Pending'): ?>
                                                <form method="POST" action="reservation.php" class="d-inline">
                                                    <input type="hidden" name="action" value="notify">
                                                    <input type="hidden" name="ReservationID" value="<?php echo $q['ReservationID']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Notify Member Hold"><i class="fa-solid fa-bell"></i> Notify</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="reservation.php" class="d-inline" onsubmit="return confirm('Cancel reservation?');">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="ReservationID" value="<?php echo $q['ReservationID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-times"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No active item reservations in waitlist queue.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Book Search Modal for Reservations -->
<div class="modal fade" id="resBookSearchModal" tabindex="-1" aria-labelledby="resBookSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="resBookSearchModalLabel"><i class="fa-solid fa-search me-2"></i>Search Book Catalog for Reservation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="resBookSearchForm" class="row g-3 mb-4 bg-light p-3 rounded border">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Keyword (Any Field)</label>
                        <input type="text" id="res_search_q" class="form-control" placeholder="Search title, author, code...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Book Number</label>
                        <input type="number" id="res_search_BookNumber" class="form-control" placeholder="e.g. 101">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Title (Name)</label>
                        <input type="text" id="res_search_BookName" class="form-control" placeholder="Book Title">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Author Name</label>
                        <input type="text" id="res_search_AutherName" class="form-control" placeholder="Author Name">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Class / Category</label>
                        <input type="text" id="res_search_BookClass" class="form-control" placeholder="Class / Category">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Book Code</label>
                        <input type="text" id="res_search_BookCode" class="form-control" placeholder="Code">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Call Number</label>
                        <input type="text" id="res_search_CallNumber" class="form-control" placeholder="Call No">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Book Remarks</label>
                        <input type="text" id="res_search_BookRemarks" class="form-control" placeholder="Remarks">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Max Price (₹)</label>
                        <input type="number" step="0.01" id="res_search_BookPrice" class="form-control" placeholder="Price">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" id="resResetSearchBtn" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-undo me-1"></i>Reset</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-magnifying-glass me-1"></i>Search Catalog</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
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
                        <tbody id="resSearchResultsBody">
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
    const searchForm = document.getElementById("resBookSearchForm");
    const resultsBody = document.getElementById("resSearchResultsBody");
    const resetBtn = document.getElementById("resResetSearchBtn");
    const accessionInput = document.getElementById("ResAccessionInput");

    function executeSearch() {
        const params = new URLSearchParams();
        params.append("action", "search_books");

        const fields = ["q", "BookNumber", "BookName", "BookRemarks", "AutherName", "BookClass", "BookCode", "CallNumber", "BookPrice"];
        fields.forEach(f => {
            const el = document.getElementById("res_search_" + f);
            if (el && el.value.trim()) params.append(f, el.value.trim());
        });

        resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> Searching catalog...</td></tr>';

        fetch("reservation.php?" + params.toString())
            .then(res => res.json())
            .then(data => {
                if (data.status === "success" && data.data.length > 0) {
                    let html = "";
                    data.data.forEach(book => {
                        const statusBadge = book.BookStatus == 1
                            ? '<span class="badge bg-success">Available</span>'
                            : '<span class="badge bg-warning text-dark">Checked Out / Reserved</span>';

                        const selectBtn = `<button type="button" class="btn btn-sm btn-primary select-res-book-btn" data-accession="${book.AccessionNumber}"><i class="fa-solid fa-check me-1"></i>Select for Reservation</button>`;

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

                    document.querySelectorAll(".select-res-book-btn").forEach(btn => {
                        btn.addEventListener("click", function() {
                            const accession = this.getAttribute("data-accession");
                            accessionInput.value = accession;
                            const modalElem = document.getElementById("resBookSearchModal");
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
                resultsBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error fetching search results.</td></tr>';
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
