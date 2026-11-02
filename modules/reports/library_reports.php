<?php
// modules/reports/library_reports.php - Enterprise Print-Ready Library Reports Module
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$report_type = $_GET['type'] ?? 'issue_register';
$page_title = "Library Reports - " . ucwords(str_replace('_', ' ', $report_type));

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$cat_id     = isset($_GET['category']) && $_GET['category'] !== '' ? intval($_GET['category']) : null;
$class_id   = isset($_GET['member_class']) && $_GET['member_class'] !== '' ? intval($_GET['member_class']) : null;
$type_id    = isset($_GET['member_type']) && $_GET['member_type'] !== '' ? intval($_GET['member_type']) : null;
$search_q   = trim($_GET['q'] ?? '');

// Fetch Categories & Member Classes/Types for filter dropdowns
$categories = db_query($conn, "SELECT CategoryNumber, CategoryName FROM MstrCategory ORDER BY CategoryName ASC");
$member_types = db_query($conn, "SELECT MemberTypeNumber, MemberTypeName FROM MstrMemberType ORDER BY MemberTypeName ASC");
$member_classes = db_query($conn, "SELECT MemberClassNumber, MemberClassName FROM MstrMemberClass ORDER BY MemberClassName ASC");

// Organization Info for print headers
$company = get_company_profile($conn);

// Process Report Queries
$report_rows = [];
$report_subtotals = [];
$report_summary = [];

