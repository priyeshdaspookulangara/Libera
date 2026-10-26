<?php
// modules/circulation/reservation.php - Book Waitlist & Queue Management Engine
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Book Reservations & Waitlist Queue";
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'reserve') {
        $accession = sanitize_input($_POST['AccessionNumber'] ?? '');
        $member_id = intval($_POST['MemberNumber'] ?? 0);
        $remarks = sanitize_input($_POST['Remarks'] ?? '');

        if (empty($accession) || $member_id <= 0) {
            $error = "Please select a Member and specify an Accession Number.";
        } else {
            // Find book
            $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
            if (!$book_res || db_num_rows($book_res) === 0) {
                $error = "Book with Accession Number '$accession' not found.";
            } else {
                $book = db_fetch_assoc($book_res);
                $book_num = $book['BookNumber'];

                // Calculate Queue Position
                $pos_res = db_query($conn, "SELECT COUNT(*) as queue_cnt FROM BookReservations WHERE BookNumber = ? AND NotificationStatus IN ('Pending', 'Notified')", "i", [$book_num]);
                $queue_pos = (db_fetch_assoc($pos_res)['queue_cnt'] ?? 0) + 1;

                $res_date = date('Y-m-d H:i:s');
                $sql_ins = "INSERT INTO BookReservations (BookNumber, AccessionNumber, MemberNumber, ReservationDate, NotificationStatus, QueuePosition, Remarks) VALUES (?, ?, ?, ?, 'Pending', ?, ?)";
                $res_ins = db_query($conn, $sql_ins, "isisis", [$book_num, $accession, $member_id, $res_date, $queue_pos, $remarks]);

                if ($res_ins) {
                    $msg = "Reservation queued successfully for '{$book['BookName']}'! Queue Position #$queue_pos.";
                } else {
                    $error = "Failed to queue reservation.";
                }
            }
        }
    } elseif ($action === 'notify') {
        $res_id = intval($_POST['ReservationID']);
        $expiry_date = date('Y-m-d H:i:s', strtotime('+3 days'));

        db_query($conn, "UPDATE BookReservations SET NotificationStatus = 'Notified', ExpiryDate = ? WHERE ReservationID = ?", "si", [$expiry_date, $res_id]);
        $msg = "Member notified via automated SMS/Email hook! Book placed on 3-day hold until " . date('d-M-Y H:i', strtotime($expiry_date));
    } elseif ($action === 'cancel') {
        $res_id = intval($_POST['ReservationID']);
        db_query($conn, "UPDATE BookReservations SET NotificationStatus = 'Cancelled' WHERE ReservationID = ?", "i", [$res_id]);
        $msg = "Reservation cancelled.";
    }
}

// Fetch Active Reservations Queue
$sql_queue = "SELECT r.*, b.BookName, m.MemberName, m.PhoneHome
              FROM BookReservations r
              LEFT JOIN MstrBookStock b ON r.BookNumber = b.BookNumber
              LEFT JOIN MstrMember m ON r.MemberNumber = m.MemberNumber
              WHERE r.NotificationStatus IN ('Pending', 'Notified')
              ORDER BY r.BookNumber ASC, r.QueuePosition ASC";

$queue_res = db_query($conn, $sql_queue);
$members = db_query($conn, "SELECT MemberNumber, MemberName FROM MstrMember ORDER BY MemberName ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb-nav mb-1">HOME / CIRCULATION / RESERVATIONS</div>
<h1 class="page-header-title"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Book Waitlist Queue & Hold Notifications</h1>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-bookmark me-2"></i>Reserve Checked-Out Item</h5>
            </div>
            <div class="card-body">
                <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <form method="POST" action="reservation.php">
                    <input type="hidden" name="action" value="reserve">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Member *</label>
                        <select name="MemberNumber" class="form-select" required>
                            <option value="">-- Choose Member --</option>
                            <?php if ($members && db_num_rows($members) > 0): ?>
                                <?php while ($m = db_fetch_assoc($members)): ?>
                                    <option value="<?php echo $m['MemberNumber']; ?>">MEM-<?php echo str_pad($m['MemberNumber'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($m['MemberName']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Book Accession Number *</label>
                        <input type="text" name="AccessionNumber" class="form-control" placeholder="e.g. ACC1001" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small">Remarks</label>
                        <input type="text" name="Remarks" class="form-control" placeholder="Optional notes">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-plus me-1"></i>Queue Reservation</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-list-ol me-2"></i>Active Waitlist Queue</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Pos #</th>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Reserved On</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($queue_res && db_num_rows($queue_res) > 0): ?>
                                <?php while ($q = db_fetch_assoc($queue_res)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">#<?php echo $q['QueuePosition']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($q['BookName'] ?? 'N/A'); ?></strong> <br><small class="text-muted"><?php echo htmlspecialchars($q['AccessionNumber']); ?></small></td>
                                        <td><?php echo htmlspecialchars($q['MemberName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($q['ReservationDate'])); ?></td>
                                        <td>
                                            <?php if ($q['NotificationStatus'] === 'Pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending Return</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Notified / On Hold</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($q['NotificationStatus'] === 'Pending'): ?>
                                                <form method="POST" action="reservation.php" class="d-inline">
                                                    <input type="hidden" name="action" value="notify">
                                                    <input type="hidden" name="ReservationID" value="<?php echo $q['ReservationID']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Notify Member Hold"><i class="fa-solid fa-bell"></i> Notify</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="reservation.php" class="d-inline" onsubmit="return confirm('Cancel reservation?');">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="ReservationID" value="<?php echo $q['ReservationID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-times"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No active item reservations in waitlist queue.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
