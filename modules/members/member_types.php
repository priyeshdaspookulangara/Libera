<?php
// modules/members/member_types.php - Membership Rules & Circulation Policies
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Membership Classes & Rules";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        $name = sanitize_input($_POST['MemberTypeName'] ?? '');
        $code = sanitize_input($_POST['MemberTypeCode'] ?? '');
        $app_fee = floatval($_POST['ApplicationFee'] ?? 0);
        $mem_amount = floatval($_POST['MembershipAmount'] ?? 0);
        $sub_amount = floatval($_POST['SubscriptionAmount'] ?? 0);
        $allowed_books = intval($_POST['IssueBookCount'] ?? 3);
        $allowed_days = intval($_POST['IssueAllowedDays'] ?? 14);
        $fine_amount = floatval($_POST['IssueFineAmount'] ?? 1.0);

        if (empty($name)) {
            $error = "Membership Type Name is required.";
        } else {
            if ($action === 'add') {
                $sql = "INSERT INTO MstrMemberType (MemberTypeName, MemberTypeStatus, MemberTypeCode, ApplicationFee, MembershipAmount, SubscriptionAmount, IssueBookCount, IssueAllowedDays, IssueFineAmount) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)";
                $res = db_query($conn, $sql, "ssdddiid", [$name, $code, $app_fee, $mem_amount, $sub_amount, $allowed_books, $allowed_days, $fine_amount]);
                if ($res) $msg = "Membership Class created successfully.";
                else $error = "Failed to create membership class.";
            } elseif ($action === 'edit') {
                $id = intval($_POST['MemberTypeNumber']);
                $sql = "UPDATE MstrMemberType SET MemberTypeName = ?, MemberTypeCode = ?, ApplicationFee = ?, MembershipAmount = ?, SubscriptionAmount = ?, IssueBookCount = ?, IssueAllowedDays = ?, IssueFineAmount = ? WHERE MemberTypeNumber = ?";
                $res = db_query($conn, $sql, "ssdddiidi", [$name, $code, $app_fee, $mem_amount, $sub_amount, $allowed_books, $allowed_days, $fine_amount, $id]);
                if ($res !== false) $msg = "Membership rules updated.";
                else $error = "Failed to update membership class.";
            }
        }
    }
}

$types_res = db_query($conn, "SELECT * FROM MstrMemberType ORDER BY MemberTypeNumber ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-layer-group text-primary me-2"></i>Membership Classes & Borrowing Policies</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeModal">
        <i class="fa-solid fa-plus me-1"></i>New Membership Class
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 small"><?php echo $msg; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2 small"><?php echo $error; ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-striped mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Membership Class</th>
                    <th>App Fee</th>
                    <th>Deposit / Sub Fee</th>
                    <th>Max Books Allowed</th>
                    <th>Issue Duration (Days)</th>
                    <th>Fine Rate (₹/day)</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($types_res && db_num_rows($types_res) > 0): ?>
                    <?php while ($t = db_fetch_assoc($types_res)): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($t['MemberTypeCode'] ?? 'N/A'); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($t['MemberTypeName']); ?></strong></td>
                            <td>₹<?php echo number_format($t['ApplicationFee'] ?? 0, 2); ?></td>
                            <td>₹<?php echo number_format($t['MembershipAmount'] ?? 0, 2); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo $t['IssueBookCount']; ?> Books</span></td>
                            <td><span class="badge bg-warning text-dark"><?php echo $t['IssueAllowedDays']; ?> Days</span></td>
                            <td><span class="badge bg-danger">₹<?php echo number_format($t['IssueFineAmount'], 2); ?>/day</span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editTypeModal<?php echo $t['MemberTypeNumber']; ?>">
                                    <i class="fa-solid fa-pen"></i> Edit Rules
                                </button>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editTypeModal<?php echo $t['MemberTypeNumber']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="member_types.php">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="MemberTypeNumber" value="<?php echo $t['MemberTypeNumber']; ?>">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title">Edit Membership Rules</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label fw-bold small">Class Name *</label>
                                                <input type="text" name="MemberTypeName" class="form-control" required value="<?php echo htmlspecialchars($t['MemberTypeName']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Code</label>
                                                <input type="text" name="MemberTypeCode" class="form-control" value="<?php echo htmlspecialchars($t['MemberTypeCode']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">App Fee (₹)</label>
                                                <input type="number" step="0.01" name="ApplicationFee" class="form-control" value="<?php echo $t['ApplicationFee']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Deposit Fee (₹)</label>
                                                <input type="number" step="0.01" name="MembershipAmount" class="form-control" value="<?php echo $t['MembershipAmount']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Sub Fee (₹)</label>
                                                <input type="number" step="0.01" name="SubscriptionAmount" class="form-control" value="<?php echo $t['SubscriptionAmount']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Max Book Count</label>
                                                <input type="number" name="IssueBookCount" class="form-control" value="<?php echo $t['IssueBookCount']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Allowed Days</label>
                                                <input type="number" name="IssueAllowedDays" class="form-control" value="<?php echo $t['IssueAllowedDays']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold small">Fine / Overdue Day (₹)</label>
                                                <input type="number" step="0.01" name="IssueFineAmount" class="form-control" value="<?php echo $t['IssueFineAmount']; ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-info text-white">Save Rules</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No membership classes configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="member_types.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Create Membership Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Class Name *</label>
                        <input type="text" name="MemberTypeName" class="form-control" required placeholder="e.g. Student Member">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Code</label>
                        <input type="text" name="MemberTypeCode" class="form-control" placeholder="STU">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">App Fee (₹)</label>
                        <input type="number" step="0.01" name="ApplicationFee" class="form-control" value="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Deposit Fee (₹)</label>
                        <input type="number" step="0.01" name="MembershipAmount" class="form-control" value="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Sub Fee (₹)</label>
                        <input type="number" step="0.01" name="SubscriptionAmount" class="form-control" value="50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Max Book Count</label>
                        <input type="number" name="IssueBookCount" class="form-control" value="3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Allowed Days</label>
                        <input type="number" name="IssueAllowedDays" class="form-control" value="14">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Fine / Overdue Day (₹)</label>
                        <input type="number" step="0.01" name="IssueFineAmount" class="form-control" value="1.0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