switch ($report_type) {
    case 'issue_register':
        $sql = "SELECT t.SlNo, t.IssueDate, t.DueDate, t.ReturnDate, t.FineAmount, t.IssueStatus,
                       b.BookNumber, b.BookName, b.AccessionNumber, b.CallNumber, b.BookCode,
                       m.MemberNumber, m.MemberName, m.PhoneHome, mt.MemberTypeName
                FROM TransBookIssue t
                JOIN MstrBookStock b ON t.BookNumber = b.BookNumber
                JOIN MstrMember m ON t.MemberNumber = m.MemberNumber
                LEFT JOIN MstrMemberType mt ON m.MemberRemarks = mt.MemberTypeName OR m.MemberNumber = mt.MemberTypeNumber
                WHERE 1=1";
        $params = [];
        $types = '';
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $sql .= " AND DATE(t.IssueDate) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }
        $sql .= " ORDER BY t.IssueDate DESC, t.SlNo DESC";
        $res = !empty($params) ? db_query($conn, $sql, $types, $params) : db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) { $report_rows[] = $r; }
        }
        break;

    case 'stock_register':
        $sql = "SELECT b.BookNumber, b.BookName, b.AccessionNumber, b.CallNumber, b.BookCode, b.ISBNumber,
                       b.BookPrice, b.BookStatus, b.BookRemarks,
                       a.AuthorName, p.PublisherName, c.CategoryName, d.ClassName as Classification
                FROM MstrBookStock b
                LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                LEFT JOIN MstrClassification d ON b.ClassNumber = d.ClassNumber
                WHERE 1=1";
        if ($cat_id) { $sql .= " AND b.CategoryNumber = $cat_id"; }
        if ($search_q) {
            $s = db_escape($conn, $search_q);
            $sql .= " AND (b.BookName LIKE '%$s%' OR b.AccessionNumber LIKE '%$s%' OR b.CallNumber LIKE '%$s%' OR b.BookCode LIKE '%$s%' OR a.AuthorName LIKE '%$s%')";
        }
        $sql .= " ORDER BY b.BookNumber ASC";
        $res = db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) { $report_rows[] = $r; }
        }
        break;

    case 'category_books':
        $sql = "SELECT c.CategoryNumber, c.CategoryName,
                       b.BookNumber, b.BookName, b.AccessionNumber, b.CallNumber, b.BookCode, b.ISBNumber,
                       b.BookPrice, b.BookStatus, a.AuthorName, p.PublisherName
                FROM MstrBookStock b
                LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                WHERE 1=1";
        if ($cat_id) { $sql .= " AND b.CategoryNumber = $cat_id"; }
        $sql .= " ORDER BY c.CategoryName ASC, b.BookName ASC";
        $res = db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) {
                $cname = $r['CategoryName'] ?: 'Uncategorized';
                $report_rows[$cname][] = $r;
            }
        }
        break;

    case 'lost_damaged':
        // Lost or Damaged Book List (BookStatus 2=Lost, 3=Damaged, 4=Missing, or 0=Write-off)
        $sql = "SELECT b.BookNumber, b.BookName, b.AccessionNumber, b.CallNumber, b.BookCode, b.ISBNumber,
                       b.BookPrice, b.BookStatus, b.BookRemarks,
                       a.AuthorName, p.PublisherName, c.CategoryName
                FROM MstrBookStock b
                LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                WHERE b.BookStatus IN (0, 2, 3, 4)
                ORDER BY b.BookStatus ASC, b.BookName ASC";
        $res = db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) { $report_rows[] = $r; }
        }
        break;

    case 'member_register':
        // Member register (category-wise / type-wise and class-wise)
        $sql = "SELECT m.MemberNumber, m.MemberName, m.PhoneHome, m.HouseName, m.MemBirthDate,
                       m.StudentSex, m.MemberRemarks,
                       md.MemberJoinDate, md.ApprovedDate, md.BloodGroupStr, md.AadharNumber, md.TerminationDate, md.MemberEmail,
                       mt.MemberTypeName, mt.MembershipAmount, mt.SubscriptionAmount
                FROM MstrMember m
                LEFT JOIN MstrMemberDetails md ON m.MemberNumber = md.MemberNumber
                LEFT JOIN MstrMemberType mt ON m.MemberRemarks = mt.MemberTypeName OR m.MemberNumber = mt.MemberTypeNumber
                WHERE 1=1";
        $params = [];
        $types = '';
        if ($type_id) {
            $sql .= " AND mt.MemberTypeNumber = ?";
            $params[] = $type_id;
            $types .= 'i';
        }
        if ($search_q) {
            $sql .= " AND (m.MemberName LIKE ? OR m.MemberNumber LIKE ? OR m.PhoneHome LIKE ?)";
            $wild = '%' . $search_q . '%';
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $types .= 'sss';
        }
        $sql .= " ORDER BY mt.MemberTypeName ASC, m.MemberNumber ASC";
        $res = !empty($params) ? db_query($conn, $sql, $types, $params) : db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) {
                $group_key = $r['MemberTypeName'] ?: 'General Members';
                $report_rows[$group_key][] = $r;
            }
        }
        break;

    case 'member_due':
        // Member due list: currently checked out items, overdue days, fine calculation
        $sql = "SELECT t.SlNo, t.IssueDate, t.DueDate, t.IssueStatus,
                       b.BookNumber, b.BookName, b.AccessionNumber, b.CallNumber, b.BookCode,
                       m.MemberNumber, m.MemberName, m.PhoneHome,
                       mt.MemberTypeName, mt.IssueFineAmount
                FROM TransBookIssue t
                JOIN MstrBookStock b ON t.BookNumber = b.BookNumber
                JOIN MstrMember m ON t.MemberNumber = m.MemberNumber
                LEFT JOIN MstrMemberType mt ON m.MemberRemarks = mt.MemberTypeName OR m.MemberNumber = mt.MemberTypeNumber
                WHERE t.ReturnDate IS NULL OR t.ReturnDate = ''
                ORDER BY t.DueDate ASC";
        $res = db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) {
                $due_ts = strtotime($r['DueDate']);
                $diff = time() - $due_ts;
                $days = ($diff > 0 && !empty($r['DueDate'])) ? intval(floor($diff / 86400)) : 0;
                $r['OverdueDays'] = $days;
                $rate = floatval(($r['IssueFineAmount'] ?? 0) > 0 ? $r['IssueFineAmount'] : 1.0);
                $r['CalculatedFine'] = ($days > 0) ? ($days * $rate) : 0;
                $report_rows[] = $r;
            }
        }
        break;

    case 'fine_list':
        // Fine List (fines accrued, collected, or waived from TransBookIssue and Accounting vouchers)
        $sql = "SELECT t.SlNo, t.IssueDate, t.DueDate, t.ReturnDate, t.FineAmount, t.IssueStatus,
                       b.BookNumber, b.BookName, b.AccessionNumber,
                       m.MemberNumber, m.MemberName, m.PhoneHome,
                       act.AccTransNumber, act.TransactionDate, act.TransactionRemarks
                FROM TransBookIssue t
                JOIN MstrBookStock b ON t.BookNumber = b.BookNumber
                JOIN MstrMember m ON t.MemberNumber = m.MemberNumber
                LEFT JOIN AccTransaction act ON act.MemberNumber = m.MemberNumber AND act.TransTypeNumber = 14
                WHERE (t.FineAmount > 0 OR t.ReturnDate IS NOT NULL)
                ORDER BY t.ReturnDate DESC, t.SlNo DESC";
        $res = db_query($conn, $sql);
        if ($res) {
            while ($r = db_fetch_assoc($res)) { $report_rows[] = $r; }
        }
        break;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* Print Layout Styling */
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
    body {
        background-color: #fff !important;
        font-size: 11pt;
        color: #000 !important;
    }
    .no-print, .top-navbar, .sidebar-aside, .btn, .card-header-actions, .filter-box {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table-bordered th, .table-bordered td {
        border: 1px solid #222 !important;
        padding: 5px 7px !important;
    }
    .table thead th {
        background-color: #e9ecef !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-header {
        display: block !important;
        text-align: center;
        border-bottom: 2px double #333;
        padding-bottom: 10px;
        margin-bottom: 18px;
    }
    .print-signature-row {
        display: flex !important;
        justify-content: space-between;
        margin-top: 50px;
        page-break-inside: avoid;
    }
    .signature-box {
        width: 200px;
        text-align: center;
        border-top: 1px solid #333;
        padding-top: 5px;
        font-size: 10pt;
    }
}

