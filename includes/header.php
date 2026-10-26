<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$company = get_company_profile($conn);
$page_title = isset($page_title) ? $page_title : 'Dashboard';
$current_page = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Libera System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 60px;
            --brand-color: #34495e;
            --primary-blue: #2c3e50;
            --bg-light: #f4f6f9;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        /* Top Bar Layout */
        .top-navbar {
            height: var(--topbar-height);
            background-color: var(--primary-blue);
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .top-navbar .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
        }

        /* App Wrapper */
        .app-wrapper {
            display: flex;
            margin-top: var(--topbar-height);
            min-height: calc(100vh - var(--topbar-height));
        }

        /* Sidebar Styling */
        .sidebar-aside {
            width: var(--sidebar-width);
            background-color: #ffffff;
            border-right: 1px solid #e2e8f0;
            flex-shrink: 0;
            position: fixed;
            top: var(--topbar-height);
            bottom: 0;
            left: 0;
            overflow-y: auto;
            z-index: 1020;
            padding: 1.25rem 0;
        }

        .sidebar-user-card {
            padding: 0 1.25rem 1.25rem 1.25rem;
            border-bottom: 1px solid #edf2f7;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
        }

        .sidebar-menu-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 1.25rem 0.5rem 1.25rem;
        }

        .sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 1.25rem;
            color: #475569;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            position: relative;
        }

        .sidebar-nav-link:hover, .sidebar-nav-link.active {
            color: #2563eb;
            background-color: #f1f5f9;
        }

        .sidebar-nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #2563eb;
        }

        .sidebar-nav-link i.menu-icon {
            width: 24px;
            font-size: 1.1rem;
            margin-right: 10px;
        }

        .sidebar-submenu {
            padding-left: 2.25rem;
            background-color: #f8fafc;
        }

        .sidebar-submenu .sidebar-nav-link {
            font-size: 0.9rem;
            padding: 0.45rem 1rem;
        }

        /* Main Content Container */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            padding: 1.75rem;
        }

        .breadcrumb-nav {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .page-header-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.25rem;
        }

        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .top-navbar { display: none !important; }
        }
    </style>
</head>
<body>

<?php if (is_logged_in()): ?>
<!-- Top Bar Navbar -->
<header class="top-navbar d-flex align-items-center justify-content-between px-3 no-print">
    <div class="d-flex align-items-center">
        <a class="navbar-brand text-decoration-none d-flex align-items-center" href="/dashboard.php">
            <i class="fa-solid fa-book-bookmark text-warning me-2 fs-4"></i>Libera System
        </a>
    </div>

    <!-- Right Profile Section -->
    <div class="dropdown">
        <button class="btn text-white dropdown-toggle d-flex align-items-center gap-2 border-0" type="button" data-bs-toggle="dropdown">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-weight: 600;">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
            </div>
            <span class="fw-semibold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="/dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
        </ul>
    </div>
</header>

