<?php
// modules/members/renew.php - Membership Tiering, Validity & Renewal Management
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Membership Tier Validity & Subscriptions";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
    $member_id = intval($_POST['MemberNumber']);
    $renewal_years = intval($_POST['RenewalYears'] ?? 1);

    // Fetch member and tier details
    $mem_res = db_query($conn, "SELECT m.*, d.MemberJoinDate, mt.SubscriptionAmount, mt.MemberTypeName
                               FROM MstrMember m
                               LEFT JOIN MstrMemberDetails d ON m.MemberNumber = d.MemberNumber
                               LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                               LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                               WHERE m.MemberNumber = ? LIMIT 1", "i", [$member_id]);
    if ($mem_res && db_num_rows($mem_res) > 0) {
        $member = db_fetch_assoc($mem_res);
        $sub_fee = floatval($member['SubscriptionAmount'] ?? 50.0) * $renewal_years;
        $today = date('Y-m-d');
        $new_expiry = date('Y-m-d', strtotime("+ $renewal_years years"));

        db_begin_transaction($conn);
        try {
            // Update details
            db_query($conn, "UPDATE MstrMemberDetails SET ApprovedDate = ? WHERE MemberNumber = ?", "si", [$new_expiry, $member_id]);

            // Automatically post balancing voucher if subscription fee collected
            if ($sub_fee > 0) {
                $fiscal = get_active_fiscal_year($conn);
                $year_num = intval($fiscal['YearNumber']);

                $res_h = db_query($conn, "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks, MemberNumber) VALUES (?, 14, ?, ?, ?)", "sisi", [$today, $year_num, "Subscription Renewal ($renewal_years yr) - {$member['MemberName']}", $member_id]);
                $head_id = $res_h['insert_id'] ?? 1;

                // Dr Cash (1), Cr Subscription Income (10013)
                db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10013, ?, ?, ?)", "isidds", [$head_id, $today, $member_id, $sub_fee, "Subscription Renewal Receipt"]);
                db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, 'Cash Subscription')", "isids", [$head_id, $today, $member_id, $sub_fee]);
                db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, 'Subscription Fee Income')", "isids", [$head_id, $today, $member_id, $sub_fee]);

                // Audit Log
                db_query($conn, "INSERT INTO AuditLogs (ActionType, Module, ReferenceID, UserNumber, UserName, ActionTimestamp, DetailsJSON) VALUES ('MEMBER_RENEWAL', 'MEMBERS', ?, ?, ?, ?, ?)", "siss", ["MEM-$member_id", $_SESSION['user_id'] ?? 1, $_SESSION['username'] ?? 'Admin', date('Y-m-d H:i:s'), json_encode(['amount' => $sub_fee, 'years' => $renewal_years])]);
            }

            db_commit($conn);
            $msg = "Membership renewed for {$member['MemberName']} until " . date('d-M-Y', strtotime($new_expiry)) . "! Financial voucher posted for ₹" . number_format($sub_fee, 2);
        } catch (Exception $e) {
            db_rollback($conn);
            $error = "Renewal failed: " . $e->getMessage();
        }
    } else {
        $error = "Member not found.";
    }
}

$sql_members = "SELECT m.*, d.GuardianName, d.MemberJoinDate, d.ApprovedDate as ExpiryDate, mt.MemberTypeName, mt.SubscriptionAmount
                FROM MstrMember m
                LEFT JOIN MstrMemberDetails d ON m.MemberNumber = d.MemberNumber
                LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                ORDER BY m.MemberNumber DESC LIMIT 50";

$members_res = db_query($conn, $sql_members);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / MEMBERS / TIERING & RENEWALS</div>
<h1 class="page-header-title"><i class="fa-solid fa-id-card text-primary me-2"></i>Membership Tier Validity & Renewal Management</h1>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Member #</th>
                        <th>Member Name</th>
                        <th>Tier Class</th>
                        <th>Joined Date</th>
                        <th>Expiry Status</th>
                        <th>Annual Fee</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members_res && db_num_rows($members_res) > 0): ?>
                        <?php while ($m = db_fetch_assoc($members_res)): ?>
                            <?php
                                $exp = !empty($m['ExpiryDate']) ? strtotime($m['ExpiryDate']) : strtotime("+1 year", strtotime($m['MemberJoinDate'] ?? 'now'));
                                $is_expired = time() > $exp;
                            ?>
                            <tr class="<?php echo $is_expired ? 'table-warning' : ''; ?>">
                                <td><span class="badge bg-secondary">MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($m['MemberName']); ?></strong></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($m['MemberTypeName'] ?? 'Standard'); ?></span></td>
                                <td><?php echo $m['MemberJoinDate'] ? date('d-M-Y', strtotime($m['MemberJoinDate'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge bg-danger">Expired (<?php echo date('d-M-Y', $exp); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Valid until <?php echo date('d-M-Y', $exp); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo number_format($m['SubscriptionAmount'] ?? 50, 2); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#renewModal<?php echo $m['MemberNumber']; ?>">
                                        <i class="fa-solid fa-rotate-right me-1"></i>Renew
                                    </button>
                                </td>
                            </tr>

                            <!-- Renew Modal -->
                            <div class="modal fade" id="renewModal<?php echo $m['MemberNumber']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="renew.php">
                                            <input type="hidden" name="action" value="renew">
                                            <input type="hidden" name="MemberNumber" value="<?php echo $m['MemberNumber']; ?>">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Renew Membership Subscription</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <p>Renewing membership for <strong><?php echo htmlspecialchars($m['MemberName']); ?></strong>.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small">Renewal Term</label>
                                                    <select name="RenewalYears" class="form-select">
                                                        <option value="1">1 Year (₹<?php echo number_format(($m['SubscriptionAmount'] ?? 50) * 1, 2); ?>)</option>
                                                        <option value="2">2 Years (₹<?php echo number_format(($m['SubscriptionAmount'] ?? 50) * 2, 2); ?>)</option>
                                                        <option value="3">3 Years (₹<?php echo number_format(($m['SubscriptionAmount'] ?? 50) * 3, 2); ?>)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Collect Fee & Renew</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No members recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
