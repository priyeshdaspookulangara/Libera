<?php
// modules/inventory/authors.php - Author Master Directory
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Authors Master Directory";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = sanitize_input($_POST['AuthorName'] ?? '');
        $code = sanitize_input($_POST['AuthorCode'] ?? '');
        $place = sanitize_input($_POST['AuthorPlace'] ?? '');
        if (!empty($name)) {
            $sql = "INSERT INTO MstrAuthor (AuthorName, AuthorStatus, AuthorCode, AuthorPlace) VALUES (?, 1, ?, ?)";
            $res = db_query($conn, $sql, "sss", [$name, $code, $place]);
            if ($res) $msg = "Author registered successfully.";
            else $error = "Failed to add author.";
        } else {
            $error = "Author name is required.";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['AuthorNumber']);
        $res = db_query($conn, "DELETE FROM MstrAuthor WHERE AuthorNumber = ?", "i", [$id]);
        if ($res) $msg = "Author deleted.";
        else $error = "Could not delete author.";
    }
}

$authors = db_query($conn, "SELECT * FROM MstrAuthor ORDER BY AuthorName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-user-pen text-primary me-2"></i>Authors Directory</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAuthorModal">
        <i class="fa-solid fa-plus me-1"></i>Add New Author
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 small"><?php echo $msg; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2 small"><?php echo $error; ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Author Name</th>
                    <th>Place</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($authors && db_num_rows($authors) > 0): ?>
                    <?php while ($a = db_fetch_assoc($authors)): ?>
                        <tr>
                            <td><?php echo $a['AuthorNumber']; ?></td>
                            <td><code><?php echo htmlspecialchars($a['AuthorCode'] ?? 'N/A'); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($a['AuthorName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['AuthorPlace'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="text-end">
                                <form method="POST" action="authors.php" class="d-inline" onsubmit="return confirm('Delete author?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="AuthorNumber" value="<?php echo $a['AuthorNumber']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No authors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addAuthorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="authors.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Author</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Author Name *</label>
                        <input type="text" name="AuthorName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Author Code</label>
                        <input type="text" name="AuthorCode" class="form-control" placeholder="AUT001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Place / Origin</label>
                        <input type="text" name="AuthorPlace" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Author</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
