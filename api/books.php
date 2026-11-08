<?php
// api/books.php - Mobile Catalog & Book Stock REST API Endpoint
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../manglish_helper.php';

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
    // Generate phonetic Manglish variations for matching
    $search_variations = get_manglish_search_variations($search);
    $search_clauses = [
        "b.BookName LIKE ?",
        "b.AccessionNumber LIKE ?",
        "b.ISBNumber LIKE ?",
        "a.AuthorName LIKE ?",
        "b.BookCode LIKE ?",
        "b.CallNumber LIKE ?",
        "b.BookRemarks LIKE ?"
    ];
    $sp = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $sp;
        $types .= "s";
    }

    foreach ($search_variations as $var) {
        $search_clauses[] = "b.title_search_key LIKE ?";
        $search_clauses[] = "b.author_search_key LIKE ?";
        $params[] = "%" . $var . "%";
        $params[] = "%" . $var . "%";
        $types .= "ss";
    }

    $where[] = "(" . implode(" OR ", $search_clauses) . ")";
}

if ($book_id > 0) {
    $where[] = "b.BookNumber = ?";
    $params[] = $book_id;
    $types .= "i";
}

if (!empty($book_name)) {
    $manglish_bn = generate_manglish_key($book_name);
    $where[] = "(b.BookName LIKE ? OR b.title_search_key LIKE ? OR b.title_search_key LIKE ?)";
    $params[] = "%$book_name%";
    $params[] = $manglish_bn . "%";
    $params[] = "%" . $manglish_bn . "%";
    $types .= "sss";
}

if (!empty($book_remarks)) {
    $where[] = "b.BookRemarks LIKE ?";
    $params[] = "%$book_remarks%";
    $types .= "s";
}

if (!empty($author_name)) {
    $manglish_an = generate_manglish_key($author_name);
    $where[] = "(a.AuthorName LIKE ? OR b.author_search_key LIKE ? OR b.author_search_key LIKE ?)";
    $params[] = "%$author_name%";
    $params[] = $manglish_an . "%";
    $params[] = "%" . $manglish_an . "%";
    $types .= "sss";
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
                     b.title_search_key, b.author_search_key,
                     COALESCE(a.AuthorName, 'Unknown') as AutherName, COALESCE(a.AuthorName, 'Unknown') as AuthorName, p.PublisherName, c.CategoryName, COALESCE(cl.ClassName, 'General') as BookClass
              FROM MstrBookStock b
              LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
              LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
              LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
              LEFT JOIN MstrClassification cl ON b.ClassNumber = cl.ClassNumber
              WHERE " . implode(" AND ", $where) . "
              ORDER BY
                 CASE
                    WHEN b.title_search_key LIKE ? THEN 1
                    WHEN b.author_search_key LIKE ? THEN 2
                    ELSE 3
                 END,
                 b.BookNumber DESC LIMIT 50";

if (!empty($search)) {
    $first_v = !empty($search_variations[0]) ? $search_variations[0] : $search;
    $types .= "ss";
    $params[] = $first_v . "%";
    $params[] = $first_v . "%";
} else {
    $types .= "ss";
    $params[] = "%";
    $params[] = "%";
}

$res = db_query($conn, $sql_books, $types, $params);
$books = [];
if ($res && db_num_rows($res) > 0) {
    while ($row = db_fetch_assoc($res)) {
        $books[] = $row;
    }
}

// Fallback search in ExcelBookMaster if MstrBookStock had no matches
if (empty($books) && !empty($search)) {
    $e_clauses = ["e.BookName LIKE ?", "e.AutherName LIKE ?", "e.CallNumber LIKE ?", "e.BookCode LIKE ?"];
    $e_params = ["%$search%", "%$search%", "%$search%", "%$search%"];
    $e_types = "ssss";

    foreach ($search_variations as $var) {
        $e_clauses[] = "e.title_search_key LIKE ?";
        $e_clauses[] = "e.author_search_key LIKE ?";
        $e_params[] = "%" . $var . "%";
        $e_params[] = "%" . $var . "%";
        $e_types .= "ss";
    }

    $excel_sql = "SELECT e.BookNumber, e.BookName, e.BookRemarks, e.AutherName, e.BookClass, e.BookCode, e.CallNumber, e.BookPrice, 1 as BookStatus,
                         e.title_search_key, e.author_search_key, e.publisher_id,
                         COALESCE(pub.publisher_name, p.PublisherName, 'N/A') as PublisherName
                  FROM ExcelBookMaster e
                  LEFT JOIN Publishers pub ON e.publisher_id = pub.publisher_id
                  LEFT JOIN MstrPublisher p ON e.publisher_id = p.PublisherNumber
                  WHERE " . implode(" OR ", $e_clauses) . "
                  ORDER BY e.BookNumber DESC LIMIT 50";
    $e_res = db_query($conn, $excel_sql, $e_types, $e_params);
    if ($e_res && db_num_rows($e_res) > 0) {
        while ($er = db_fetch_assoc($e_res)) {
            $er['AccessionNumber'] = $er['CallNumber'] ?? ('B' . $er['BookNumber']);
            $books[] = $er;
        }
    }
}

send_json_success($books, "Book stock records retrieved.");
