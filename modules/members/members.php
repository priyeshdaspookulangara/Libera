<?php
// modules/members/members.php - Member Master Directory & Subscription Management
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Member Management";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        $name = sanitize_input($_POST['MemberName'] ?? '');
        $phone = sanitize_input($_POST['PhoneHome'] ?? '');
        $house = sanitize_input($_POST['HouseName'] ?? '');
        $sex = sanitize_input($_POST['StudentSex'] ?? 'Male');
        $dob = sanitize_input($_POST['MemBirthDate'] ?? '');
        $age = !empty($_POST['MemberAge']) ? intval($_POST['MemberAge']) : null;
        $blood = sanitize_input($_POST['BloodGroupStr'] ?? '');
        $aadhar = sanitize_input($_POST['AadharNumber'] ?? '');
        $email = sanitize_input($_POST['MemberEmail'] ?? '');
        $term_date = !empty($_POST['TerminationDate']) ? sanitize_input($_POST['TerminationDate']) : null;
        $guardian = sanitize_input($_POST['GuardianName'] ?? '');
        $remarks = sanitize_input($_POST['MemberRemarks'] ?? '');
        $member_type_id = intval($_POST['MemberTypeNumber'] ?? 1);

        if (empty($name)) {
            $error = "Member Name is required.";
        } else {
            if ($action === 'add') {
                // Fetch fees from member type
                $mtype_res = db_query($conn, "SELECT * FROM MstrMemberType WHERE MemberTypeNumber = ? LIMIT 1", "i", [$member_type_id]);
                $mtype = ($mtype_res && db_num_rows($mtype_res) > 0) ? db_fetch_assoc($mtype_res) : ['RegistrationFees' => 50, 'MembershipAmount' => 100];
                $reg_fee = floatval($mtype['RegistrationFees'] ?? 50.0);
                $sub_fee = floatval($mtype['MembershipAmount'] ?? 100.0);
                $total_collected = $reg_fee + $sub_fee;

                // Insert into MstrMember
                $sql1 = "INSERT INTO MstrMember (MemberName, PhoneHome, HouseName, StudentSex, MemBirthDate, MemberRemarks) VALUES (?, ?, ?, ?, ?, ?)";
                $res1 = db_query($conn, $sql1, "ssssss", [$name, $phone, $house, $sex, $dob, $remarks]);

                if ($res1 && isset($res1['insert_id'])) {
                    $mem_id = $res1['insert_id'];

                    // Insert Details including optional attributes
                    $today = date('Y-m-d');
                    $sql2 = "INSERT INTO MstrMemberDetails (MemberNumber, GuardianName, RegistrationFees, MemberJoinDate, ApprovedDate, MemberAge, BloodGroupStr, AadharNumber, MemberEmail, TerminationDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    db_query($conn, $sql2, "isdssissss", [$mem_id, $guardian, $reg_fee, $today, $today, $age, $blood, $aadhar, $email, $term_date]);

                    // Automatically generate balanced Double-Entry Accounting Voucher for Registration & Subscription Fee Collection
                    if ($total_collected > 0) {
                        $fiscal = get_active_fiscal_year($conn);
                        $year_num = intval($fiscal['YearNumber']);
                        $trans_date = date('Y-m-d');

                        // Insert TransLedgerHead
                        $res_head = db_query($conn, "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks, MemberNumber) VALUES (?, 14, ?, ?, ?)", "sisi", [$trans_date, $year_num, "Member Reg Fee - " . $name, $mem_id]);
                        $head_id = $res_head['insert_id'] ?? 1;

                        if ($reg_fee > 0) {
                            db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10014, ?, ?, ?)", "isids", [$head_id, $trans_date, $mem_id, $reg_fee, "Reg Fee"]);
                            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, ?)", "isids", [$head_id, $trans_date, $mem_id, $reg_fee, "Cash Reg Fee"]);
                            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, ?)", "isids", [$head_id, $trans_date, $mem_id, $reg_fee, "Reg Fee Income"]);
                        }

                        if ($sub_fee > 0) {
                            db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10013, ?, ?, ?)", "isids", [$head_id, $trans_date, $mem_id, $sub_fee, "Subscription Fee"]);
                            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, ?)", "isids", [$head_id, $trans_date, $mem_id, $sub_fee, "Cash Subscription"]);
                            db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, ?)", "isids", [$head_id, $trans_date, $mem_id, $sub_fee, "Subscription Income"]);
                        }
                    }

                    $msg = "Member enrolled successfully! Double-entry receipt voucher posted for ₹" . number_format($total_collected, 2);
                } else {
                    $error = "Failed to create member record.";
                }
            } elseif ($action === 'edit') {
                $mem_id = intval($_POST['MemberNumber']);
                $sql1 = "UPDATE MstrMember SET MemberName = ?, PhoneHome = ?, HouseName = ?, StudentSex = ?, MemBirthDate = ?, MemberRemarks = ? WHERE MemberNumber = ?";
                db_query($conn, $sql1, "ssssssi", [$name, $phone, $house, $sex, $dob, $remarks, $mem_id]);

                $sql2 = "UPDATE MstrMemberDetails SET GuardianName = ?, MemberAge = ?, BloodGroupStr = ?, AadharNumber = ?, MemberEmail = ?, TerminationDate = ? WHERE MemberNumber = ?";
                db_query($conn, $sql2, "sissssi", [$guardian, $age, $blood, $aadhar, $email, $term_date, $mem_id]);

                $msg = "Member details updated successfully.";
            }
        }
    }
}

