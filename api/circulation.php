<?php
// api/circulation.php - Mobile Circulation & Overdues REST API
require_once __DIR__ . '/common.php';

// Protect Endpoint
$user = require_api_auth();

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$input = get_json_input();
if (!empty($input['action'])) {
    $action = $input['action'];
}

if ($action === 'issue') {
    $accession = trim($input['accession_number'] ?? $_POST['accession_number'] ?? '');
    $member_id = intval($input['member_id'] ?? $_POST['member_id'] ?? 0);

    if (empty($accession) || $member_id <= 0) {
        send_json_error("Both member_id and accession_number are required.", 400);
    }

    $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
    if (!$book_res || db_num_rows($book_res) === 0) {
        send_json_error("Book with Accession Number '$accession' not found.", 404);
    }

    $book = db_fetch_assoc($book_res);
    if ($book['BookStatus'] != 1) {
        send_json_error("Book '{$book['BookName']}' is currently not available for issue.", 400);
    }

    $mem_res = db_query($conn, "SELECT m.*, mt.IssueBookCount, mt.IssueAllowedDays
                               FROM MstrMember m
                               LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
                               LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
                               WHERE m.MemberNumber = ? LIMIT 1", "i", [$member_id]);
    if (!$mem_res || db_num_rows($mem_res) === 0) {
        send_json_error("Member not found.", 404);
    }

    $member = db_fetch_assoc($mem_res);
    $allowed_days = intval($member['IssueAllowedDays'] ?? 14);
    $issue_date = date('Y-m-d H:i:s');
    $due_date = date('Y-m-d 23:59:59', strtotime("+ $allowed_days days"));

    $res_issue = db_query($conn, "INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, AccessionNumber, MemberNumber, MemberName, IssueStatus) VALUES (?, ?, ?, ?, ?, ?, 1)", "ssssis", [$issue_date, $due_date, $book['BookNumber'], $book['AccessionNumber'], $member_id, $member['MemberName']]);

    if ($res_issue) {
        db_query($conn, "UPDATE MstrBookStock SET BookStatus = 2 WHERE BookNumber = ?", "i", [$book['BookNumber']]);
        send_json_success([
            'book_title' => $book['BookName'],
            'accession_number' => $accession,
            'member_name' => $member['MemberName'],
            'due_date' => $due_date
        ], "Book issued successfully.");
    } else {
        send_json_error("Failed to issue book.", 500);
    }

} elseif ($action === 'return') {
    $accession = trim($input['accession_number'] ?? $_POST['accession_number'] ?? $_GET['accession_number'] ?? '');
    $issue_id = intval($input['issue_id'] ?? $_POST['issue_id'] ?? $_GET['issue_id'] ?? 0);
    $fine_waived = floatval($input['fine_waived'] ?? $_POST['fine_waived'] ?? 0);

    if (empty($accession) && $issue_id <= 0) {
        send_json_error("Either accession_number or issue_id is required.", 400);
    }

    $where = $issue_id > 0 ? "i.IssueID = ?" : "i.AccessionNumber = ?";
    $param = $issue_id > 0 ? $issue_id : $accession;
    $type = $issue_id > 0 ? "i" : "s";

    $sql = "SELECT i.*, b.BookName, b.BookNumber, m.MemberName, mt.IssueFineAmount
            FROM TransBookIssue i
            LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
            LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
            LEFT JOIN ExcelMemberMaster em ON m.MemberNumber = em.MemberNumber
            LEFT JOIN MstrMemberType mt ON COALESCE(em.MemberTypeNumber, 1) = mt.MemberTypeNumber
            WHERE $where AND i.IssueStatus = 1 LIMIT 1";

    $res = db_query($conn, $sql, $type, [$param]);
    if (!$res || db_num_rows($res) === 0) {
        send_json_error("Active issued checkout record not found.", 404);
    }

    $checkout = db_fetch_assoc($res);
    $due = strtotime($checkout['DueDate']);
    $today = strtotime(date('Y-m-d'));
    $overdue_days = ($today > $due) ? ceil(($today - $due) / (60 * 60 * 24)) : 0;
    $fine_rate = floatval($checkout['IssueFineAmount'] ?? 1.0);
    $calculated_fine = $overdue_days * $fine_rate;
    $collected_fine = max(0, $calculated_fine - $fine_waived);
    $return_date = date('Y-m-d H:i:s');

    db_query($conn, "UPDATE TransBookIssue SET ReturnDate = ?, IssueStatus = 2, FineAmount = ?, FineWaived = ?, FineCollected = ? WHERE IssueID = ?", "sdddi", [$return_date, $calculated_fine, $fine_waived, $collected_fine, $checkout['IssueID']]);
    db_query($conn, "UPDATE MstrBookStock SET BookStatus = 1 WHERE AccessionNumber = ?", "s", [$checkout['AccessionNumber']]);

    // Post double-entry accounting voucher if fine collected
    if ($collected_fine > 0) {
        $fiscal = get_active_fiscal_year($conn);
        $year_num = intval($fiscal['YearNumber']);
        $today_date = date('Y-m-d');

        $res_h = db_query($conn, "INSERT INTO TransLedgerHead (TransactionDate, TransTypeNumber, YearNumber, TransactionRemarks, MemberNumber) VALUES (?, 14, ?, ?, ?)", "sisi", [$today_date, $year_num, "API Fine Collection - {$checkout['AccessionNumber']}", $checkout['MemberNumber']]);
        $head_id = $res_h['insert_id'] ?? 1;

        db_query($conn, "INSERT INTO AccTransaction (TransHeadNumber, TransTypeNumber, TransactionDate, LedgerNumberBook, LedgerNumberHead, MemberNumber, TransactionAmount, TransactionRemarks) VALUES (?, 14, ?, 1, 10012, ?, ?, 'API Fine Collection')", "isids", [$head_id, $today_date, $checkout['MemberNumber'], $collected_fine]);
        db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, ?, 0, 'Cash Fine')", "isids", [$head_id, $today_date, $checkout['MemberNumber'], $collected_fine]);
        db_query($conn, "INSERT INTO AccTransDetails (TransHeadNumber, TransTypeNumber, TransactionDate, MemberNumber, LedgerDebitAmount, LedgerCreditAmount, TransactionRemarks) VALUES (?, 14, ?, ?, 0, ?, 'Fine Income')", "isids", [$head_id, $today_date, $checkout['MemberNumber'], $collected_fine]);
    }

    send_json_success([
        'issue_id' => $checkout['IssueID'],
        'book_title' => $checkout['BookName'],
        'accession_number' => $checkout['AccessionNumber'],
        'overdue_days' => $overdue_days,
        'calculated_fine' => $calculated_fine,
        'collected_fine' => $collected_fine
    ], "Book returned successfully.");

} elseif ($action === 'reserve') {
    $accession = trim($input['accession_number'] ?? $_POST['accession_number'] ?? '');
    $member_id = intval($input['member_id'] ?? $_POST['member_id'] ?? 0);
    $remarks = trim($input['remarks'] ?? $_POST['remarks'] ?? 'API Reservation');

    if (empty($accession) || $member_id <= 0) {
        send_json_error("Both member_id and accession_number are required.", 400);
    }

    $book_res = db_query($conn, "SELECT * FROM MstrBookStock WHERE AccessionNumber = ? LIMIT 1", "s", [$accession]);
    if (!$book_res || db_num_rows($book_res) === 0) {
        send_json_error("Book with Accession Number '$accession' not found.", 404);
    }

    $book = db_fetch_assoc($book_res);
    $book_num = $book['BookNumber'];

    $pos_res = db_query($conn, "SELECT COUNT(*) as queue_cnt FROM BookReservations WHERE BookNumber = ? AND NotificationStatus IN ('Pending', 'Notified')", "i", [$book_num]);
    $queue_pos = (db_fetch_assoc($pos_res)['queue_cnt'] ?? 0) + 1;

    $res_date = date('Y-m-d H:i:s');
    $sql_ins = "INSERT INTO BookReservations (BookNumber, AccessionNumber, MemberNumber, ReservationDate, NotificationStatus, QueuePosition, Remarks) VALUES (?, ?, ?, ?, 'Pending', ?, ?)";
    $res_ins = db_query($conn, $sql_ins, "isisis", [$book_num, $accession, $member_id, $res_date, $queue_pos, $remarks]);

    if ($res_ins) {
        send_json_success([
            'book_title' => $book['BookName'],
            'accession_number' => $accession,
            'queue_position' => $queue_pos
        ], "Book reservation queued successfully.");
    } else {
        send_json_error("Failed to queue reservation.", 500);
    }

} elseif ($action === 'checkouts' || $action === 'overdues') {
    $sql = "SELECT i.*, b.BookName, m.MemberName, m.PhoneHome
            FROM TransBookIssue i
            LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber OR i.BookNumber = CAST(b.BookNumber AS CHAR)
            LEFT JOIN MstrMember m ON i.MemberNumber = m.MemberNumber
            WHERE i.IssueStatus = 1
            ORDER BY i.DueDate ASC";
    $res = db_query($conn, $sql);
    $checkouts = [];
    if ($res && db_num_rows($res) > 0) {
        while ($row = db_fetch_assoc($res)) {
            $due = strtotime($row['DueDate']);
            $today = strtotime(date('Y-m-d'));
            $overdue_days = ($today > $due) ? ceil(($today - $due) / (60 * 60 * 24)) : 0;
            $row['overdue_days'] = $overdue_days;

            if ($action === 'overdues' && $overdue_days == 0) {
                continue;
            }
            $checkouts[] = $row;
        }
    }
    send_json_success($checkouts, "Active circulation checkouts retrieved.");

} else {
    send_json_error("Invalid circulation action requested.", 400);
}
