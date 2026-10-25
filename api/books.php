<?php
// api/books.php - Mobile Catalog & Book Stock REST API Endpoint
require_once __DIR__ . '/common.php';

// Protect Endpoint
$user = require_api_auth();

$search = trim($_GET['search'] ?? '');
$book_id = intval($_GET['id'] ?? 0);

if ($book_id > 0) {
    $sql = "SELECT b.*, a.AuthorName, p.PublisherName, c.CategoryName, lang.LanguageName
            FROM MstrBookStock b
            LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
            LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
            LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
            LEFT JOIN MstrLanguage lang ON b.LanguageNumber = lang.LanguageNumber
            WHERE b.BookNumber = ? LIMIT 1";
    $res = db_query($conn, $sql, "i", [$book_id]);
    if ($res && db_num_rows($res) > 0) {
        send_json_success(db_fetch_assoc($res));
    } else {
        send_json_error("Book not found.", 404);
    }
}

$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE b.BookName LIKE ? OR b.AccessionNumber LIKE ? OR b.ISBNumber LIKE ? OR a.AuthorName LIKE ? ";
    $sp = "%$search%";
    $params = [$sp, $sp, $sp, $sp];
    $types = "ssss";
}

$sql_books = "SELECT b.BookNumber, b.BookName, b.AccessionNumber, b.ISBNumber, b.BookPrice, b.BookStatus,
                     a.AuthorName, p.PublisherName, c.CategoryName
              FROM MstrBookStock b
              LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
              LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
              LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
              $where_clause
              ORDER BY b.BookNumber DESC LIMIT 50";

$res = db_query($conn, $sql_books, $types, $params);
$books = [];
if ($res && db_num_rows($res) > 0) {
    while ($row = db_fetch_assoc($res)) {
        $books[] = $row;
    }
}

send_json_success($books, "Book stock records retrieved.");
