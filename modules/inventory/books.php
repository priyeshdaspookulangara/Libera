<?php
// modules/inventory/books.php - Book Catalog & Inventory Master Management
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Book Catalog & Inventory";
$msg = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        $book_name = sanitize_input($_POST['BookName'] ?? '');
        $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
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
            if ($action === 'add') {
                // Check unique Accession Number
                $check = db_query($conn, "SELECT BookNumber FROM MstrBookStock WHERE AccessionNumber = ?", "s", [$accession]);
                if ($check && db_num_rows($check) > 0) {
                    $error = "Accession Number '$accession' already exists!";
                } else {
                    $book_code = 'B' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $sql = "INSERT INTO MstrBookStock (BookName, BookStatus, BookCode, BookRemarks, CallNumber, ISBNumber, AccessionNumber, AuthorNumber, ClassNumber, PublisherNumber, BookPrice, LanguageNumber, CategoryNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $res = db_query($conn, $sql, "sissssiiiidii", [$book_name, $status, $book_code, $remarks, $call_num, $isbn, $accession, $author_id, $class_id, $pub_id, $price, $lang_id, $cat_id]);
                    if ($res) {
                        $msg = "Book added successfully to catalog stock.";
                    } else {
                        $error = "Error adding book stock.";
                    }
                }
            } elseif ($action === 'edit') {
                $book_id = intval($_POST['BookNumber']);
                $sql = "UPDATE MstrBookStock SET BookName = ?, BookStatus = ?, BookRemarks = ?, CallNumber = ?, ISBNumber = ?, AccessionNumber = ?, AuthorNumber = ?, ClassNumber = ?, PublisherNumber = ?, BookPrice = ?, LanguageNumber = ?, CategoryNumber = ? WHERE BookNumber = ?";
                $res = db_query($conn, $sql, "sissssiiiidiii", [$book_name, $status, $remarks, $call_num, $isbn, $accession, $author_id, $class_id, $pub_id, $price, $lang_id, $cat_id, $book_id]);
                if ($res !== false) {
                    $msg = "Book details updated successfully.";
                } else {
                    $error = "Failed to update book details.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $book_id = intval($_POST['BookNumber']);
        $res = db_query($conn, "DELETE FROM MstrBookStock WHERE BookNumber = ?", "i", [$book_id]);
        if ($res) {
            $msg = "Book removed from inventory.";
        } else {
            $error = "Cannot delete book if it has active circulation history.";
        }
    }
}

// Search / Filter
$search = sanitize_input($_GET['search'] ?? '');
$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE b.BookName LIKE ? OR b.AccessionNumber LIKE ? OR b.ISBNumber LIKE ? OR a.AuthorName LIKE ? ";
    $s_param = "%$search%";
    $params = [$s_param, $s_param, $s_param, $s_param];
    $types = "ssss";
}

$sql_books = "SELECT b.*, a.AuthorName, p.PublisherName, c.CategoryName, lang.LanguageName, cl.ClassName
              FROM MstrBookStock b
              LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
              LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
              LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
              LEFT JOIN MstrLanguage lang ON b.LanguageNumber = lang.LanguageNumber
              LEFT JOIN MstrClassification cl ON b.ClassNumber = cl.ClassNumber
              $where_clause
              ORDER BY b.BookNumber DESC LIMIT 100";

$books_result = db_query($conn, $sql_books, $types, $params);

