<?php
// dashboard.php - Libera Library Management Dashboard
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$page_title = "Library Dashboard";
$today = date('Y-m-d');

// 1. Core Library KPI Metrics
$total_books_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock");
$total_books = db_fetch_assoc($total_books_res)['cnt'] ?? 0;

$available_books_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock WHERE BookStatus = 1");
$available_books = db_fetch_assoc($available_books_res)['cnt'] ?? 0;

$active_checkouts_res = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE IssueStatus = 1 OR ReturnDate IS NULL");
$active_checkouts = db_fetch_assoc($active_checkouts_res)['cnt'] ?? 0;

$overdue_res = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE (IssueStatus = 1 OR ReturnDate IS NULL) AND DueDate < ?", "s", [$today]);
$overdue_count = db_fetch_assoc($overdue_res)['cnt'] ?? 0;

$total_members_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrMember");
$total_members = db_fetch_assoc($total_members_res)['cnt'] ?? 0;

$today_issues_res = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE DATE(IssueDate) = ?", "s", [$today]);
$today_issues = db_fetch_assoc($today_issues_res)['cnt'] ?? 0;

// Book Condition Breakdown
$missing_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock WHERE BookStatus = 3");
$missing_count = db_fetch_assoc($missing_res)['cnt'] ?? 0;

$damaged_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock WHERE BookStatus = 4");
$damaged_count = db_fetch_assoc($damaged_res)['cnt'] ?? 0;

