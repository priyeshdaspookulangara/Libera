<?php
// modules/accounts/audit_log.php - Enterprise System & Financial Audit Trail
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Enterprise Audit Trail Logs";

$module_filter = sanitize_input($_GET['module'] ?? '');
$action_filter = sanitize_input($_GET['action_type'] ?? '');
$date_from = sanitize_input($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitize_input($_GET['date_to'] ?? date('Y-m-d'));

$where = ["DATE(ActionTimestamp) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = "ss";

if (!empty($module_filter)) {
    $where[] = "Module = ?";
    $params[] = $module_filter;
    $types .= "s";
}

if (!empty($action_filter)) {
    $where[] = "ActionType = ?";
    $params[] = $action_filter;
    $types .= "s";
}

$sql = "SELECT * FROM AuditLogs WHERE " . implode(" AND ", $where) . " ORDER BY LogID DESC LIMIT 200";
$logs = db_query($conn, $sql, $types, $params);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / ACCOUNTS / AUDIT TRAIL LOGS</div>
<h1 class="page-header-title"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>System & Financial Audit Trail</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body bg-light">
        <form method="GET" action="audit_log.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small">Module</label>
                <select name="module" class="form-select">
                    <option value="">-- All Modules --</option>
                    <option value="CIRCULATION" <?php echo $module_filter === 'CIRCULATION' ? 'selected' : ''; ?>>Circulation</option>
                    <option value="INVENTORY" <?php echo $module_filter === 'INVENTORY' ? 'selected' : ''; ?>>Inventory</option>
                    <option value="ACCOUNTS" <?php echo $module_filter === 'ACCOUNTS' ? 'selected' : ''; ?>>Accounts</option>
                    <option value="MEMBERS" <?php echo $module_filter === 'MEMBERS' ? 'selected' : ''; ?>>Members</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Action Type</label>
                <select name="action_type" class="form-select">
                    <option value="">-- All Actions --</option>
                    <option value="BOOK_ISSUE" <?php echo $action_filter === 'BOOK_ISSUE' ? 'selected' : ''; ?>>Book Issue</option>
                    <option value="BOOK_RETURN" <?php echo $action_filter === 'BOOK_RETURN' ? 'selected' : ''; ?>>Book Return</option>
                    <option value="MEMBER_RENEW" <?php echo $action_filter === 'MEMBER_RENEW' ? 'selected' : ''; ?>>Member Renew</option>
                    <option value="BOOK_WRITEOFF" <?php echo $action_filter === 'BOOK_WRITEOFF' ? 'selected' : ''; ?>>Book Write-off</option>
                    <option value="FISCAL_CLOSE" <?php echo $action_filter === 'FISCAL_CLOSE' ? 'selected' : ''; ?>>Fiscal Closing</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter me-2"></i>Filter Logs</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Immutable System Activity & Audit Ledger</h5>
        <span class="badge bg-light text-dark fw-bold">Recent Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Log ID</th>
                        <th>Timestamp</th>
                        <th>Module</th>
                        <th>Action Type</th>
                        <th>Ref / Voucher</th>
                        <th>Staff User</th>
                        <th>Action Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && db_num_rows($logs) > 0): ?>
                        <?php while ($l = db_fetch_assoc($logs)): ?>
                            <tr>
                                <td><code>#<?php echo $l['LogID']; ?></code></td>
                                <td class="small text-muted"><?php echo date('Y-m-d H:i:s', strtotime($l['ActionTimestamp'])); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($l['Module']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($l['ActionType']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($l['ReferenceID']); ?></code></td>
                                <td><i class="fa-solid fa-user me-1 text-secondary"></i><?php echo htmlspecialchars($l['UserName']); ?></td>
                                <td>
                                    <pre class="bg-light p-2 mb-0 rounded border small text-wrap" style="max-height:80px; overflow-y:auto;"><?php
                                        $details = json_decode($l['DetailsJSON'], true);
                                        echo htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT));
                                    ?></pre>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No audit logs match the specified criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