<div class="app-wrapper">
    <!-- Sidebar (Aside Navigation) -->
    <aside class="sidebar-aside no-print">
        <!-- Sidebar User Card -->
        <div class="sidebar-user-card">
            <div class="user-avatar-circle">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">LIBRARIAN / ADMIN</div>
            </div>
        </div>

        <div class="sidebar-menu-label">MENU</div>

        <nav class="nav flex-column">
            <!-- Dashboard Link -->
            <a href="/dashboard.php" class="sidebar-nav-link <?php echo ($current_page == '/dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge menu-icon text-primary"></i> Dashboard
            </a>

            <!-- Circulation Accordion -->
            <a class="sidebar-nav-link d-flex justify-content-between align-items-center <?php echo (strpos($current_page, '/circulation/') !== false) ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#menuCirculation">
                <span><i class="fa-solid fa-arrows-rotate menu-icon text-info"></i> Circulation</span>
                <i class="fa-solid fa-chevron-down small"></i>
            </a>
            <div class="collapse <?php echo (strpos($current_page, '/circulation/') !== false) ? 'show' : ''; ?> sidebar-submenu" id="menuCirculation">
                <a href="/modules/circulation/issue.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/circulation/issue.php') ? 'active' : ''; ?>">Issue Book</a>
                <a href="/modules/circulation/return.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/circulation/return.php') ? 'active' : ''; ?>">Return & Fine Processing</a>
                <a href="/modules/circulation/issued_list.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/circulation/issued_list.php') ? 'active' : ''; ?>">Active Checkouts</a>
                <a href="/modules/circulation/reservation.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/circulation/reservation.php') ? 'active' : ''; ?>">Reservations & Holds</a>
            </div>

            <!-- Inventory Accordion -->
            <a class="sidebar-nav-link d-flex justify-content-between align-items-center <?php echo (strpos($current_page, '/inventory/') !== false) ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#menuInventory">
                <span><i class="fa-solid fa-book menu-icon text-success"></i> Catalog & Inventory</span>
                <i class="fa-solid fa-chevron-down small"></i>
            </a>
            <div class="collapse <?php echo (strpos($current_page, '/inventory/') !== false) ? 'show' : ''; ?> sidebar-submenu" id="menuInventory">
                <a href="/modules/inventory/books.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/books.php') ? 'active' : ''; ?>">Book Stock Master</a>
                <a href="/modules/inventory/scanner.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/scanner.php') ? 'active' : ''; ?>">Barcode / RFID Scanner</a>
                <a href="/modules/inventory/writeoff.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/writeoff.php') ? 'active' : ''; ?>">Damaged / Lost Write-off</a>
                <a href="/modules/inventory/audit.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/audit.php') ? 'active' : ''; ?>">Stock Audit Sessions</a>
                <a href="/modules/inventory/authors.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/authors.php') ? 'active' : ''; ?>">Authors Directory</a>
                <a href="/modules/inventory/publishers.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/publishers.php') ? 'active' : ''; ?>">Publishers Directory</a>
                <a href="/modules/inventory/categories.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/inventory/categories.php') ? 'active' : ''; ?>">Categories & DDC</a>
            </div>

            <!-- Members Accordion -->
            <a class="sidebar-nav-link d-flex justify-content-between align-items-center <?php echo (strpos($current_page, '/members/') !== false) ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#menuMembers">
                <span><i class="fa-solid fa-users menu-icon text-warning"></i> Member Management</span>
                <i class="fa-solid fa-chevron-down small"></i>
            </a>
            <div class="collapse <?php echo (strpos($current_page, '/members/') !== false) ? 'show' : ''; ?> sidebar-submenu" id="menuMembers">
                <a href="/modules/members/members.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/members/members.php') ? 'active' : ''; ?>">Member Profiles</a>
                <a href="/modules/members/renew.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/members/renew.php') ? 'active' : ''; ?>">Validity & Renewals</a>
                <a href="/modules/members/member_types.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/members/member_types.php') ? 'active' : ''; ?>">Classes & Rules</a>
            </div>

            <!-- Double-Entry Accounts Accordion -->
            <a class="sidebar-nav-link d-flex justify-content-between align-items-center <?php echo (strpos($current_page, '/accounts/') !== false) ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#menuAccounts">
                <span><i class="fa-solid fa-calculator menu-icon text-danger"></i> Double-Entry Accounts</span>
                <i class="fa-solid fa-chevron-down small"></i>
            </a>
            <div class="collapse <?php echo (strpos($current_page, '/accounts/') !== false) ? 'show' : ''; ?> sidebar-submenu" id="menuAccounts">
                <a href="/modules/accounts/vouchers.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/vouchers.php') ? 'active' : ''; ?>">Post Voucher</a>
                <a href="/modules/accounts/ledgers.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/ledgers.php') ? 'active' : ''; ?>">Chart of Accounts</a>
                <a href="/modules/accounts/distributors.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/distributors.php') ? 'active' : ''; ?>">Distributors Vendors</a>
                <a href="/modules/accounts/fiscal_closing.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/fiscal_closing.php') ? 'active' : ''; ?>">Fiscal Period Closing</a>
                <a href="/modules/accounts/audit_log.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/audit_log.php') ? 'active' : ''; ?>">Audit Trail Logs</a>
                <a href="/modules/accounts/reports.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/accounts/reports.php') ? 'active' : ''; ?>">Financial Reports</a>
            </div>

            <!-- Analytics Link -->
            <a href="/modules/reports/analytics.php" class="sidebar-nav-link <?php echo ($current_page == '/modules/reports/analytics.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line menu-icon text-secondary"></i> Circulation Analytics
            </a>
        </nav>
    </aside>

    <!-- Main Content Container -->
    <main class="main-content">
<?php else: ?>
    <div class="container my-5">
<?php endif; ?>