// 2. Active Overdue Borrowers Feed
$overdue_items = db_query($conn, "SELECT i.*, b.BookName, b.CallNumber, m.PhoneHome, m.HouseName
                                  FROM TransBookIssue i
                                  LEFT JOIN MstrBookStock b ON (i.AccessionNumber = b.AccessionNumber OR i.BookNumber = b.BookNumber)
                                  LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
                                  WHERE (i.IssueStatus = 1 OR i.ReturnDate IS NULL) AND i.DueDate < ?
                                  ORDER BY i.DueDate ASC LIMIT 6", "s", [$today]);

// 3. Recent Circulation Activity (Issues & Returns)
$recent_issues = db_query($conn, "SELECT i.*, b.BookName, b.CallNumber
                                  FROM TransBookIssue i
                                  LEFT JOIN MstrBookStock b ON (i.AccessionNumber = b.AccessionNumber OR i.BookNumber = b.BookNumber)
                                  ORDER BY i.IssueID DESC LIMIT 8");

// 4. Popular / Active Categories
$popular_cats = db_query($conn, "SELECT c.CategoryName, COUNT(b.BookNumber) as book_count
                                 FROM MstrCategory c
                                 JOIN MstrBookStock b ON c.CategoryNumber = b.CategoryNumber
                                 GROUP BY c.CategoryNumber, c.CategoryName
                                 ORDER BY book_count DESC LIMIT 5");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Section Title -->
<div class="breadcrumb-nav mb-1">HOME / LIBRARY DASHBOARD</div>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-header-title mb-1"><i class="fa-solid fa-book-open-reader text-primary me-2"></i>Library Circulation & Inventory Overview</h1>
        <div class="text-muted small">Live catalog metrics, active member borrowings, and overdue alerts</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/modules/circulation/issue.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-hand-holding-hand me-1"></i>Issue Book</a>
        <a href="/modules/circulation/return.php" class="btn btn-success fw-bold"><i class="fa-solid fa-rotate-left me-1"></i>Return Book</a>
        <a href="/modules/inventory/books.php?tab=all" class="btn btn-outline-secondary"><i class="fa-solid fa-plus me-1"></i>Add Book</a>
    </div>
</div>

<!-- Primary Library KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Total Books -->
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white p-3 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1 small">Total Book Catalog</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($total_books); ?></h2>
                    <div class="small text-white-50 mt-1"><?php echo number_format($available_books); ?> Available for checkout</div>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-books"></i></div>
            </div>
        </div>
    </div>

    <!-- Active Checkouts -->
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark p-3 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-dark text-opacity-75 text-uppercase fw-bold mb-1 small">Active Checkouts</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($active_checkouts); ?></h2>
                    <div class="small text-dark text-opacity-75 mt-1"><?php echo number_format($today_issues); ?> Issued today</div>
                </div>
                <div class="fs-1 text-dark text-opacity-25"><i class="fa-solid fa-book-bookmark"></i></div>
            </div>
        </div>
    </div>

    <!-- Overdue Alerts -->
    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white p-3 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1 small">Overdue Alerts</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($overdue_count); ?></h2>
                    <div class="small text-white-50 mt-1">Requires immediate notice</div>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </div>
        </div>
    </div>

    <!-- Registered Members -->
    <div class="col-md-3">
        <div class="card stat-card bg-dark text-white p-3 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1 small">Enrolled Members</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($total_members); ?></h2>
                    <div class="small text-white-50 mt-1">Active library patrons</div>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Book Condition Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="/modules/inventory/books.php?tab=all" class="text-decoration-none">
            <div class="card border-0 bg-light p-3 rounded shadow-sm hover-shadow">
                <div class="d-flex align-items-center">
                    <div class="bg-success text-white rounded p-2 me-3"><i class="fa-solid fa-circle-check fs-4"></i></div>
                    <div>
                        <div class="fw-bold text-dark"><?php echo number_format($available_books); ?> Available In Rack</div>
                        <div class="text-muted small">Ready to issue immediately</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/modules/inventory/books.php?tab=missing" class="text-decoration-none">
            <div class="card border-0 bg-light p-3 rounded shadow-sm hover-shadow">
                <div class="d-flex align-items-center">
                    <div class="bg-warning text-dark rounded p-2 me-3"><i class="fa-solid fa-eye-slash fs-4"></i></div>
                    <div>
                        <div class="fw-bold text-dark"><?php echo number_format($missing_count); ?> Missing Books</div>
                        <div class="text-muted small">Items not found during stock audit</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/modules/inventory/books.php?tab=damage" class="text-decoration-none">
            <div class="card border-0 bg-light p-3 rounded shadow-sm hover-shadow">
                <div class="d-flex align-items-center">
                    <div class="bg-danger text-white rounded p-2 me-3"><i class="fa-solid fa-heart-crack fs-4"></i></div>
                    <div>
                        <div class="fw-bold text-dark"><?php echo number_format($damaged_count); ?> Damaged Copies</div>
                        <div class="text-muted small">Sent for binding or write-off</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Quick Search Lookup Widget -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Quick Library Search</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <form method="GET" action="/modules/inventory/books.php" class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search Books (Malayalam / Manglish / ISBN / Title)..." required>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i>Search Catalog</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="GET" action="/modules/members/members.php" class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search Member Name, Phone, or House..." required>
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-user me-1"></i>Search Member</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Main Feed: Overdues Alert & Circulation Feed -->
<div class="row g-4 mb-4">
    <!-- Overdue Alert Table -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Overdue Borrowed Items</h5>
                <a href="/modules/circulation/issued_list.php?filter=overdue" class="btn btn-sm btn-outline-danger">View All Overdues</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Acc #</th>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Due Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($overdue_items && db_num_rows($overdue_items) > 0): ?>
                                <?php while ($item = db_fetch_assoc($overdue_items)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($item['AccessionNumber'] ?? 'N/A'); ?></span></td>
                                        <td>
                                            <strong class="text-dark"><?php echo htmlspecialchars($item['BookName'] ?? 'Book #' . $item['BookNumber']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($item['MemberName'] ?? 'N/A'); ?></div>
                                            <small class="text-muted"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($item['PhoneHome'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><span class="badge bg-danger"><?php echo date('d-M-Y', strtotime($item['DueDate'])); ?></span></td>
                                        <td class="text-end">
                                            <a href="/modules/circulation/return.php" class="btn btn-sm btn-outline-success">Return</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>
                                        Great! No books are currently overdue.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Circulation Feed -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-arrows-rotate me-2"></i>Recent Book Issues & Returns</h5>
                <a href="/modules/circulation/issued_list.php" class="btn btn-sm btn-outline-primary">View Full Register</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Acc #</th>
                                <th>Book Title</th>
                                <th>Borrower</th>
                                <th>Issue Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_issues && db_num_rows($recent_issues) > 0): ?>
                                <?php while ($issue = db_fetch_assoc($recent_issues)): ?>
                                    <?php
                                    $is_returned = !empty($issue['ReturnDate']) || ($issue['IssueStatus'] == 2);
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($issue['AccessionNumber'] ?? 'N/A'); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($issue['BookName'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($issue['MemberName'] ?? 'N/A'); ?></td>
                                        <td><small><?php echo date('d-M', strtotime($issue['IssueDate'])); ?></small></td>
                                        <td>
                                            <?php if ($is_returned): ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Issued</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No recent circulation records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Category Distribution & Quick Links -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-tags me-2"></i>Top Catalog Categories</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($popular_cats && db_num_rows($popular_cats) > 0): ?>
                        <?php while ($cat = db_fetch_assoc($popular_cats)): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-bookmark text-primary me-2"></i><?php echo htmlspecialchars($cat['CategoryName']); ?></span>
                                <span class="badge bg-primary rounded-pill"><?php echo number_format($cat['book_count']); ?> Books</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">No categorized books found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-compass me-2"></i>Circulation Quick Shortcuts</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="/modules/circulation/issue.php" class="btn btn-outline-primary w-100 py-3 text-start">
                            <i class="fa-solid fa-hand-holding-hand fs-4 mb-2 d-block"></i>
                            <strong>New Book Issue</strong>
                            <div class="small text-muted">Issue book to member</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/modules/circulation/return.php" class="btn btn-outline-success w-100 py-3 text-start">
                            <i class="fa-solid fa-rotate-left fs-4 mb-2 d-block"></i>
                            <strong>Process Return</strong>
                            <div class="small text-muted">Collect & return book</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/modules/circulation/reservation.php" class="btn btn-outline-warning text-dark w-100 py-3 text-start">
                            <i class="fa-solid fa-clock fs-4 mb-2 d-block"></i>
                            <strong>Reservations</strong>
                            <div class="small text-muted">Waitlist queue management</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/modules/reports/library_reports.php" class="btn btn-outline-secondary w-100 py-3 text-start">
                            <i class="fa-solid fa-print fs-4 mb-2 d-block"></i>
                            <strong>Circulation Registers</strong>
                            <div class="small text-muted">Printable library records</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
