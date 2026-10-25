<?php
// modules/inventory/categories.php - Categories & DDC Classification Master
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Categories & DDC Classifications";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add_cat') {
        $name = sanitize_input($_POST['CategoryName'] ?? '');
        if (!empty($name)) {
            $sql = "INSERT INTO MstrCategory (CategoryName, CategoryStatus) VALUES (?, 1)";
            $res = db_query($conn, $sql, "s", [$name]);
            if ($res) $msg = "Category added.";
            else $error = "Failed to add category.";
        }
    } elseif ($action === 'add_ddc') {
        $code = sanitize_input($_POST['ClassCode'] ?? '');
        $title = sanitize_input($_POST['ClassName'] ?? '');
        if (!empty($code) && !empty($title)) {
            $sql = "INSERT INTO MstrClassification (ClassCode, ClassName, ClassStatus) VALUES (?, ?, 1)";
            $res = db_query($conn, $sql, "ss", [$code, $title]);
            if ($res) $msg = "DDC Classification added.";
            else $error = "Failed to add DDC classification.";
        }
    }
}

$categories = db_query($conn, "SELECT * FROM MstrCategory ORDER BY CategoryName ASC");
$ddc = db_query($conn, "SELECT * FROM MstrClassification ORDER BY ClassCode ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <!-- Categories Column -->
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-tags me-2"></i>Book Categories</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="categories.php" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="add_cat">
                    <div class="col-8">
                        <input type="text" name="CategoryName" class="form-control" placeholder="New Category Name" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
                <ul class="list-group">
                    <?php if ($categories && db_num_rows($categories) > 0): ?>
                        <?php while ($c = db_fetch_assoc($categories)): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($c['CategoryName']); ?>
                                <span class="badge bg-secondary rounded-pill">ID: <?php echo $c['CategoryNumber']; ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-muted">No categories created yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- DDC Classes Column -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-sitemap me-2"></i>DDC Classifications</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="categories.php" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="add_ddc">
                    <div class="col-md-4">
                        <input type="text" name="ClassCode" class="form-control" placeholder="DDC Code (e.g. 500)" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="ClassName" class="form-control" placeholder="Class Title (e.g. Pure Sciences)" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100">Add</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-secondary">
                            <tr>
                                <th>Code</th>
                                <th>Classification Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ddc && db_num_rows($ddc) > 0): ?>
                                <?php while ($d = db_fetch_assoc($ddc)): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($d['ClassCode'] ?? 'N/A'); ?></code></td>
                                        <td><?php echo htmlspecialchars($d['ClassName']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-muted">No DDC classes found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
