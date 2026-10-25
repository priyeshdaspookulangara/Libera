<?php
// dashboard.php - Libera Main Dashboard Summary View
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$page_title = "Dashboard";
$fiscal = get_active_fiscal_year($conn);
$today = date('Y-m-d');

// 1. Stat Summary Metrics
$total_books_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock");
$total_books = db_fetch_assoc($total_books_res)['cnt'] ?? 0;

$active_checkouts_res = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE IssueStatus = 1");
$active_checkouts = db_fetch_assoc($active_checkouts_res)['cnt'] ?? 0;

$overdue_res = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE IssueStatus = 1 AND DueDate < ?", "s", [$today]);
$overdue_count = db_fetch_assoc($overdue_res)['cnt'] ?? 0;

$total_members_res = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrMember");
$total_members = db_fetch_assoc($total_members_res)['cnt'] ?? 0;

// Daily Cash Flow Total
$daily_cash_res = db_query($conn, "SELECT COALESCE(SUM(TransactionAmount), 0) as daily_total FROM AccTransaction WHERE TransactionDate = ?", "s", [$today]);
$daily_cash_flow = db_fetch_assoc($daily_cash_res)['daily_total'] ?? 0.0;

// Recent Book Issuances / Circulation Feed
$recent_issues = db_query($conn, "SELECT i.*, b.BookName FROM TransBookIssue i LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber ORDER BY i.IssueID DESC LIMIT 6");

// Recent Transactions Feed
$recent_trans = db_query($conn, "SELECT t.*, l.LedgerName FROM AccTransaction t LEFT JOIN MstrLedger l ON t.LedgerNumberHead = l.LedgerNumber ORDER BY t.AccTransNumber DESC LIMIT 6");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Section Title -->
<div class="breadcrumb-nav mb-1">HOME / DASHBOARD</div>
<h1 class="page-header-title">System Overview Dashboard</h1>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="text-muted small">
        Active Financial Period: <strong><?php echo htmlspecialchars($fiscal['YearDescription']); ?></strong>
    </div>
    <div>
        <a href="/modules/circulation/issue.php" class="btn btn-primary fw-bold me-2"><i class="fa-solid fa-hand-holding-hand me-1"></i>Issue Book</a>
        <a href="/modules/circulation/return.php" class="btn btn-dark fw-bold me-2"><i class="fa-solid fa-rotate-left me-1"></i>Return Book</a>
        <a href="/modules/accounts/vouchers.php" class="btn btn-success fw-bold"><i class="fa-solid fa-receipt me-1"></i>Post Voucher</a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Active Checkouts</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($active_checkouts); ?></h2>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-book-bookmark"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Overdue Alerts</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($overdue_count); ?></h2>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card bg-success text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Daily Cash Flow</h6>
                    <h2 class="mb-0 fw-bold">₹<?php echo number_format($daily_cash_flow, 2); ?></h2>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card bg-dark text-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Total Catalog / Members</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($total_books); ?> / <?php echo number_format($total_members); ?></h2>
                </div>
                <div class="fs-1 text-white-50"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Search Widget -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Quick Search Lookup Widget</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <form method="GET" action="/modules/inventory/books.php" class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search Book Catalog (Title, Accession #, ISBN)..." required>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i>Find Book</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="GET" action="/modules/members/members.php" class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search Members (Name, Phone)..." required>
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-search me-1"></i>Find Member</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Feeds Grid -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-arrows-rotate text-primary me-2"></i>Recent Circulation Activity</h5>
                <a href="/modules/circulation/issued_list.php" class="btn btn-sm btn-outline-primary">View All Checkouts</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Acc #</th>
                                <th>Book Title</th>
                                <th>Borrower</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_issues && db_num_rows($recent_issues) > 0): ?>
                                <?php while ($issue = db_fetch_assoc($recent_issues)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($issue['AccessionNumber']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($issue['BookName'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($issue['MemberName'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($issue['IssueStatus'] == 1): ?>
                                                <span class="badge bg-warning text-dark">Issued</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No recent circulation records.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Recent Double-Entry Ledgers</h5>
                <a href="/modules/accounts/vouchers.php" class="btn btn-sm btn-outline-success">Post New Voucher</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Head Account</th>
                                <th>Remarks</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_trans && db_num_rows($recent_trans) > 0): ?>
                                <?php while ($trans = db_fetch_assoc($recent_trans)): ?>
                                    <tr>
                                        <td><small><?php echo date('d-M', strtotime($trans['TransactionDate'])); ?></small></td>
                                        <td><strong><?php echo htmlspecialchars($trans['LedgerName'] ?? 'General'); ?></strong></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($trans['TransactionRemarks'] ?? 'N/A'); ?></small></td>
                                        <td class="text-end fw-bold text-success">₹<?php echo number_format($trans['TransactionAmount'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No accounting entries posted today.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
