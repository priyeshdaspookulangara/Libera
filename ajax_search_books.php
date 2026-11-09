<?php
/**
 * ajax_search_books.php
 * Quick lookup AJAX endpoint for book catalog searches with Manglish transliteration support.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/manglish_multi_table_search.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode([
        'query'      => '',
        'translated' => '',
        'count'      => 0,
        'results'    => []
    ]);
    exit;
}

$search_data = search_books_and_authors($conn, $query, 50);

// Format output expected by frontend widgets
$output = [
    'query'      => $search_data['query'],
    'translated' => $search_data['translated'],
    'count'      => $search_data['count'],
    'results'    => []
];

foreach ($search_data['results'] as $item) {
    $output['results'][] = [
        'BookNumber'      => $item['BookNumber'],
        'BookName'        => $item['BookName'],
        'AuthorName'      => $item['AuthorName'],
        'CallNumber'      => $item['CallNumber'],
        'AccessionNumber' => $item['AccessionNumber'],
        'BookPrice'       => $item['BookPrice'],
        'BookStatus'      => $item['BookStatus'],
        'source'          => $item['source']
    ];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
exit;
