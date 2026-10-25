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

} elseif ($action === 'checkouts' || $action === 'overdues') {
    $sql = "SELECT i.*, b.BookName, m.MemberName, m.PhoneHome
            FROM TransBookIssue i
            LEFT JOIN MstrBookStock b ON i.AccessionNumber = b.AccessionNumber
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
