<?php
// modules/reports/analytics.php - Advanced Circulation & Inventory Analytics Dashboard
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Circulation & Inventory Analytics";

// 1. Monthly Checkout Trends (Last 6 Months)
$monthly_issues = db_query($conn, "SELECT DATE_FORMAT(IssueDate, '%Y-%m') as MonthLabel, COUNT(*) as TotalIssues FROM TransBookIssue GROUP BY MonthLabel ORDER BY MonthLabel DESC LIMIT 6");
$months = [];
$issue_counts = [];
if ($monthly_issues && db_num_rows($monthly_issues) > 0) {
    while ($row = db_fetch_assoc($monthly_issues)) {
        $months[] = $row['MonthLabel'];
        $issue_counts[] = intval($row['TotalIssues']);
    }
    $months = array_reverse($months);
    $issue_counts = array_reverse($issue_counts);
}

// 2. Top Borrowed Categories/Genres
$top_categories = db_query($conn, "SELECT c.CategoryName, COUNT(t.SlNo) as IssueCount
    FROM TransBookIssue t
    JOIN MstrBookStock b ON t.BookNumber = b.BookNumber
    LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
    GROUP BY c.CategoryNumber, c.CategoryName
    ORDER BY IssueCount DESC LIMIT 5");

$cat_labels = [];
$cat_data = [];
if ($top_categories && db_num_rows($top_categories) > 0) {
    while ($r = db_fetch_assoc($top_categories)) {
        $cat_labels[] = $r['CategoryName'] ?: 'Uncategorized';
        $cat_data[] = intval($r['IssueCount']);
    }
}

// 3. Stock Health & Utilization
$total_stock = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock WHERE BookStatus = 1");
$total_books = db_fetch_assoc($total_stock)['cnt'] ?? 0;

$issued_stock = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE ReturnDate IS NULL");
$active_issued = db_fetch_assoc($issued_stock)['cnt'] ?? 0;

$writeoff_stock = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock WHERE BookStatus = 0");
$inactive_books = db_fetch_assoc($writeoff_stock)['cnt'] ?? 0;

$utilization_rate = $total_books > 0 ? round(($active_issued / $total_books) * 100, 1) : 0;

// 4. Overdue Rate Analysis
$overdue_stock = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE ReturnDate IS NULL AND DueDate < CURDATE()");
$overdue_count = db_fetch_assoc($overdue_stock)['cnt'] ?? 0;
$overdue_rate = $active_issued > 0 ? round(($overdue_count / $active_issued) * 100, 1) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / REPORTS / ANALYTICS</div>
<h1 class="page-header-title"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Circulation Trends & Inventory Analytics</h1>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-primary border-4 p-3">
            <div class="text-uppercase text-muted fw-bold small">Total Catalog Size</div>
            <div class="fs-2 fw-bold text-dark mt-1"><?php echo number_format($total_books); ?> <span class="fs-6 text-muted font-normal">titles</span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-warning border-4 p-3">
            <div class="text-uppercase text-muted fw-bold small">Active Checkouts</div>
            <div class="fs-2 fw-bold text-warning mt-1"><?php echo number_format($active_issued); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-info border-4 p-3">
            <div class="text-uppercase text-muted fw-bold small">Stock Utilization Rate</div>
            <div class="fs-2 fw-bold text-info mt-1"><?php echo $utilization_rate; ?>%</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-danger border-4 p-3">
            <div class="text-uppercase text-muted fw-bold small">Overdue Rate</div>
            <div class="fs-2 fw-bold text-danger mt-1"><?php echo $overdue_rate; ?>%</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-chart-line me-2"></i>Monthly Checkout Velocity</h5>
                <span class="badge bg-secondary">Last 6 Months</span>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Top Borrowed Categories</h5>
                <span class="badge bg-secondary">Distribution</span>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Monthly Line Chart
    const ctx1 = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Books Issued',
                data: <?php echo json_encode($issue_counts); ?>,
                borderColor: '#182b49',
                backgroundColor: 'rgba(24, 43, 73, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Category Doughnut Chart
    const ctx2 = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_data); ?>,
                backgroundColor: ['#182b49', '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
