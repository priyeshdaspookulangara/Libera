<?php
// modules/inventory/books.php - Library Catalog & Inventory Management with Top Tabs
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../manglish_helper.php';
require_login();

$page_title = "Library Catalog & Stock";
$msg = '';
$error = '';

// Active Tab determination: all, class_wise, issue_register, category_wise, missing, damage, lost
$tab = sanitize_input($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'class_wise', 'issue_register', 'category_wise', 'missing', 'damage', 'lost'])) {
    $tab = 'all';
}

// Handle CRUD operations & Status Changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        $book_name = sanitize_input($_POST['BookName'] ?? '');
        $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
        $book_code = sanitize_input($_POST['BookCode'] ?? ''); // Barcode / Item Code
        $isbn = sanitize_input($_POST['ISBNumber'] ?? '');
        $call_num = sanitize_input($_POST['CallNumber'] ?? '');
        $author_id = !empty($_POST['AuthorNumber']) ? intval($_POST['AuthorNumber']) : null;
        $class_id = !empty($_POST['ClassNumber']) ? intval($_POST['ClassNumber']) : null;
        $pub_id = !empty($_POST['PublisherNumber']) ? intval($_POST['PublisherNumber']) : null;
        $cat_id = !empty($_POST['CategoryNumber']) ? intval($_POST['CategoryNumber']) : null;
        $lang_id = !empty($_POST['LanguageNumber']) ? intval($_POST['LanguageNumber']) : null;
        $price = !empty($_POST['BookPrice']) ? floatval($_POST['BookPrice']) : 0.0;
        $remarks = sanitize_input($_POST['BookRemarks'] ?? '');
        $status = intval($_POST['BookStatus'] ?? 1);

        if (empty($book_name) || empty($accession)) {
            $error = "Book Title and Accession Number are required fields.";
        } else {
            if (empty($book_code)) {
                $book_code = 'B' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }
            // Look up author name if author_id is specified to generate author_search_key
            $author_name = '';
            if ($author_id) {
                $ares = db_query($conn, "SELECT AuthorName FROM MstrAuthor WHERE AuthorNumber = ?", "i", [$author_id]);
                if ($ares && ($arow = db_fetch_assoc($ares))) {
                    $author_name = $arow['AuthorName'] ?? '';
                }
            }
            $title_key = generate_manglish_key($book_name);
            $author_key = generate_manglish_key($author_name);

            if ($action === 'add') {
                $check = db_query($conn, "SELECT BookNumber FROM MstrBookStock WHERE AccessionNumber = ?", "s", [$accession]);
                if ($check && db_num_rows($check) > 0) {
                    $error = "Accession Number '$accession' already exists!";
                } else {
                    $sql = "INSERT INTO MstrBookStock (BookName, title_search_key, author_search_key, BookStatus, BookCode, BookRemarks, CallNumber, ISBNumber, AccessionNumber, AuthorNumber, ClassNumber, PublisherNumber, BookPrice, LanguageNumber, CategoryNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $res = db_query($conn, $sql, "sssissssiiiidii", [$book_name, $title_key, $author_key, $status, $book_code, $remarks, $call_num, $isbn, $accession, $author_id, $class_id, $pub_id, $price, $lang_id, $cat_id]);
                    if ($res) {
                        $msg = "Book added successfully to catalog stock.";
                    } else {
                        $error = "Error adding book stock.";
                    }
                }
            } elseif ($action === 'edit') {
                $book_id = intval($_POST['BookNumber']);
                $sql = "UPDATE MstrBookStock SET BookName = ?, title_search_key = ?, author_search_key = ?, BookStatus = ?, BookCode = ?, BookRemarks = ?, CallNumber = ?, ISBNumber = ?, AccessionNumber = ?, AuthorNumber = ?, ClassNumber = ?, PublisherNumber = ?, BookPrice = ?, LanguageNumber = ?, CategoryNumber = ? WHERE BookNumber = ?";
                $res = db_query($conn, $sql, "sssisssssiiiidii", [$book_name, $title_key, $author_key, $status, $book_code, $remarks, $call_num, $isbn, $accession, $author_id, $class_id, $pub_id, $price, $lang_id, $cat_id, $book_id]);
                if ($res !== false) {
                    $msg = "Book details updated successfully.";
                } else {
                    $error = "Failed to update book details.";
                }
            }
        }
    } elseif ($action === 'status_change') {
        $book_id = intval($_POST['BookNumber']);
        $new_status = intval($_POST['NewStatus']);
        $new_remarks = sanitize_input($_POST['StatusRemarks'] ?? '');

        $sql = "UPDATE MstrBookStock SET BookStatus = ?, BookRemarks = CASE WHEN ? != '' THEN ? ELSE BookRemarks END WHERE BookNumber = ?";
        $res = db_query($conn, $sql, "issi", [$new_status, $new_remarks, $new_remarks, $book_id]);
        if ($res !== false) {
            $msg = "Book status successfully updated!";
        } else {
            $error = "Failed to update status.";
        }
    } elseif ($action === 'delete') {
        $book_id = intval($_POST['BookNumber']);
        $res = db_query($conn, "DELETE FROM MstrBookStock WHERE BookNumber = ?", "i", [$book_id]);
        if ($res) {
            $msg = "Book removed from inventory.";
        } else {
            $error = "Cannot delete book if it has circulation records.";
        }
    }
}

