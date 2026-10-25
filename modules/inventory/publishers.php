<?php
// modules/inventory/publishers.php - Publisher Master Directory
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Publishers Master Directory";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = sanitize_input($_POST['PublisherName'] ?? '');
        $code = sanitize_input($_POST['PublisherCode'] ?? '');
        $place = sanitize_input($_POST['PublisherPlace'] ?? '');
        $phone = sanitize_input($_POST['PublisherPhone'] ?? '');
        if (!empty($name)) {
            $sql = "INSERT INTO MstrPublisher (PublisherName, PublisherStatus, PublisherCode, PublisherPlace, PublisherPhone) VALUES (?, 1, ?, ?, ?)";
            $res = db_query($conn, $sql, "ssss", [$name, $code, $place, $phone]);
            if ($res) $msg = "Publisher added successfully.";
            else $error = "Failed to add publisher.";
        } else {
            $error = "Publisher name is required.";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['PublisherNumber']);
        $res = db_query($conn, "DELETE FROM MstrPublisher WHERE PublisherNumber = ?", "i", [$id]);
        if ($res) $msg = "Publisher deleted.";
        else $error = "Could not delete publisher.";
    }
}

$publishers = db_query($conn, "SELECT * FROM MstrPublisher ORDER BY PublisherName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-building text-primary me-2"></i>Publishers Directory</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPubModal">
        <i class="fa-solid fa-plus me-1"></i>Add Publisher
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
                    <th>Publisher Name</th>
                    <th>Place</th>
                    <th>Phone</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($publishers && db_num_rows($publishers) > 0): ?>
                    <?php while ($p = db_fetch_assoc($publishers)): ?>
                        <tr>
                            <td><?php echo $p['PublisherNumber']; ?></td>
                            <td><code><?php echo htmlspecialchars($p['PublisherCode'] ?? 'N/A'); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($p['PublisherName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['PublisherPlace'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['PublisherPhone'] ?? 'N/A'); ?></td>
                            <td class="text-end">
                                <form method="POST" action="publishers.php" class="d-inline" onsubmit="return confirm('Delete publisher?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="PublisherNumber" value="<?php echo $p['PublisherNumber']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No publishers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addPubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="publishers.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Publisher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Publisher Name *</label>
                        <input type="text" name="PublisherName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Publisher Code</label>
                        <input type="text" name="PublisherCode" class="form-control" placeholder="PUB001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Phone</label>
                        <input type="text" name="PublisherPhone" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Place / Location</label>
                        <input type="text" name="PublisherPlace" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Publisher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
