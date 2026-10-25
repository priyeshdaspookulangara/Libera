<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$company = get_company_profile($conn);
$page_title = isset($page_title) ? $page_title : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Libera</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .stat-card {
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background-color: #fff; }
        }
    </style>
</head>
<body>
<?php if (is_logged_in()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand text-warning" href="/dashboard.php">
      <i class="fa-solid fa-book-bookmark me-2"></i>Libera System
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php echo ($page_title == 'Dashboard') ? 'active' : ''; ?>" href="/dashboard.php">
            <i class="fa-solid fa-chart-line me-1"></i> Dashboard
          </a>
        </li>
        <!-- Circulation Menu -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?php echo (strpos($page_title, 'Circulation') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-arrows-rotate me-1"></i> Circulation
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-menu-item dropdown-item" href="/modules/circulation/issue.php"><i class="fa-solid fa-hand-holding-hand me-2"></i>Issue Book</a></li>
            <li><a class="dropdown-menu-item dropdown-item" href="/modules/circulation/return.php"><i class="fa-solid fa-rotate-left me-2"></i>Return Book & Fine Processing</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-menu-item dropdown-item" href="/modules/circulation/issued_list.php"><i class="fa-solid fa-list-check me-2"></i>Active Checkouts & Overdues</a></li>
          </ul>
        </li>
        <!-- Catalog & Inventory -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?php echo (strpos($page_title, 'Inventory') !== false || strpos($page_title, 'Catalog') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-book me-1"></i> Catalog & Inventory
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/modules/inventory/books.php"><i class="fa-solid fa-book-open me-2"></i>Book Stock Master</a></li>
            <li><a class="dropdown-item" href="/modules/inventory/authors.php"><i class="fa-solid fa-user-pen me-2"></i>Authors Master</a></li>
            <li><a class="dropdown-item" href="/modules/inventory/publishers.php"><i class="fa-solid fa-building me-2"></i>Publishers Master</a></li>
            <li><a class="dropdown-item" href="/modules/inventory/categories.php"><i class="fa-solid fa-tags me-2"></i>Categories & DDC</a></li>
          </ul>
        </li>
        <!-- Member Management -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?php echo (strpos($page_title, 'Member') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-users me-1"></i> Members
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/modules/members/members.php"><i class="fa-solid fa-user-gear me-2"></i>Member Profiles</a></li>
            <li><a class="dropdown-item" href="/modules/members/member_types.php"><i class="fa-solid fa-layer-group me-2"></i>Membership Classes & Rules</a></li>
          </ul>
        </li>
        <!-- Double-Entry Accounting -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?php echo (strpos($page_title, 'Accounts') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-calculator me-1"></i> Double-Entry Accounts
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/modules/accounts/vouchers.php"><i class="fa-solid fa-receipt me-2"></i>Post Journal Voucher</a></li>
            <li><a class="dropdown-item" href="/modules/accounts/ledgers.php"><i class="fa-solid fa-sitemap me-2"></i>Chart of Accounts (Ledgers)</a></li>
            <li><a class="dropdown-item" href="/modules/accounts/distributors.php"><i class="fa-solid fa-truck-field me-2"></i>Distributors Master</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/modules/accounts/reports.php"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Financial Reports (Trial Balance, P&L, Balance Sheet)</a></li>
          </ul>
        </li>
      </ul>
      <div class="d-flex align-items-center text-light">
        <span class="me-3 small"><i class="fa-solid fa-user me-1 text-info"></i><?php echo htmlspecialchars($_SESSION['username'] ?? 'Staff'); ?></span>
        <a href="/logout.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="container-fluid my-4">