.print-header {
    display: none;
}
.print-signature-row {
    display: none;
}
.report-nav-pill.active {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
</style>

<!-- Print Header visible only when printed -->
<div class="print-header">
    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($company['CompanyName'] ?? 'LIBERA CENTRAL LIBRARY'); ?></h3>
    <div class="small text-muted mb-1"><?php echo htmlspecialchars($company['CompanyAddress'] ?? 'Main Campus'); ?>, <?php echo htmlspecialchars($company['CompanyPlace'] ?? ''); ?> | Phone: <?php echo htmlspecialchars($company['CompanyPhone'] ?? ''); ?></div>
    <h5 class="fw-bold text-uppercase mt-2 mb-1" style="text-decoration: underline;"><?php echo htmlspecialchars($page_title); ?></h5>
    <div class="small text-muted">Generated On: <?php echo date('d-M-Y H:i:s'); ?> | Prepared By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Librarian'); ?></div>
</div>

<div class="no-print">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="breadcrumb-nav">HOME / REPORTS / LIBRARY MASTER REGISTERS</div>
            <h1 class="page-header-title mb-0"><i class="fa-solid fa-file-invoice text-primary me-2"></i>Official Library Registers & Reports</h1>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark fw-semibold shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print / Export PDF
            </button>
        </div>
    </div>

    <!-- Report Selector Navigation Pills -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm border">
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'issue_register') ? 'active' : ''; ?>" href="?type=issue_register">
                <i class="fa-solid fa-list-check me-1"></i> 1. Issue Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'stock_register') ? 'active' : ''; ?>" href="?type=stock_register">
                <i class="fa-solid fa-boxes-stacked me-1"></i> 2. Book Stock Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'category_books') ? 'active' : ''; ?>" href="?type=category_books">
                <i class="fa-solid fa-layer-group me-1"></i> 3. Category wise Book List
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'lost_damaged') ? 'active' : ''; ?>" href="?type=lost_damaged">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> 4. Lost / Damaged Book List
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'member_register') ? 'active' : ''; ?>" href="?type=member_register">
                <i class="fa-solid fa-id-card-clip me-1"></i> 5. Member Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'member_due') ? 'active' : ''; ?>" href="?type=member_due">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> 6. Member Due List
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link report-nav-pill <?php echo ($report_type == 'fine_list') ? 'active' : ''; ?>" href="?type=fine_list">
                <i class="fa-solid fa-coins me-1"></i> 7. Fine Register
            </a>
        </li>
    </ul>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm border-0 filter-box">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">

                <?php if ($report_type == 'issue_register'): ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">From Issue Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">To Issue Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                <?php endif; ?>

                <?php if (in_array($report_type, ['stock_register', 'category_books'])): ?>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">-- All Categories --</option>
                            <?php if ($categories): db_data_seek($categories, 0); while ($c = db_fetch_assoc($categories)): ?>
                                <option value="<?php echo $c['CategoryNumber']; ?>" <?php echo ($cat_id == $c['CategoryNumber']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['CategoryName']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($report_type == 'member_register'): ?>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Member Type / Class</label>
                        <select name="member_type" class="form-select form-select-sm">
                            <option value="">-- All Member Types --</option>
                            <?php if ($member_types): db_data_seek($member_types, 0); while ($mt = db_fetch_assoc($member_types)): ?>
                                <option value="<?php echo $mt['MemberTypeNumber']; ?>" <?php echo ($type_id == $mt['MemberTypeNumber']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mt['MemberTypeName']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (in_array($report_type, ['stock_register', 'member_register'])): ?>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Search Keywords</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Title, Accession, Barcode or Name" value="<?php echo htmlspecialchars($search_q); ?>">
                    </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                        <i class="fa-solid fa-filter me-1"></i> Apply Filter
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="?type=<?php echo htmlspecialchars($report_type); ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Content Body -->
<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <?php if ($report_type == 'issue_register'): ?>
            <!-- 1. ISSUE REGISTER -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Circulation Issue & Return Register</h5>
                <span class="badge bg-primary fs-6"><?php echo count($report_rows); ?> Records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Book Details</th>
                            <th>Acc #</th>
                            <th>Call #</th>
                            <th>Member Name & No</th>
                            <th>Member Type</th>
                            <th>Return Date</th>
                            <th class="text-end">Fine (₹)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-muted">No circulation records found for the selected period.</td></tr>
                        <?php else:
                            $sl = 1; $total_fines = 0;
                            foreach ($report_rows as $row):
                                $total_fines += floatval($row['FineAmount'] ?? 0);
                        ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $sl++; ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['IssueDate'])); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['DueDate'])); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['BookName']); ?></div>
                                    <div class="small text-muted">Item Code / Barcode: <?php echo htmlspecialchars($row['BookCode'] ?: '-'); ?></div>
                                </td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['CallNumber'] ?: '-'); ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['MemberName']); ?></div>
                                    <div class="small text-muted">#<?php echo $row['MemberNumber']; ?> | <?php echo htmlspecialchars($row['PhoneHome'] ?: '-'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['MemberTypeName'] ?: 'Standard'); ?></td>
                                <td><?php echo $row['ReturnDate'] ? date('d-M-Y', strtotime($row['ReturnDate'])) : '<span class="text-danger fw-bold">Active</span>'; ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['FineAmount'] ?? 0, 2); ?></td>
                                <td>
                                    <?php
                                    $st = $row['IssueStatus'] ?? $row['Status'] ?? 'Issued';
                                    if ($st == 'Returned'): ?>
                                        <span class="badge bg-success">Returned</span>
                                    <?php elseif ($st == 'Overdue' || (empty($row['ReturnDate']) && !empty($row['DueDate']) && strtotime($row['DueDate']) < time())): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($st); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="9" class="text-end">Total Fine Recorded:</td>
                                <td class="text-end">₹<?php echo number_format($total_fines, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type == 'stock_register'): ?>
            <!-- 2. BOOK STOCK REGISTER -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Accession Book Stock Register</h5>
                <span class="badge bg-primary fs-6"><?php echo count($report_rows); ?> Total Books</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No</th>
                            <th>Acc #</th>
                            <th>Call #</th>
                            <th>Barcode</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th class="text-end">Price (₹)</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-muted">No book inventory records found.</td></tr>
                        <?php else:
                            $sl = 1; $total_val = 0;
                            foreach ($report_rows as $row):
                                $total_val += floatval($row['BookPrice'] ?? 0);
                        ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $sl++; ?></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['CallNumber'] ?: '-'); ?></span></td>
                                <td><code><?php echo htmlspecialchars($row['BookCode'] ?: '-'); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['BookName']); ?></td>
                                <td><?php echo htmlspecialchars($row['AuthorName'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['PublisherName'] ?: '-'); ?></td>
                                <td class="small"><?php echo htmlspecialchars($row['ISBNumber'] ?: '-'); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['CategoryName'] ?: 'General'); ?></span></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['BookPrice'] ?? 0, 2); ?></td>
                                <td class="text-center">
                                    <?php if ($row['BookStatus'] == 1): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php elseif ($row['BookStatus'] == 2): ?>
                                        <span class="badge bg-warning text-dark">Lost</span>
                                    <?php elseif ($row['BookStatus'] == 3): ?>
                                        <span class="badge bg-danger">Damaged</span>
                                    <?php elseif ($row['BookStatus'] == 4): ?>
                                        <span class="badge bg-secondary">Missing</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">Write-off</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="9" class="text-end">Total Catalog Valuation:</td>
                                <td class="text-end">₹<?php echo number_format($total_val, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type == 'category_books'): ?>
            <!-- 3. CATEGORY WISE BOOK LIST -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Category-wise Classified Book Catalog</h5>
                <span class="badge bg-primary fs-6"><?php echo count($report_rows); ?> Categories</span>
            </div>

            <?php if (empty($report_rows)): ?>
                <div class="text-center py-4 text-muted">No books found for category grouping.</div>
            <?php else:
                foreach ($report_rows as $category_name => $books):
            ?>
                <div class="card mb-4 border">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center py-2">
                        <span class="fw-bold fs-6"><i class="fa-solid fa-folder me-2"></i><?php echo htmlspecialchars($category_name); ?></span>
                        <span class="badge bg-light text-dark"><?php echo count($books); ?> Titles</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>Acc #</th>
                                    <th>Call #</th>
                                    <th>Barcode</th>
                                    <th>Book Title</th>
                                    <th>Author</th>
                                    <th>Publisher</th>
                                    <th>ISBN</th>
                                    <th class="text-end">Price (₹)</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($books as $b): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i++; ?></td>
                                    <td><span class="badge bg-dark"><?php echo htmlspecialchars($b['AccessionNumber']); ?></span></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['CallNumber'] ?: '-'); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($b['BookCode'] ?: '-'); ?></code></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($b['BookName']); ?></td>
                                    <td><?php echo htmlspecialchars($b['AuthorName'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($b['PublisherName'] ?: '-'); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($b['ISBNumber'] ?: '-'); ?></td>
                                    <td class="text-end"><?php echo number_format($b['BookPrice'] ?? 0, 2); ?></td>
                                    <td class="text-center">
                                        <?php if ($b['BookStatus'] == 1): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif ($b['BookStatus'] == 2): ?>
                                            <span class="badge bg-warning text-dark">Lost</span>
                                        <?php elseif ($b['BookStatus'] == 3): ?>
                                            <span class="badge bg-danger">Damaged</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Other</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; endif; ?>

        <?php elseif ($report_type == 'lost_damaged'): ?>
            <!-- 4. LOST OR DAMAGED BOOK LIST -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Lost, Damaged & Missing Stock Audit List</h5>
                <span class="badge bg-danger fs-6"><?php echo count($report_rows); ?> Affected Titles</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No</th>
                            <th>Acc #</th>
                            <th>Call #</th>
                            <th>Barcode</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>Category</th>
                            <th class="text-center">Condition / Status</th>
                            <th class="text-end">Asset Value (₹)</th>
                            <th>Damage / Incident Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-success"><i class="fa-solid fa-circle-check me-2"></i>Good news! No books are currently recorded as lost, damaged, or missing.</td></tr>
                        <?php else:
                            $sl = 1; $total_loss = 0;
                            foreach ($report_rows as $row):
                                $total_loss += floatval($row['BookPrice'] ?? 0);
                        ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $sl++; ?></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['CallNumber'] ?: '-'); ?></span></td>
                                <td><code><?php echo htmlspecialchars($row['BookCode'] ?: '-'); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['BookName']); ?></td>
                                <td><?php echo htmlspecialchars($row['AuthorName'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['PublisherName'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['CategoryName'] ?: 'General'); ?></td>
                                <td class="text-center">
                                    <?php if ($row['BookStatus'] == 2): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-circle-question me-1"></i>Lost</span>
                                    <?php elseif ($row['BookStatus'] == 3): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-heart-crack me-1"></i>Damaged</span>
                                    <?php elseif ($row['BookStatus'] == 4): ?>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-magnifying-glass me-1"></i>Missing</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">Written-off</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-danger">₹<?php echo number_format($row['BookPrice'] ?? 0, 2); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['BookRemarks'] ?: 'None recorded'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="9" class="text-end text-danger">Total Estimated Asset Loss:</td>
                                <td class="text-end text-danger">₹<?php echo number_format($total_loss, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type == 'member_register'): ?>
            <!-- 5. MEMBER REGISTER (CATEGORYWISE & CLASSWISE) -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Official Member Directory & Profiles (Class & Category Wise)</h5>
                <span class="badge bg-primary fs-6"><?php echo count($report_rows); ?> Membership Groups</span>
            </div>

            <?php if (empty($report_rows)): ?>
                <div class="text-center py-4 text-muted">No member records found.</div>
            <?php else:
                foreach ($report_rows as $group_name => $mems):
            ?>
                <div class="card mb-4 border">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                        <span class="fw-bold fs-6"><i class="fa-solid fa-id-badge me-2"></i>Class / Category: <?php echo htmlspecialchars($group_name); ?></span>
                        <span class="badge bg-primary"><?php echo count($mems); ?> Members</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>Member ID</th>
                                    <th>Full Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Address / House</th>
                                    <th>DOB / Age</th>
                                    <th>Blood Group</th>
                                    <th>Aadhaar</th>
                                    <th>Join Date</th>
                                    <th>Expiry / Term</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($mems as $m): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i++; ?></td>
                                    <td><span class="badge bg-primary">#<?php echo $m['MemberNumber']; ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($m['MemberName']); ?></td>
                                    <td><?php echo htmlspecialchars($m['PhoneHome'] ?: '-'); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($m['MemberEmail'] ?: '-'); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($m['HouseName'] ?: '-'); ?></td>
                                    <td class="small"><?php echo $m['MemBirthDate'] ? date('d-M-Y', strtotime($m['MemBirthDate'])) : '-'; ?></td>
                                    <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?php echo htmlspecialchars($m['BloodGroupStr'] ?: '-'); ?></span></td>
                                    <td class="small"><code><?php echo htmlspecialchars($m['AadharNumber'] ?: '-'); ?></code></td>
                                    <td><?php echo $m['MemberJoinDate'] ? date('d-M-Y', strtotime($m['MemberJoinDate'])) : '-'; ?></td>
                                    <td class="small"><?php echo $m['TerminationDate'] ? date('d-M-Y', strtotime($m['TerminationDate'])) : '<span class="text-muted">Lifetime</span>'; ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($m['TerminationDate']) && strtotime($m['TerminationDate']) < time()): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; endif; ?>

        <?php elseif ($report_type == 'member_due'): ?>
            <!-- 6. MEMBER DUE LIST -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-clock me-2"></i>Member Book Due & Overdue Circulation List</h5>
                <span class="badge bg-danger fs-6"><?php echo count($report_rows); ?> Outstanding Books</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No</th>
                            <th>Member ID & Name</th>
                            <th>Contact Phone</th>
                            <th>Member Class</th>
                            <th>Book Title</th>
                            <th>Acc #</th>
                            <th>Call #</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th class="text-center">Overdue Days</th>
                            <th class="text-end">Accrued Fine (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-success"><i class="fa-solid fa-circle-check me-2"></i>Zero books are currently overdue. All checkouts are within due dates!</td></tr>
                        <?php else:
                            $sl = 1; $total_pending_fine = 0;
                            foreach ($report_rows as $row):
                                $is_overdue = ($row['OverdueDays'] > 0);
                                $total_pending_fine += floatval($row['CalculatedFine']);
                        ?>
                            <tr class="<?php echo $is_overdue ? 'table-warning' : ''; ?>">
                                <td class="text-center fw-bold"><?php echo $sl++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['MemberName']); ?></div>
                                    <div class="small text-muted">ID: #<?php echo $row['MemberNumber']; ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['PhoneHome'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['MemberTypeName'] ?: 'Standard'); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['BookName']); ?></div>
                                    <div class="small text-muted">Barcode: <?php echo htmlspecialchars($row['BookCode'] ?: '-'); ?></div>
                                </td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['CallNumber'] ?: '-'); ?></span></td>
                                <td><?php echo date('d-M-Y', strtotime($row['IssueDate'])); ?></td>
                                <td><span class="fw-bold <?php echo $is_overdue ? 'text-danger' : 'text-dark'; ?>"><?php echo date('d-M-Y', strtotime($row['DueDate'])); ?></span></td>
                                <td class="text-center">
                                    <?php if ($is_overdue): ?>
                                        <span class="badge bg-danger fs-6"><?php echo $row['OverdueDays']; ?> Days</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-danger">₹<?php echo number_format($row['CalculatedFine'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="10" class="text-end text-danger">Total Accrued Overdue Fines:</td>
                                <td class="text-end text-danger">₹<?php echo number_format($total_pending_fine, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type == 'fine_list'): ?>
            <!-- 7. FINE REGISTER -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-coins me-2 text-warning"></i>Circulation Fine Audit & Collection Register</h5>
                <span class="badge bg-warning text-dark fs-6"><?php echo count($report_rows); ?> Fine Events</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No</th>
                            <th>Return / Assessment Date</th>
                            <th>Member Details</th>
                            <th>Book Details</th>
                            <th>Acc #</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th class="text-end">Fine Amount (₹)</th>
                            <th>Collection / Voucher Ref</th>
                            <th class="text-center">Audit Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">No fines recorded in circulation history.</td></tr>
                        <?php else:
                            $sl = 1; $total_fines_accrued = 0;
                            foreach ($report_rows as $row):
                                $total_fines_accrued += floatval($row['FineAmount'] ?? 0);
                        ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $sl++; ?></td>
                                <td><?php echo $row['ReturnDate'] ? date('d-M-Y', strtotime($row['ReturnDate'])) : date('d-M-Y', strtotime($row['DueDate'])); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['MemberName']); ?></div>
                                    <div class="small text-muted">#<?php echo $row['MemberNumber']; ?> | <?php echo htmlspecialchars($row['PhoneHome'] ?: '-'); ?></div>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['BookName']); ?></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><?php echo date('d-M-Y', strtotime($row['IssueDate'])); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['DueDate'])); ?></td>
                                <td class="text-end fw-bold text-danger">₹<?php echo number_format($row['FineAmount'] ?? 0, 2); ?></td>
                                <td>
                                    <?php if (!empty($row['AccTransNumber'])): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-receipt me-1"></i>Voucher #<?php echo $row['AccTransNumber']; ?></span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['TransactionRemarks'] ?: 'Cash Receipt'); ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ledger Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (floatval($row['FineAmount'] ?? 0) > 0): ?>
                                        <span class="badge bg-warning text-dark">Fine Collected</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Waived / Nil</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="7" class="text-end">Total Fine Sum:</td>
                                <td class="text-end text-danger">₹<?php echo number_format($total_fines_accrued, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

        <!-- Print Signature & Verification Block -->
        <div class="print-signature-row">
            <div class="signature-box">
                <div>Prepared By</div>
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Librarian'); ?></div>
            </div>
            <div class="signature-box">
                <div>Verified By / Accountant</div>
                <div class="fw-bold">Sign & Date</div>
            </div>
            <div class="signature-box">
                <div>Library Secretary / Authority</div>
                <div class="fw-bold">Official Seal</div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
