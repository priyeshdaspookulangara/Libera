<?php
/**
 * ajax_search_books.php
 * Lightweight AJAX search endpoint using MySQLi prepared statements.
 *
 * Supports:
 *   - Manglish/English input (e.g. "indu", "chandu", "basheer", "sherlock")
 *   - Malayalam Unicode input (e.g. "ഇന്ദു", "ബഷീർ")
 *
 * Returns JSON containing matching books with:
 *   - book_number
 *   - title_malayalam
 *   - author_malayalam
 *   - accession_number
 *   - book_code
 *   - title_search_key
 *   - author_search_key
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/manglish_helper.php';

// Accept search term from GET or POST parameter 'q'
$raw_query = trim($_REQUEST['q'] ?? '');

if ($raw_query === '') {
    echo json_encode([
        'success' => true,
        'count' => 0,
        'results' => []
    ]);
    exit;
}

// Normalize search query
// If user types in Malayalam, transliterate to Manglish for matching against keys
$manglish_query = generate_manglish_key($raw_query);
// Strip special characters for safe prefix searching
$manglish_clean = preg_replace('/[^a-z0-9\s]/i', '', $manglish_query);
$manglish_clean = strtolower(trim($manglish_clean));

// Formulate search prefixes
$prefix = $manglish_clean . '%';
$anywhere = '%' . $manglish_clean . '%';
$raw_anywhere = '%' . $raw_query . '%';

// Query with prepared statement
// Matches:
// 1. Manglish key prefix or word substring (title_search_key / author_search_key)
// 2. Exact/partial Malayalam title (b.BookName) or author (a.AuthorName)
// 3. Accession Number / Barcode (b.AccessionNumber, b.BookCode)
$sql = "SELECT b.BookNumber,
               b.BookName AS title_malayalam,
               COALESCE(a.AuthorName, '') AS author_malayalam,
               b.AccessionNumber AS accession_number,
               b.BookCode AS book_code,
               b.title_search_key,
               b.author_search_key,
               b.BookStatus,
               b.BookPrice
        FROM MstrBookStock b
        LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
        WHERE b.title_search_key LIKE ?
           OR b.author_search_key LIKE ?
           OR b.title_search_key LIKE ?
           OR b.author_search_key LIKE ?
           OR b.BookName LIKE ?
           OR a.AuthorName LIKE ?
           OR b.AccessionNumber LIKE ?
        ORDER BY
           CASE
               WHEN b.title_search_key LIKE ? THEN 1
               WHEN b.author_search_key LIKE ? THEN 2
               ELSE 3
           END,
           b.BookNumber ASC
        LIMIT 25";

$types = "sssssssss";
$params = [
    $prefix,       // title starts with prefix
    $prefix,       // author starts with prefix
    $anywhere,     // title contains anywhere
    $anywhere,     // author contains anywhere
    $raw_anywhere, // Malayalam title contains
    $raw_anywhere, // Malayalam author contains
    $prefix,       // accession number
    $prefix,       // order case 1
    $prefix        // order case 2
];

$res = db_query($conn, $sql, $types, $params);

$results = [];
if ($res) {
    while ($row = db_fetch_assoc($res)) {
        $results[] = [
            'book_number'        => (int)$row['BookNumber'],
            'title_malayalam'    => $row['title_malayalam'],
            'author_malayalam'   => $row['author_malayalam'],
            'accession_number'   => $row['accession_number'],
            'book_code'          => $row['book_code'],
            'title_search_key'   => $row['title_search_key'],
            'author_search_key'  => $row['author_search_key'],
            'status'             => (int)$row['BookStatus'],
            'price'              => (float)$row['BookPrice']
        ];
    }
}

echo json_encode([
    'success' => true,
    'query'   => $raw_query,
    'manglish_key' => $manglish_clean,
    'count'   => count($results),
    'results' => $results
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