// Calculate badge counts for the top navigation tabs
$cnt_all_res = db_query($conn, "SELECT COUNT(*) as c FROM MstrBookStock");
$cnt_all = db_fetch_assoc($cnt_all_res)['c'] ?? 0;

$cnt_issue_res = db_query($conn, "SELECT COUNT(*) as c FROM TransBookIssue WHERE IssueStatus = 1 OR ReturnDate IS NULL");
$cnt_issue = db_fetch_assoc($cnt_issue_res)['c'] ?? 0;

$cnt_missing_res = db_query($conn, "SELECT COUNT(*) as c FROM MstrBookStock WHERE BookStatus = 3");
$cnt_missing = db_fetch_assoc($cnt_missing_res)['c'] ?? 0;

$cnt_damage_res = db_query($conn, "SELECT COUNT(*) as c FROM MstrBookStock WHERE BookStatus = 4");
$cnt_damage = db_fetch_assoc($cnt_damage_res)['c'] ?? 0;

$cnt_lost_res = db_query($conn, "SELECT COUNT(*) as c FROM MstrBookStock WHERE BookStatus = 5 OR (BookStatus = 0 AND (BookRemarks LIKE '%lost%' OR BookRemarks LIKE '%write%'))");
$cnt_lost = db_fetch_assoc($cnt_lost_res)['c'] ?? 0;

