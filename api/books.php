<?php
// api/books.php - Mobile Catalog & Book Stock REST API Endpoint
require_once __DIR__ . '/common.php';

// Protect Endpoint
$user = require_api_auth();

$search = trim($_GET['search'] ?? $_GET['q'] ?? '');
$book_id = intval($_GET['id'] ?? $_GET['BookNumber'] ?? 0);
$book_name = trim($_GET['BookName'] ?? '');
$book_remarks = trim($_GET['BookRemarks'] ?? '');
$author_name = trim($_GET['AutherName'] ?? '');
$book_class = trim($_GET['BookClass'] ?? '');
$book_code = trim($_GET['BookCode'] ?? '');
$call_num = trim($_GET['CallNumber'] ?? '');
$book_price = trim($_GET['BookPrice'] ?? '');

if ($book_id > 0 && empty($search) && empty($book_name)) {
    $sql = "SELECT b.*, COALESCE(a.AuthorName, 'Unknown') as AutherName, COALESCE(a.AuthorName, 'Unknown') as AuthorName, p.PublisherName, c.CategoryName, lang.LanguageName, cl.ClassName as BookClass
            FROM MstrBookStock b
            LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
            LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
            LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
            LEFT JOIN MstrClassification cl ON b.ClassNumber = cl.ClassNumber
            LEFT JOIN MstrLanguage lang ON b.LanguageNumber = lang.LanguageNumber
            WHERE b.BookNumber = ? LIMIT 1";
    $res = db_query($conn, $sql, "i", [$book_id]);
    if ($res && db_num_rows($res) > 0) {
        send_json_success(db_fetch_assoc($res));
    } else {
        send_json_error("Book not found.", 404);
    }
}

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where[] = "(b.BookName LIKE ? OR b.AccessionNumber LIKE ? OR b.ISBNumber LIKE ? OR a.AuthorName LIKE ? OR b.BookCode LIKE ? OR b.CallNumber LIKE ? OR b.BookRemarks LIKE ?)";
    $sp = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $sp;
        $types .= "s";
    }
}

if ($book_id > 0) {
    $where[] = "b.BookNumber = ?";
    $params[] = $book_id;
    $types .= "i";
}

if (!empty($book_name)) {
    $where[] = "b.BookName LIKE ?";
    $params[] = "%$book_name%";
    $types .= "s";
}

if (!empty($book_remarks)) {
    $where[] = "b.BookRemarks LIKE ?";
    $params[] = "%$book_remarks%";
    $types .= "s";
}

if (!empty($author_name)) {
    $where[] = "a.AuthorName LIKE ?";
    $params[] = "%$author_name%";
    $types .= "s";
}

if (!empty($book_class)) {
    $where[] = "(cl.ClassName LIKE ? OR cl.ClassCode LIKE ? OR c.CategoryName LIKE ?)";
    $params[] = "%$book_class%";
    $params[] = "%$book_class%";
    $params[] = "%$book_class%";
    $types .= "sss";
}

if (!empty($book_code)) {
    $where[] = "b.BookCode LIKE ?";
    $params[] = "%$book_code%";
    $types .= "s";
}

if (!empty($call_num)) {
    $where[] = "b.CallNumber LIKE ?";
    $params[] = "%$call_num%";
    $types .= "s";
}

if (!empty($book_price) && is_numeric($book_price)) {
    $where[] = "b.BookPrice <= ?";
    $params[] = floatval($book_price);
    $types .= "d";
}

$sql_books = "SELECT b.BookNumber, b.BookName, b.AccessionNumber, b.ISBNumber, b.BookCode, b.CallNumber, b.BookRemarks, b.BookPrice, b.BookStatus,
                     COALESCE(a.AuthorName, 'Unknown') as AutherName, COALESCE(a.AuthorName, 'Unknown') as AuthorName, p.PublisherName, c.CategoryName, COALESCE(cl.ClassName, 'General') as BookClass
              FROM MstrBookStock b
              LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
              LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
              LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
              LEFT JOIN MstrClassification cl ON b.ClassNumber = cl.ClassNumber
              WHERE " . implode(" AND ", $where) . "
              ORDER BY b.BookNumber DESC LIMIT 50";

$res = db_query($conn, $sql_books, $types, $params);
$books = [];
if ($res && db_num_rows($res) > 0) {
    while ($row = db_fetch_assoc($res)) {
        $books[] = $row;
    }
}

send_json_success($books, "Book stock records retrieved.");
