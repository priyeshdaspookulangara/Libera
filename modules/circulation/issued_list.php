<?php
// modules/circulation/issued_list.php - Active Checkouts & Overdues Report
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Active Checkouts & Overdues";

// Query active checkouts
$sql = "SELECT i.*, b.BookName, m.MemberName, m.PhoneHome
        FROM TransBookIssue i
        LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber
        LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
        WHERE i.IssueStatus = 1
        ORDER BY i.DueDate ASC";

$result = db_query($conn, $sql);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-list-check text-primary me-2"></i>Active Checkouts & Overdues Alert</h2>
    <div>
        <a href="/modules/circulation/issue.php" class="btn btn-primary me-2"><i class="fa-solid fa-plus me-1"></i>Issue Book</a>
        <a href="/modules/circulation/return.php" class="btn btn-dark"><i class="fa-solid fa-rotate-left me-1"></i>Return Book</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Acc No.</th>
                        <th>Book Title</th>
                        <th>Borrower Member</th>
                        <th>Phone</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Overdue Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && db_num_rows($result) > 0): ?>
                        <?php while ($row = db_fetch_assoc($result)): ?>
                            <?php
                                $due = strtotime($row['DueDate']);
                                $today = strtotime(date('Y-m-d'));
                                $overdue_days = 0;
                                if ($today > $due) {
                                    $overdue_days = ceil(($today - $due) / (60 * 60 * 24));
                                }
                            ?>
                            <tr class="<?php echo ($overdue_days > 0) ? 'table-danger' : ''; ?>">
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['AccessionNumber']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['BookName'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['MemberName'] ?? 'N/A'); ?> (MEM-<?php echo str_pad($row['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>)</td>
                                <td><?php echo htmlspecialchars($row['PhoneHome'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['IssueDate'])); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['DueDate'])); ?></td>
                                <td>
                                    <?php if ($overdue_days > 0): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-bell me-1"></i><?php echo $overdue_days; ?> Days Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active / On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/modules/circulation/return.php?search_accession=<?php echo urlencode($row['AccessionNumber']); ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fa-solid fa-rotate-left me-1"></i>Return
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No active checkouts recorded currently.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
