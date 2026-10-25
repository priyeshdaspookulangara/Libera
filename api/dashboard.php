<?php
// api/dashboard.php - Mobile Dashboard & Key Metrics REST API
require_once __DIR__ . '/common.php';

// Protect Endpoint
$user = require_api_auth();

$today = date('Y-m-d');
$fiscal = get_active_fiscal_year($conn);

$res1 = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrBookStock");
$total_books = db_fetch_assoc($res1)['cnt'] ?? 0;

$res2 = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE IssueStatus = 1");
$active_checkouts = db_fetch_assoc($res2)['cnt'] ?? 0;

$res3 = db_query($conn, "SELECT COUNT(*) as cnt FROM TransBookIssue WHERE IssueStatus = 1 AND DueDate < ?", "s", [$today]);
$overdue_count = db_fetch_assoc($res3)['cnt'] ?? 0;

$res4 = db_query($conn, "SELECT COUNT(*) as cnt FROM MstrMember");
$total_members = db_fetch_assoc($res4)['cnt'] ?? 0;

$res5 = db_query($conn, "SELECT COALESCE(SUM(TransactionAmount), 0) as daily_cash FROM AccTransaction WHERE TransactionDate = ?", "s", [$today]);
$daily_cash_flow = floatval(db_fetch_assoc($res5)['daily_cash'] ?? 0.0);

send_json_success([
    'fiscal_year' => $fiscal['YearDescription'] ?? '2024-2025',
    'total_books' => intval($total_books),
    'active_checkouts' => intval($active_checkouts),
    'overdue_count' => intval($overdue_count),
    'total_members' => intval($total_members),
    'daily_cash_flow' => $daily_cash_flow
], "Mobile dashboard metrics retrieved.");