// Search query
$search = sanitize_input($_GET['search'] ?? '');
$filter_class = !empty($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$filter_cat = !empty($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;

// Fetch Master Directories for dropdowns & grouping
$authors = db_query($conn, "SELECT AuthorNumber, AuthorName FROM MstrAuthor ORDER BY AuthorName ASC");
$publishers = db_query($conn, "SELECT PublisherNumber, PublisherName FROM MstrPublisher ORDER BY PublisherName ASC");
$categories = db_query($conn, "SELECT CategoryNumber, CategoryName FROM MstrCategory ORDER BY CategoryName ASC");
$languages = db_query($conn, "SELECT LanguageNumber, LanguageName FROM MstrLanguage ORDER BY LanguageName ASC");
$classifications = db_query($conn, "SELECT ClassNumber, ClassName, ClassCode FROM MstrClassification ORDER BY ClassCode ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>Library Master & Circulation Register</h2>
        <div class="text-muted small">Manage book stocks, class/category distributions, circulation records, and condition tracking</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
            <i class="fa-solid fa-plus me-1"></i>Add New Book
        </button>
        <a href="/modules/circulation/issue.php" class="btn btn-outline-success">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Issue Book
        </a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Top Navigation Tabs -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-2 bg-light rounded">
        <ul class="nav nav-pills nav-fill gap-1" id="libraryTopNav">
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'all') ? 'active bg-primary fw-bold' : 'text-dark'; ?>" href="books.php?tab=all">
                    <i class="fa-solid fa-books me-1"></i>All Books
                    <span class="badge <?php echo ($tab === 'all') ? 'bg-white text-primary' : 'bg-secondary'; ?> ms-1"><?php echo $cnt_all; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'class_wise') ? 'active bg-primary fw-bold' : 'text-dark'; ?>" href="books.php?tab=class_wise">
                    <i class="fa-solid fa-layer-group me-1"></i>Class wise
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'issue_register') ? 'active bg-primary fw-bold' : 'text-dark'; ?>" href="books.php?tab=issue_register">
                    <i class="fa-solid fa-clipboard-list me-1"></i>Issue Register
                    <span class="badge <?php echo ($tab === 'issue_register') ? 'bg-white text-primary' : 'bg-warning text-dark'; ?> ms-1"><?php echo $cnt_issue; ?> Active</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'category_wise') ? 'active bg-primary fw-bold' : 'text-dark'; ?>" href="books.php?tab=category_wise">
                    <i class="fa-solid fa-tags me-1"></i>Category wise
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'missing') ? 'active bg-warning text-dark fw-bold' : 'text-dark'; ?>" href="books.php?tab=missing">
                    <i class="fa-solid fa-eye-slash me-1"></i>Missing
                    <span class="badge <?php echo ($tab === 'missing') ? 'bg-dark text-white' : 'bg-warning text-dark'; ?> ms-1"><?php echo $cnt_missing; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'damage') ? 'active bg-danger text-white fw-bold' : 'text-dark'; ?>" href="books.php?tab=damage">
                    <i class="fa-solid fa-heart-crack me-1"></i>Damage
                    <span class="badge <?php echo ($tab === 'damage') ? 'bg-white text-danger' : 'bg-danger text-white'; ?> ms-1"><?php echo $cnt_damage; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 <?php echo ($tab === 'lost') ? 'active bg-dark text-white fw-bold' : 'text-dark'; ?>" href="books.php?tab=lost">
                    <i class="fa-solid fa-ban me-1"></i>Lost
                    <span class="badge <?php echo ($tab === 'lost') ? 'bg-white text-dark' : 'bg-dark text-white'; ?> ms-1"><?php echo $cnt_lost; ?></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if ($tab === 'all' || $tab === 'missing' || $tab === 'damage' || $tab === 'lost'): ?>
    <?php
    // Prepare queries for standard book lists
    $where = [];
    $types = "";
    $params = [];

    if ($tab === 'missing') {
        $where[] = "b.BookStatus = 3";
    } elseif ($tab === 'damage') {
        $where[] = "b.BookStatus = 4";
    } elseif ($tab === 'lost') {
        $where[] = "(b.BookStatus = 5 OR (b.BookStatus = 0 AND (b.BookRemarks LIKE '%lost%' OR b.BookRemarks LIKE '%write%')))";
    }

    if (!empty($search)) {
        $search_variations = get_manglish_search_variations($search);
        $search_clauses = [
            "b.BookName LIKE ?",
            "b.AccessionNumber LIKE ?",
            "b.ISBNumber LIKE ?",
            "b.BookCode LIKE ?",
            "b.CallNumber LIKE ?",
            "a.AuthorName LIKE ?",
            "p.PublisherName LIKE ?",
            "c.CategoryName LIKE ?",
            "b.BookRemarks LIKE ?"
        ];
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= "sssssssss";

        // Also check phonetic search keys for all variations (e.g. mish, mis, etc.)
        foreach ($search_variations as $var) {
            $search_clauses[] = "b.title_search_key LIKE ?";
            $search_clauses[] = "b.author_search_key LIKE ?";
            $var_p = "%" . $var . "%";
            $params[] = $var_p;
            $params[] = $var_p;
            $types .= "ss";
        }

        $where[] = "(" . implode(" OR ", $search_clauses) . ")";
    }

    $where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

    // Count Total Books Matching Filter
    $count_sql = "SELECT COUNT(*) as total FROM MstrBookStock b
                  LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                  LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                  LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                  $where_sql";
    $count_res = db_query($conn, $count_sql, $types, $params);
    $total_books_count = ($count_res && $crow = db_fetch_assoc($count_res)) ? intval($crow['total']) : 0;

    // Pagination Parameters
    $per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    if ($per_page <= 0) $per_page = 100;
    if ($per_page > 500) $per_page = 500; // safe upper bound per page

    $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($current_page < 1) $current_page = 1;
    $total_pages = ceil($total_books_count / $per_page);
    if ($total_pages < 1) $total_pages = 1;
    if ($current_page > $total_pages) $current_page = $total_pages;

    $offset = ($current_page - 1) * $per_page;

    $sql_books = "SELECT b.*, a.AuthorName, COALESsCE_PUB.PublisherName, c.CategoryName, lang.LanguageName, cl.ClassName, cl.ClassCode
                  FROM MstrBookStock b
                  LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                  LEFT JOIN (
                      SELECT PublisherNumber, COALESCE(pub.publisher_name, p.PublisherName) as PublisherName
                      FROM MstrPublisher p
                      LEFT JOIN Publishers pub ON p.PublisherNumber = pub.publisher_id
                  ) COALESsCE_PUB ON b.PublisherNumber = COALESsCE_PUB.PublisherNumber
                  LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                  LEFT JOIN MstrLanguage lang ON b.LanguageNumber = lang.LanguageNumber
                  LEFT JOIN MstrClassification cl ON b.ClassNumber = cl.ClassNumber
                  $where_sql
                  ORDER BY b.BookNumber DESC LIMIT $per_page OFFSET $offset";

    $books_result = db_query($conn, $sql_books, $types, $params);
    ?>

    <!-- Search & Filter Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="books.php" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Book Title, Accession #, ISBN, Author, or Remarks..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text small">Per Page</span>
                        <select name="limit" class="form-select" onchange="this.form.submit()">
                            <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50 records</option>
                            <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100 records</option>
                            <option value="250" <?php echo ($per_page == 250) ? 'selected' : ''; ?>>250 records</option>
                            <option value="500" <?php echo ($per_page == 500) ? 'selected' : ''; ?>>500 records</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Master Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <?php
                if ($tab === 'missing') echo '<i class="fa-solid fa-eye-slash text-warning me-2"></i>Missing Books Registry';
                elseif ($tab === 'damage') echo '<i class="fa-solid fa-heart-crack text-danger me-2"></i>Damaged Books Registry';
                elseif ($tab === 'lost') echo '<i class="fa-solid fa-ban text-dark me-2"></i>Lost Books Registry';
                else echo '<i class="fa-solid fa-list text-primary me-2"></i>Complete Book Catalog & Stock';
                ?>
            </h5>
            <div>
                <span class="badge bg-primary text-white me-2">Total: <?php echo number_format($total_books_count); ?> books</span>
                <span class="badge bg-light text-dark border">Showing <?php echo min($total_books_count, $offset + 1); ?> - <?php echo min($total_books_count, $offset + $per_page); ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">Sl No.</th>
                            <th>Call Number</th>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>Barcode</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Acc No.</th>
                            <th>Status</th>
                            <th>Remarks / Condition</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($books_result && db_num_rows($books_result) > 0): ?>
                            <?php
                            $sl_no = $offset + 1;
                            while ($b = db_fetch_assoc($books_result)):
                            ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?php echo $sl_no++; ?></td>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars(!empty($b['CallNumber']) ? $b['CallNumber'] : 'N/A'); ?></span></td>
                                    <td>
                                        <strong class="text-primary"><?php echo htmlspecialchars($b['BookName']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($b['AuthorName'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <span class="text-secondary"><?php echo htmlspecialchars($b['PublisherName'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <code class="text-dark bg-light px-2 py-1 rounded border"><i class="fa-solid fa-barcode me-1 text-muted"></i><?php echo htmlspecialchars(!empty($b['BookCode']) ? $b['BookCode'] : 'N/A'); ?></code>
                                    </td>
                                    <td>
                                        <span class="font-monospace small"><?php echo htmlspecialchars(!empty($b['ISBNumber']) ? $b['ISBNumber'] : 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($b['CategoryName'] ?? 'General'); ?></span>
                                    </td>
                                    <td><span class="badge bg-dark font-monospace"><?php echo htmlspecialchars($b['AccessionNumber'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <?php if ($b['BookStatus'] == 1): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif ($b['BookStatus'] == 2): ?>
                                            <span class="badge bg-warning text-dark">Issued</span>
                                        <?php elseif ($b['BookStatus'] == 3): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-eye-slash me-1"></i>Missing</span>
                                        <?php elseif ($b['BookStatus'] == 4): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-heart-crack me-1"></i>Damage</span>
                                        <?php elseif ($b['BookStatus'] == 5): ?>
                                            <span class="badge bg-dark"><i class="fa-solid fa-ban me-1"></i>Lost</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($b['BookRemarks'] ?? 'None'); ?></small>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <!-- Quick Status Change Dropdown -->
                                        <div class="btn-group btn-group-sm me-1">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                Status
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li><h6 class="dropdown-header">Mark Condition</h6></li>
                                                <li>
                                                    <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                                                        <input type="hidden" name="action" value="status_change">
                                                        <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                                        <input type="hidden" name="NewStatus" value="1">
                                                        <button type="submit" class="dropdown-item text-success"><i class="fa-solid fa-check me-2"></i>Available</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                                                        <input type="hidden" name="action" value="status_change">
                                                        <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                                        <input type="hidden" name="NewStatus" value="3">
                                                        <button type="submit" class="dropdown-item text-warning"><i class="fa-solid fa-eye-slash me-2"></i>Missing</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                                                        <input type="hidden" name="action" value="status_change">
                                                        <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                                        <input type="hidden" name="NewStatus" value="4">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-heart-crack me-2"></i>Damaged</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                                                        <input type="hidden" name="action" value="status_change">
                                                        <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                                        <input type="hidden" name="NewStatus" value="5">
                                                        <button type="submit" class="dropdown-item text-dark"><i class="fa-solid fa-ban me-2"></i>Lost</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>

                                        <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#editBookModal<?php echo $b['BookNumber']; ?>">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <form method="POST" action="books.php?tab=<?php echo $tab; ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this book?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editBookModal<?php echo $b['BookNumber']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                                <div class="modal-header bg-info text-white">
                                                    <h5 class="modal-title"><i class="fa-solid fa-pen me-2"></i>Edit Book Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body row g-3">
                                                    <div class="col-md-8">
                                                        <label class="form-label fw-bold small">Book Title / Name *</label>
                                                        <input type="text" name="BookName" class="form-control" required value="<?php echo htmlspecialchars($b['BookName']); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Accession Number *</label>
                                                        <input type="text" name="AccessionNumber" class="form-control" required value="<?php echo htmlspecialchars($b['AccessionNumber']); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label fw-bold small">Barcode / Item Code</label>
                                                        <input type="text" name="BookCode" class="form-control" value="<?php echo htmlspecialchars($b['BookCode'] ?? ''); ?>" placeholder="e.g. B00123">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label fw-bold small">ISBN</label>
                                                        <input type="text" name="ISBNumber" class="form-control" value="<?php echo htmlspecialchars($b['ISBNumber']); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label fw-bold small">Call Number</label>
                                                        <input type="text" name="CallNumber" class="form-control" value="<?php echo htmlspecialchars($b['CallNumber']); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Price (₹)</label>
                                                        <input type="number" step="0.01" name="BookPrice" class="form-control" value="<?php echo $b['BookPrice']; ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold small">Author</label>
                                                        <select name="AuthorNumber" class="form-select">
                                                            <option value="">-- Select Author --</option>
                                                            <?php db_data_seek($authors, 0); ?>
                                                            <?php while ($auth = db_fetch_assoc($authors)): ?>
                                                                <option value="<?php echo $auth['AuthorNumber']; ?>" <?php echo ($b['AuthorNumber'] == $auth['AuthorNumber']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($auth['AuthorName']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold small">Publisher</label>
                                                        <select name="PublisherNumber" class="form-select">
                                                            <option value="">-- Select Publisher --</option>
                                                            <?php db_data_seek($publishers, 0); ?>
                                                            <?php while ($pub = db_fetch_assoc($publishers)): ?>
                                                                <option value="<?php echo $pub['PublisherNumber']; ?>" <?php echo ($b['PublisherNumber'] == $pub['PublisherNumber']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pub['PublisherName']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Classification (DDC)</label>
                                                        <select name="ClassNumber" class="form-select">
                                                            <option value="">-- Select Class --</option>
                                                            <?php db_data_seek($classifications, 0); ?>
                                                            <?php while ($cls = db_fetch_assoc($classifications)): ?>
                                                                <option value="<?php echo $cls['ClassNumber']; ?>" <?php echo ($b['ClassNumber'] == $cls['ClassNumber']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['ClassCode'] . ' - ' . $cls['ClassName']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Category</label>
                                                        <select name="CategoryNumber" class="form-select">
                                                            <option value="">-- Select Category --</option>
                                                            <?php db_data_seek($categories, 0); ?>
                                                            <?php while ($cat = db_fetch_assoc($categories)): ?>
                                                                <option value="<?php echo $cat['CategoryNumber']; ?>" <?php echo ($b['CategoryNumber'] == $cat['CategoryNumber']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['CategoryName']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Condition / Status</label>
                                                        <select name="BookStatus" class="form-select">
                                                            <option value="1" <?php echo ($b['BookStatus'] == 1) ? 'selected' : ''; ?>>Available</option>
                                                            <option value="2" <?php echo ($b['BookStatus'] == 2) ? 'selected' : ''; ?>>Issued</option>
                                                            <option value="3" <?php echo ($b['BookStatus'] == 3) ? 'selected' : ''; ?>>Missing</option>
                                                            <option value="4" <?php echo ($b['BookStatus'] == 4) ? 'selected' : ''; ?>>Damaged</option>
                                                            <option value="5" <?php echo ($b['BookStatus'] == 5) ? 'selected' : ''; ?>>Lost</option>
                                                            <option value="0" <?php echo ($b['BookStatus'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label fw-bold small">Remarks / Shelf Location / Notes</label>
                                                        <input type="text" name="BookRemarks" class="form-control" value="<?php echo htmlspecialchars($b['BookRemarks'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-info text-white">Update Book</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-folder-open fs-3 text-secondary mb-2 d-block"></i>
                                    No records found matching criteria in this view.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Showing <strong><?php echo min($total_books_count, $offset + 1); ?></strong> to <strong><?php echo min($total_books_count, $offset + $per_page); ?></strong> of <strong><?php echo number_format($total_books_count); ?></strong> entries
            </div>
            <nav aria-label="Books navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
                    $limit_param = '&limit=' . $per_page;
                    $tab_param = '&tab=' . urlencode($tab);
                    $base_url = "books.php?$tab_param$search_param$limit_param&page=";

                    // First & Prev
                    if ($current_page > 1):
                    ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_url . '1'; ?>">&laquo; First</a></li>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_url . ($current_page - 1); ?>">Previous</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">&laquo; First</span></li>
                        <li class="page-item disabled"><span class="page-link">Previous</span></li>
                    <?php endif; ?>

                    <?php
                    // Window of 5 pages around current
                    $start_p = max(1, $current_page - 2);
                    $end_p = min($total_pages, $current_page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++):
                    ?>
                        <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url . $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php
                    // Next & Last
                    if ($current_page < $total_pages):
                    ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_url . ($current_page + 1); ?>">Next</a></li>
                        <li class="page-item"><a class="page-link" href="<?php echo $base_url . $total_pages; ?>">Last &raquo;</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">Next</span></li>
                        <li class="page-item disabled"><span class="page-link">Last &raquo;</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'class_wise'): ?>
    <?php
    // Class Wise Grouping & Summary View
    $class_summary_sql = "SELECT cl.ClassNumber, cl.ClassCode, cl.ClassName, COUNT(b.BookNumber) as TotalBooks,
                          SUM(CASE WHEN b.BookStatus = 1 THEN 1 ELSE 0 END) as AvailableBooks,
                          SUM(CASE WHEN b.BookStatus = 2 THEN 1 ELSE 0 END) as IssuedBooks,
                          SUM(CASE WHEN b.BookStatus IN (3, 4, 5) THEN 1 ELSE 0 END) as ProblemBooks
                          FROM MstrClassification cl
                          LEFT JOIN MstrBookStock b ON cl.ClassNumber = b.ClassNumber
                          GROUP BY cl.ClassNumber, cl.ClassCode, cl.ClassName
                          ORDER BY cl.ClassCode ASC";
    $class_summary = db_query($conn, $class_summary_sql);

    // If a specific class is selected, fetch its titles
    $selected_class_books = null;
    $curr_class = null;
    if ($filter_class > 0) {
        $curr_class_res = db_query($conn, "SELECT * FROM MstrClassification WHERE ClassNumber = ?", "i", [$filter_class]);
        $curr_class = db_fetch_assoc($curr_class_res);

        $selected_class_books = db_query($conn, "SELECT b.*, a.AuthorName, p.PublisherName, c.CategoryName
                                                FROM MstrBookStock b
                                                LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                                                LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                                                LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                                                WHERE b.ClassNumber = ?
                                                ORDER BY b.BookName ASC", "i", [$filter_class]);
    }
    ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-layer-group me-2"></i>DDC Class Distribution</h5>
                    <span class="badge bg-light text-primary"><?php echo ($class_summary) ? db_num_rows($class_summary) : 0; ?> Classes</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php if ($class_summary && db_num_rows($class_summary) > 0): ?>
                        <?php while ($cs = db_fetch_assoc($class_summary)): ?>
                            <a href="books.php?tab=class_wise&class_id=<?php echo $cs['ClassNumber']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($filter_class == $cs['ClassNumber']) ? 'active' : ''; ?>">
                                <div>
                                    <span class="badge <?php echo ($filter_class == $cs['ClassNumber']) ? 'bg-white text-primary' : 'bg-secondary'; ?> me-2 font-monospace"><?php echo htmlspecialchars($cs['ClassCode']); ?></span>
                                    <strong><?php echo htmlspecialchars($cs['ClassName']); ?></strong>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?php echo ($filter_class == $cs['ClassNumber']) ? 'bg-warning text-dark' : 'bg-primary'; ?> rounded-pill"><?php echo $cs['TotalBooks']; ?> Titles</span>
                                    <?php if ($cs['IssuedBooks'] > 0): ?>
                                        <span class="badge bg-info text-white rounded-pill ms-1"><?php echo $cs['IssuedBooks']; ?> Issued</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">No classification records found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <?php if ($curr_class): ?>
                            <i class="fa-solid fa-book-open text-primary me-2"></i>Books in Class: [<?php echo htmlspecialchars($curr_class['ClassCode']); ?>] <?php echo htmlspecialchars($curr_class['ClassName']); ?>
                        <?php else: ?>
                            <i class="fa-solid fa-hand-pointer text-muted me-2"></i>Select a Class from the left to view titles
                        <?php endif; ?>
                    </h5>
                    <?php if ($selected_class_books): ?>
                        <span class="badge bg-secondary"><?php echo db_num_rows($selected_class_books); ?> Books</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($selected_class_books && db_num_rows($selected_class_books) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">Sl No.</th>
                                        <th>Call No.</th>
                                        <th>Name</th>
                                        <th>Author</th>
                                        <th>Publisher</th>
                                        <th>Barcode</th>
                                        <th>ISBN</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $c_sl = 1;
                                    while ($cb = db_fetch_assoc($selected_class_books)):
                                    ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?php echo $c_sl++; ?></td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars(!empty($cb['CallNumber']) ? $cb['CallNumber'] : 'N/A'); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($cb['BookName']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($cb['AuthorName'] ?? 'Unassigned'); ?></td>
                                            <td><span class="text-secondary"><?php echo htmlspecialchars($cb['PublisherName'] ?? 'N/A'); ?></span></td>
                                            <td><code class="text-dark bg-light px-1 border"><?php echo htmlspecialchars(!empty($cb['BookCode']) ? $cb['BookCode'] : 'N/A'); ?></code></td>
                                            <td><small class="font-monospace"><?php echo htmlspecialchars(!empty($cb['ISBNumber']) ? $cb['ISBNumber'] : 'N/A'); ?></small></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cb['CategoryName'] ?? 'General'); ?></span></td>
                                            <td>
                                                <?php if ($cb['BookStatus'] == 1): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php elseif ($cb['BookStatus'] == 2): ?>
                                                    <span class="badge bg-warning text-dark">Issued</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Condition Alert</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($filter_class > 0): ?>
                        <div class="p-5 text-center text-muted">No books currently cataloged in this classification.</div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fa-solid fa-arrow-left fa-2x mb-2 text-secondary"></i>
                            <p>Click on any Dewey Decimal / Classification group on the left side to drill down into book titles.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'category_wise'): ?>
    <?php
    // Category Wise Grouping & Summary View
    $cat_summary_sql = "SELECT c.CategoryNumber, c.CategoryName, COUNT(b.BookNumber) as TotalBooks,
                        SUM(CASE WHEN b.BookStatus = 1 THEN 1 ELSE 0 END) as AvailableBooks,
                        SUM(CASE WHEN b.BookStatus = 2 THEN 1 ELSE 0 END) as IssuedBooks
                        FROM MstrCategory c
                        LEFT JOIN MstrBookStock b ON c.CategoryNumber = b.CategoryNumber
                        GROUP BY c.CategoryNumber, c.CategoryName
                        ORDER BY TotalBooks DESC";
    $cat_summary = db_query($conn, $cat_summary_sql);

    // If a specific category is selected, fetch its titles
    $selected_cat_books = null;
    $curr_cat = null;
    if ($filter_cat > 0) {
        $curr_cat_res = db_query($conn, "SELECT * FROM MstrCategory WHERE CategoryNumber = ?", "i", [$filter_cat]);
        $curr_cat = db_fetch_assoc($curr_cat_res);

        $selected_cat_books = db_query($conn, "SELECT b.*, a.AuthorName, p.PublisherName, c.CategoryName
                                              FROM MstrBookStock b
                                              LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                                              LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
                                              LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
                                              WHERE b.CategoryNumber = ?
                                              ORDER BY b.BookName ASC", "i", [$filter_cat]);
    }
    ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-tags me-2"></i>Book Categories</h5>
                    <span class="badge bg-light text-dark"><?php echo ($cat_summary) ? db_num_rows($cat_summary) : 0; ?> Categories</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php if ($cat_summary && db_num_rows($cat_summary) > 0): ?>
                        <?php while ($cs = db_fetch_assoc($cat_summary)): ?>
                            <a href="books.php?tab=category_wise&cat_id=<?php echo $cs['CategoryNumber']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($filter_cat == $cs['CategoryNumber']) ? 'active' : ''; ?>">
                                <div>
                                    <i class="fa-solid fa-bookmark me-2 <?php echo ($filter_cat == $cs['CategoryNumber']) ? 'text-white' : 'text-info'; ?>"></i>
                                    <strong><?php echo htmlspecialchars($cs['CategoryName']); ?></strong>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?php echo ($filter_cat == $cs['CategoryNumber']) ? 'bg-warning text-dark' : 'bg-primary'; ?> rounded-pill"><?php echo $cs['TotalBooks']; ?> Titles</span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">No categories found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <?php if ($curr_cat): ?>
                            <i class="fa-solid fa-book-bookmark text-info me-2"></i>Category: <?php echo htmlspecialchars($curr_cat['CategoryName']); ?>
                        <?php else: ?>
                            <i class="fa-solid fa-hand-pointer text-muted me-2"></i>Select a Category from the left to view titles
                        <?php endif; ?>
                    </h5>
                    <?php if ($selected_cat_books): ?>
                        <span class="badge bg-secondary"><?php echo db_num_rows($selected_cat_books); ?> Books</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($selected_cat_books && db_num_rows($selected_cat_books) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">Sl No.</th>
                                        <th>Call No.</th>
                                        <th>Name</th>
                                        <th>Author</th>
                                        <th>Publisher</th>
                                        <th>Barcode</th>
                                        <th>ISBN</th>
                                        <th>Acc No.</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $cat_sl = 1;
                                    while ($cb = db_fetch_assoc($selected_cat_books)):
                                    ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted"><?php echo $cat_sl++; ?></td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars(!empty($cb['CallNumber']) ? $cb['CallNumber'] : 'N/A'); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($cb['BookName']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($cb['AuthorName'] ?? 'Unassigned'); ?></td>
                                            <td><span class="text-secondary"><?php echo htmlspecialchars($cb['PublisherName'] ?? 'N/A'); ?></span></td>
                                            <td><code class="text-dark bg-light px-1 border"><?php echo htmlspecialchars(!empty($cb['BookCode']) ? $cb['BookCode'] : 'N/A'); ?></code></td>
                                            <td><small class="font-monospace"><?php echo htmlspecialchars(!empty($cb['ISBNumber']) ? $cb['ISBNumber'] : 'N/A'); ?></small></td>
                                            <td><span class="badge bg-dark font-monospace"><?php echo htmlspecialchars($cb['AccessionNumber']); ?></span></td>
                                            <td>
                                                <?php if ($cb['BookStatus'] == 1): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php elseif ($cb['BookStatus'] == 2): ?>
                                                    <span class="badge bg-warning text-dark">Issued</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($filter_cat > 0): ?>
                        <div class="p-5 text-center text-muted">No books currently cataloged in this category.</div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fa-solid fa-arrow-left fa-2x mb-2 text-secondary"></i>
                            <p>Click on any book category on the left side to drill down into book titles.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'issue_register'): ?>
    <?php
    // Issue Register View (Active & Recent issues from TransBookIssue)
    $search_issue = sanitize_input($_GET['search_issue'] ?? '');
    $issue_where = "";
    $issue_params = [];
    $issue_types = "";

    if (!empty($search_issue)) {
        $issue_where = " WHERE i.AccessionNumber LIKE ? OR i.MemberName LIKE ? OR b.BookName LIKE ? OR i.BookNumber LIKE ? ";
        $sip = "%$search_issue%";
        $issue_params = [$sip, $sip, $sip, $sip];
        $issue_types = "ssss";
    }

    $sql_issues = "SELECT i.*, b.BookName, b.CallNumber, m.PhoneHome, m.HouseName
                   FROM TransBookIssue i
                   LEFT JOIN MstrBookStock b ON (i.AccessionNumber = b.AccessionNumber OR i.BookNumber = b.BookNumber)
                   LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
                   $issue_where
                   ORDER BY i.IssueDate DESC, i.IssueID DESC LIMIT 100";
    $issues_result = db_query($conn, $sql_issues, $issue_types, $issue_params);
    ?>

    <!-- Issue Register Filter Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="books.php" class="row g-3">
                <input type="hidden" name="tab" value="issue_register">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search_issue" class="form-control" placeholder="Search Issue Register by Accession No, Member Name, or Title..." value="<?php echo htmlspecialchars($search_issue); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter me-1"></i>Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Issue Register Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-clipboard-list text-warning me-2"></i>Circulation Issue & Return Register</h5>
            <div class="d-flex gap-2">
                <a href="/modules/circulation/issue.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus me-1"></i>New Issue</a>
                <a href="/modules/circulation/return.php" class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>Process Return</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Issue ID / Acc #</th>
                            <th>Book Title</th>
                            <th>Member Details</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Fine Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($issues_result && db_num_rows($issues_result) > 0): ?>
                            <?php while ($is = db_fetch_assoc($issues_result)): ?>
                                <?php
                                $is_returned = !empty($is['ReturnDate']) || ($is['IssueStatus'] == 2);
                                $is_overdue = !$is_returned && !empty($is['DueDate']) && (strtotime($is['DueDate']) < time());
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($is['AccessionNumber'] ?? 'N/A'); ?></span>
                                        <?php if (!empty($is['IssueID'])): ?>
                                            <div class="text-muted small">#<?php echo $is['IssueID']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($is['BookName'] ?? 'Book #' . $is['BookNumber']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($is['MemberName'] ?? 'Member #' . $is['MemberNumber']); ?></strong>
                                        <div class="text-muted small">MEM-<?php echo str_pad($is['MemberNumber'] ?? 0, 4, '0', STR_PAD_LEFT); ?></div>
                                    </td>
                                    <td><?php echo $is['IssueDate'] ? date('d-M-Y', strtotime($is['IssueDate'])) : 'N/A'; ?></td>
                                    <td>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo $is['DueDate'] ? date('d-M-Y', strtotime($is['DueDate'])) : 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($is['ReturnDate']) ? date('d-M-Y', strtotime($is['ReturnDate'])) : '<span class="text-muted">Not Returned</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo (!empty($is['FinePaid']) && $is['FinePaid'] > 0) ? '₹' . number_format($is['FinePaid'], 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_returned): ?>
                                            <span class="badge bg-success">Returned</span>
                                        <?php elseif ($is_overdue): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Active Checkout</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">No circulation issue records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="books.php?tab=<?php echo $tab; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Add New Book Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Book Title / Name *</label>
                        <input type="text" name="BookName" class="form-control" placeholder="Enter full title" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Accession Number *</label>
                        <input type="text" name="AccessionNumber" class="form-control" placeholder="e.g. ACC1009" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Barcode / Item Code</label>
                        <input type="text" name="BookCode" class="form-control" placeholder="Auto-generated if blank">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">ISBN</label>
                        <input type="text" name="ISBNumber" class="form-control" placeholder="ISBN-10 or ISBN-13">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Call Number</label>
                        <input type="text" name="CallNumber" class="form-control" placeholder="e.g. A0009">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Price (₹)</label>
                        <input type="number" step="0.01" name="BookPrice" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Author</label>
                        <select name="AuthorNumber" class="form-select">
                            <option value="">-- Select Author --</option>
                            <?php db_data_seek($authors, 0); ?>
                            <?php while ($auth = db_fetch_assoc($authors)): ?>
                                <option value="<?php echo $auth['AuthorNumber']; ?>"><?php echo htmlspecialchars($auth['AuthorName']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Publisher</label>
                        <select name="PublisherNumber" class="form-select">
                            <option value="">-- Select Publisher --</option>
                            <?php db_data_seek($publishers, 0); ?>
                            <?php while ($pub = db_fetch_assoc($publishers)): ?>
                                <option value="<?php echo $pub['PublisherNumber']; ?>"><?php echo htmlspecialchars($pub['PublisherName']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Classification (DDC)</label>
                        <select name="ClassNumber" class="form-select">
                            <option value="">-- Select Class --</option>
                            <?php db_data_seek($classifications, 0); ?>
                            <?php while ($cls = db_fetch_assoc($classifications)): ?>
                                <option value="<?php echo $cls['ClassNumber']; ?>"><?php echo htmlspecialchars($cls['ClassCode'] . ' - ' . $cls['ClassName']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Category</label>
                        <select name="CategoryNumber" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php db_data_seek($categories, 0); ?>
                            <?php while ($cat = db_fetch_assoc($categories)): ?>
                                <option value="<?php echo $cat['CategoryNumber']; ?>"><?php echo htmlspecialchars($cat['CategoryName']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Condition Status</label>
                        <select name="BookStatus" class="form-select">
                            <option value="1" selected>Available</option>
                            <option value="3">Missing</option>
                            <option value="4">Damaged</option>
                            <option value="5">Lost</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Remarks / Shelf Location</label>
                        <input type="text" name="BookRemarks" class="form-control" placeholder="Optional notes, condition, or shelf details">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Save Book Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