// Search
$search = sanitize_input($_GET['search'] ?? '');
$where = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where = " WHERE m.MemberName LIKE ? OR m.PhoneHome LIKE ? OR m.HouseName LIKE ? OR d.AadharNumber LIKE ? OR d.MemberEmail LIKE ? ";
    $sp = "%$search%";
    $params = [$sp, $sp, $sp, $sp, $sp];
    $types = "sssss";
}

$sql_members = "SELECT m.*, d.GuardianName, d.RegistrationFees, d.MemberJoinDate, d.MemberAge, d.BloodGroupStr, d.AadharNumber, d.MemberEmail, d.TerminationDate
                FROM MstrMember m
                LEFT JOIN MstrMemberDetails d ON m.MemberNumber = d.MemberNumber
                $where
                ORDER BY m.MemberNumber DESC LIMIT 100";

$members_res = db_query($conn, $sql_members, $types, $params);
$member_types = db_query($conn, "SELECT * FROM MstrMemberType ORDER BY MemberTypeName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-users text-primary me-2"></i>Member Profiles & Directory</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
        <i class="fa-solid fa-user-plus me-1"></i>Enroll New Member
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="members.php" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search by Member Name, Phone, Aadhaar, Email, or Address..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-search me-1"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Member #</th>
                        <th>Member Name</th>
                        <th>Phone / Email</th>
                        <th>Age / Blood</th>
                        <th>Aadhaar #</th>
                        <th>Joined Date</th>
                        <th>Termination</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members_res && db_num_rows($members_res) > 0): ?>
                        <?php while ($m = db_fetch_assoc($members_res)): ?>
                            <tr>
                                <td><span class="badge bg-primary">MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['MemberName']); ?></strong>
                                    <?php if (!empty($m['GuardianName'])): ?>
                                        <br><small class="text-muted">C/o: <?php echo htmlspecialchars($m['GuardianName']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($m['PhoneHome'] ?? 'N/A'); ?>
                                    <?php if (!empty($m['MemberEmail'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($m['MemberEmail']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($m['MemberAge']) ? $m['MemberAge'] . ' yrs' : 'N/A'; ?>
                                    <?php if (!empty($m['BloodGroupStr'])): ?>
                                        <span class="badge bg-danger ms-1"><?php echo htmlspecialchars($m['BloodGroupStr']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($m['AadharNumber'] ?? 'N/A'); ?></code></td>
                                <td><?php echo $m['MemberJoinDate'] ? date('d-M-Y', strtotime($m['MemberJoinDate'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if (!empty($m['TerminationDate'])): ?>
                                        <span class="badge bg-secondary"><?php echo date('d-M-Y', strtotime($m['TerminationDate'])); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editMemberModal<?php echo $m['MemberNumber']; ?>">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editMemberModal<?php echo $m['MemberNumber']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" action="members.php">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="MemberNumber" value="<?php echo $m['MemberNumber']; ?>">
                                            <div class="modal-header bg-info text-white">
                                                <h5 class="modal-title">Edit Member Profile</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Member Full Name *</label>
                                                    <input type="text" name="MemberName" class="form-control" required value="<?php echo htmlspecialchars($m['MemberName']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Guardian Name</label>
                                                    <input type="text" name="GuardianName" class="form-control" value="<?php echo htmlspecialchars($m['GuardianName'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Phone Number</label>
                                                    <input type="text" name="PhoneHome" class="form-control" value="<?php echo htmlspecialchars($m['PhoneHome'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Email Address</label>
                                                    <input type="email" name="MemberEmail" class="form-control" value="<?php echo htmlspecialchars($m['MemberEmail'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Aadhaar Number</label>
                                                    <input type="text" name="AadharNumber" class="form-control" placeholder="12-digit UIDAI" value="<?php echo htmlspecialchars($m['AadharNumber'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold small">Gender</label>
                                                    <select name="StudentSex" class="form-select">
                                                        <option value="Male" <?php echo ($m['StudentSex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                        <option value="Female" <?php echo ($m['StudentSex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                        <option value="Other" <?php echo ($m['StudentSex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold small">Date of Birth</label>
                                                    <input type="date" name="MemBirthDate" class="form-control" value="<?php echo $m['MemBirthDate'] ? date('Y-m-d', strtotime($m['MemBirthDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold small">Age</label>
                                                    <input type="number" name="MemberAge" class="form-control" value="<?php echo htmlspecialchars($m['MemberAge'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold small">Blood Group</label>
                                                    <select name="BloodGroupStr" class="form-select">
                                                        <option value="">-- None --</option>
                                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                                            <option value="<?php echo $bg; ?>" <?php echo (($m['BloodGroupStr'] ?? '') === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Termination Date</label>
                                                    <input type="date" name="TerminationDate" class="form-control" value="<?php echo !empty($m['TerminationDate']) ? date('Y-m-d', strtotime($m['TerminationDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">House Name / Address</label>
                                                    <input type="text" name="HouseName" class="form-control" value="<?php echo htmlspecialchars($m['HouseName'] ?? ''); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label fw-bold small">Remarks</label>
                                                    <input type="text" name="MemberRemarks" class="form-control" value="<?php echo htmlspecialchars($m['MemberRemarks'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-info text-white">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No member profiles found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enroll Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="members.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Enroll New Member</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Member Full Name *</label>
                        <input type="text" name="MemberName" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Membership Category / Class *</label>
                        <select name="MemberTypeNumber" class="form-select" required>
                            <?php db_data_seek($member_types, 0); ?>
                            <?php while ($mt = db_fetch_assoc($member_types)): ?>
                                <option value="<?php echo $mt['MemberTypeNumber']; ?>">
                                    <?php echo htmlspecialchars($mt['MemberTypeName']); ?> (Fee: ₹<?php echo ($mt['ApplicationFee'] + $mt['MembershipAmount']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Guardian Name</label>
                        <input type="text" name="GuardianName" class="form-control" placeholder="Parent or Guardian">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Phone Number</label>
                        <input type="text" name="PhoneHome" class="form-control" placeholder="10-digit mobile">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" name="MemberEmail" class="form-control" placeholder="e.g. member@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Aadhaar Card Number</label>
                        <input type="text" name="AadharNumber" class="form-control" placeholder="12-digit Aadhaar UIDAI">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Gender</label>
                        <select name="StudentSex" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Date of Birth</label>
                        <input type="date" name="MemBirthDate" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Age</label>
                        <input type="number" name="MemberAge" class="form-control" placeholder="Age in years">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Blood Group</label>
                        <select name="BloodGroupStr" class="form-select">
                            <option value="">-- None --</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Termination Date (If applicable)</label>
                        <input type="date" name="TerminationDate" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">House Name / Address</label>
                        <input type="text" name="HouseName" class="form-control" placeholder="Street / House Address">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Remarks</label>
                        <input type="text" name="MemberRemarks" class="form-control" placeholder="Optional remarks">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enroll & Collect Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