// Fetch Master Directories for dropdowns
$authors = db_query($conn, "SELECT AuthorNumber, AuthorName FROM MstrAuthor ORDER BY AuthorName ASC");
$publishers = db_query($conn, "SELECT PublisherNumber, PublisherName FROM MstrPublisher ORDER BY PublisherName ASC");
$categories = db_query($conn, "SELECT CategoryNumber, CategoryName FROM MstrCategory ORDER BY CategoryName ASC");
$languages = db_query($conn, "SELECT LanguageNumber, LanguageName FROM MstrLanguage ORDER BY LanguageName ASC");
$classifications = db_query($conn, "SELECT ClassNumber, ClassName, ClassCode FROM MstrClassification ORDER BY ClassCode ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-book-open text-primary me-2"></i>Book Catalog & Stock Master</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
        <i class="fa-solid fa-plus me-1"></i>Add New Book Stock
    </button>
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

<!-- Search Widget -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="books.php" class="row g-3">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Book Title, Accession #, ISBN, or Author..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter me-1"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Books Master Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Acc No.</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Publisher</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($books_result && db_num_rows($books_result) > 0): ?>
                        <?php while ($b = db_fetch_assoc($books_result)): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($b['AccessionNumber'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($b['BookName']); ?></strong>
                                    <div class="text-muted small">ISBN: <?php echo htmlspecialchars($b['ISBNumber'] ?? 'N/A'); ?> | Call: <?php echo htmlspecialchars($b['CallNumber'] ?? 'N/A'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($b['AuthorName'] ?? 'Unassigned'); ?></td>
                                <td><?php echo htmlspecialchars($b['PublisherName'] ?? 'Unassigned'); ?></td>
                                <td><?php echo htmlspecialchars($b['CategoryName'] ?? 'General'); ?></td>
                                <td>₹<?php echo number_format($b['BookPrice'] ?? 0, 2); ?></td>
                                <td>
                                    <?php if ($b['BookStatus'] == 1): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php elseif ($b['BookStatus'] == 2): ?>
                                        <span class="badge bg-warning text-dark">Issued</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#editBookModal<?php echo $b['BookNumber']; ?>">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <form method="POST" action="books.php" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this book stock item?');">
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
                                        <form method="POST" action="books.php">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="BookNumber" value="<?php echo $b['BookNumber']; ?>">
                                            <div class="modal-header bg-info text-white">
                                                <h5 class="modal-title"><i class="fa-solid fa-pen me-2"></i>Edit Book Stock Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label fw-bold small">Book Title *</label>
                                                    <input type="text" name="BookName" class="form-control" required value="<?php echo htmlspecialchars($b['BookName']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Accession Number *</label>
                                                    <input type="text" name="AccessionNumber" class="form-control" required value="<?php echo htmlspecialchars($b['AccessionNumber']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">ISBN</label>
                                                    <input type="text" name="ISBNumber" class="form-control" value="<?php echo htmlspecialchars($b['ISBNumber']); ?>">
                                                </div>
                                                <div class="col-md-4">
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
                                                    <label class="form-label fw-bold small">Language</label>
                                                    <select name="LanguageNumber" class="form-select">
                                                        <option value="">-- Select Language --</option>
                                                        <?php db_data_seek($languages, 0); ?>
                                                        <?php while ($lng = db_fetch_assoc($languages)): ?>
                                                            <option value="<?php echo $lng['LanguageNumber']; ?>" <?php echo ($b['LanguageNumber'] == $lng['LanguageNumber']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lng['LanguageName']); ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Status</label>
                                                    <select name="BookStatus" class="form-select">
                                                        <option value="1" <?php echo ($b['BookStatus'] == 1) ? 'selected' : ''; ?>>Available</option>
                                                        <option value="2" <?php echo ($b['BookStatus'] == 2) ? 'selected' : ''; ?>>Issued</option>
                                                        <option value="0" <?php echo ($b['BookStatus'] == 0) ? 'selected' : ''; ?>>Inactive / Lost</option>
                                                    </select>
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
                            <td colspan="8" class="text-center text-muted py-4">No book stock records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="books.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Add New Book Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Book Title *</label>
                        <input type="text" name="BookName" class="form-control" placeholder="Enter full title" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Accession Number *</label>
                        <input type="text" name="AccessionNumber" class="form-control" placeholder="e.g. ACC1006" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">ISBN</label>
                        <input type="text" name="ISBNumber" class="form-control" placeholder="ISBN-10 or ISBN-13">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Call Number</label>
                        <input type="text" name="CallNumber" class="form-control" placeholder="e.g. A0006">
                    </div>
                    <div class="col-md-4">
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
                        <label class="form-label fw-bold small">Language</label>
                        <select name="LanguageNumber" class="form-select">
                            <option value="">-- Select Language --</option>
                            <?php db_data_seek($languages, 0); ?>
                            <?php while ($lng = db_fetch_assoc($languages)): ?>
                                <option value="<?php echo $lng['LanguageNumber']; ?>"><?php echo htmlspecialchars($lng['LanguageName']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Initial Status</label>
                        <select name="BookStatus" class="form-select">
                            <option value="1" selected>Available</option>
                            <option value="0">Inactive</option>
                        </select>
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
