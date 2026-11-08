<?php
/**
 * ajax_search_books.php
 * Lightweight AJAX search endpoint using MySQLi prepared statements.
 *
 * Supports:
 *   - Manglish/English input (e.g. "mish", "indu", "chandu", "basheer", "sherlock")
 *   - Malayalam Unicode input (e.g. "മിശ്ര", "ഇന്ദു", "ബഷീർ")
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

// Generate phonetic search variations (e.g. "mish" -> ["mish", "mis"])
$variations = get_manglish_search_variations($raw_query);
$raw_anywhere = '%' . $raw_query . '%';

$where_clauses = [
    "b.BookName LIKE ?",
    "a.AuthorName LIKE ?",
    "b.AccessionNumber LIKE ?",
    "b.BookCode LIKE ?"
];
$params = [$raw_anywhere, $raw_anywhere, '%' . $raw_query . '%', '%' . $raw_query . '%'];
$types = "ssss";

foreach ($variations as $var) {
    $where_clauses[] = "b.title_search_key LIKE ?";
    $where_clauses[] = "b.author_search_key LIKE ?";
    $params[] = '%' . $var . '%';
    $params[] = '%' . $var . '%';
    $types .= "ss";
}

$first_var = !empty($variations[0]) ? $variations[0] : $raw_query;
$first_prefix = $first_var . '%';
$params[] = $first_prefix;
$params[] = $first_prefix;
$types .= "ss";

$where_sql = implode(" OR ", $where_clauses);

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
        WHERE $where_sql
        ORDER BY
           CASE
               WHEN b.title_search_key LIKE ? THEN 1
               WHEN b.author_search_key LIKE ? THEN 2
               ELSE 3
           END,
           b.BookNumber ASC
        LIMIT 25";

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

// Fallback to legacy ExcelBookMaster if MstrBookStock has 0 results
if (empty($results)) {
    $excel_where = [
        "e.BookName LIKE ?",
        "e.AutherName LIKE ?",
        "e.CallNumber LIKE ?",
        "e.BookCode LIKE ?"
    ];
    $e_params = [$raw_anywhere, $raw_anywhere, '%' . $raw_query . '%', '%' . $raw_query . '%'];
    $e_types = "ssss";

    foreach ($variations as $var) {
        $excel_where[] = "e.title_search_key LIKE ?";
        $excel_where[] = "e.author_search_key LIKE ?";
        $e_params[] = '%' . $var . '%';
        $e_params[] = '%' . $var . '%';
        $e_types .= "ss";
    }

    $e_where_sql = implode(" OR ", $excel_where);
    $excel_sql = "SELECT e.BookNumber,
                         e.BookName AS title_malayalam,
                         COALESCE(e.AutherName, '') AS author_malayalam,
                         e.CallNumber AS accession_number,
                         e.BookCode AS book_code,
                         e.title_search_key,
                         e.author_search_key,
                         1 AS BookStatus,
                         e.BookPrice
                  FROM ExcelBookMaster e
                  WHERE $e_where_sql
                  ORDER BY e.BookNumber ASC
                  LIMIT 25";
    $e_res = db_query($conn, $excel_sql, $e_types, $e_params);
    if ($e_res) {
        while ($er = db_fetch_assoc($e_res)) {
            $results[] = [
                'book_number'        => (int)$er['BookNumber'],
                'title_malayalam'    => $er['title_malayalam'],
                'author_malayalam'   => $er['author_malayalam'],
                'accession_number'   => $er['accession_number'] ?? ('B' . $er['BookNumber']),
                'book_code'          => $er['book_code'],
                'title_search_key'   => $er['title_search_key'],
                'author_search_key'  => $er['author_search_key'],
                'status'             => 1,
                'price'              => (float)$er['BookPrice']
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'query'   => $raw_query,
    'variations' => $variations,
    'count'   => count($results),
    'results' => $results
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
