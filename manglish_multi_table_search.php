<?php
/**
 * manglish_multi_table_search.php
 * Unified Multi-Table Book & Author Phonetic Search Engine for Libera.
 *
 * Supports flexible searches across:
 *   - MstrBookStock (joined with MstrAuthor, MstrPublisher, MstrCategory)
 *   - MstrAuthor directly
 *   - ExcelBookMaster (legacy catalog master)
 *
 * Translates phonetic queries bidirectionally between Malayalam Unicode and Manglish (Latin),
 * resolving dialect / spelling nuances (e.g., -kal / -gal, sh / s, zh / l, v / w, th / t, etc.).
 * Automatically adapts if title_search_key / author_search_key columns are present or absent!
 *
 * Usage:
 *   require_once __DIR__ . '/config/db.php';
 *   require_once __DIR__ . '/manglish_multi_table_search.php';
 *   $search_output = search_books_and_authors($conn, $query);
 */

require_once __DIR__ . '/includes/manglish_helper.php';

if (!function_exists('search_books_and_authors')) {
function search_books_and_authors($conn, $query, $limit = 50) {
    $raw_query = trim((string)$query);
    if ($raw_query === '') {
        return [
            'query' => '',
            'translated' => '',
            'variations' => [],
            'count' => 0,
            'results' => []
        ];
    }

    // Determine phonetic translation text
    $is_malayalam = preg_match('/[\x{0D00}-\x{0D7F}]/u', $raw_query);
    if ($is_malayalam) {
        $translated = generate_manglish_key($raw_query);
    } else {
        $mal_cands = manglish_to_malayalam_terms($raw_query);
        $translated = !empty($mal_cands) ? $mal_cands[0] : generate_manglish_key($raw_query);
    }

    // Generate phonetic search variations (Latin variants + Malayalam Unicode candidates)
    $variations = get_manglish_search_variations($raw_query);

    $results = [];
    $seen_books = [];

    // Check if optional search key columns exist in MstrBookStock
    $has_mstr_title_key = db_table_has_column($conn, 'MstrBookStock', 'title_search_key');
    $has_mstr_author_key = db_table_has_column($conn, 'MstrBookStock', 'author_search_key');

    // 1. Search in MstrBookStock joined with MstrAuthor, MstrPublisher, MstrCategory
    $where_clauses = [
        "b.BookName LIKE ?",
        "a.AuthorName LIKE ?",
        "b.CallNumber LIKE ?",
        "b.AccessionNumber LIKE ?",
        "b.BookCode LIKE ?",
        "b.BookRemarks LIKE ?"
    ];
    $raw_like = '%' . $raw_query . '%';
    $params = [$raw_like, $raw_like, $raw_like, $raw_like, $raw_like, $raw_like];
    $types = "ssssss";

    // For each variation, match BookName, AuthorName, and search keys if present
    foreach ($variations as $var) {
        $where_clauses[] = "b.BookName LIKE ?";
        $where_clauses[] = "a.AuthorName LIKE ?";
        $var_like = '%' . $var . '%';
        $params[] = $var_like;
        $params[] = $var_like;
        $types .= "ss";

        if ($has_mstr_title_key) {
            $where_clauses[] = "b.title_search_key LIKE ?";
            $params[] = $var_like;
            $types .= "s";
        }
        if ($has_mstr_author_key) {
            $where_clauses[] = "b.author_search_key LIKE ?";
            $params[] = $var_like;
            $types .= "s";
        }
    }

    $where_sql = implode(" OR ", $where_clauses);

    $sql = "SELECT b.BookNumber,
                   b.BookName,
                   COALESCE(a.AuthorName, '') AS AuthorName,
                   COALESCE(b.CallNumber, '') AS CallNumber,
                   COALESCE(b.AccessionNumber, '') AS AccessionNumber,
                   COALESCE(b.ISBNumber, '') AS ISBNumber,
                   COALESCE(b.BookCode, '') AS BookCode,
                   COALESCE(p.PublisherName, '') AS PublisherName,
                   COALESCE(c.CategoryName, '') AS CategoryName,
                   b.BookPrice,
                   b.BookStatus
            FROM MstrBookStock b
            LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
            LEFT JOIN MstrPublisher p ON b.PublisherNumber = p.PublisherNumber
            LEFT JOIN MstrCategory c ON b.CategoryNumber = c.CategoryNumber
            WHERE $where_sql
            ORDER BY b.BookNumber ASC
            LIMIT " . intval($limit);

    $res = db_query($conn, $sql, $types, $params);
    if ($res) {
        while ($row = db_fetch_assoc($res)) {
            $key = 'MSTR_' . $row['BookNumber'];
            $seen_books[$key] = true;
            $call_no = !empty($row['CallNumber']) ? $row['CallNumber'] : (!empty($row['AccessionNumber']) ? $row['AccessionNumber'] : 'ACC' . str_pad($row['BookNumber'], 4, '0', STR_PAD_LEFT));
            $results[] = [
                'BookNumber'      => (int)$row['BookNumber'],
                'BookName'        => $row['BookName'],
                'AuthorName'      => $row['AuthorName'],
                'CallNumber'      => $call_no,
                'AccessionNumber' => $row['AccessionNumber'],
                'ISBNumber'       => $row['ISBNumber'],
                'BookCode'        => $row['BookCode'],
                'PublisherName'   => $row['PublisherName'],
                'CategoryName'    => $row['CategoryName'],
                'BookPrice'       => (float)$row['BookPrice'],
                'BookStatus'      => (int)$row['BookStatus'],
                'source'          => 'MstrBookStock'
            ];
        }
    }

    // 2. Also search MstrAuthor directly for author name matches
    $auth_where = ["a.AuthorName LIKE ?"];
    $auth_params = [$raw_like];
    $auth_types = "s";
    $has_auth_key = db_table_has_column($conn, 'MstrAuthor', 'author_search_key');
    foreach ($variations as $var) {
        $auth_where[] = "a.AuthorName LIKE ?";
        $auth_params[] = '%' . $var . '%';
        $auth_types .= "s";
        if ($has_auth_key) {
            $auth_where[] = "a.author_search_key LIKE ?";
            $auth_params[] = '%' . $var . '%';
            $auth_types .= "s";
        }
    }

    $auth_sql = "SELECT a.AuthorNumber FROM MstrAuthor a WHERE " . implode(" OR ", $auth_where) . " LIMIT 20";
    $auth_res = @db_query($conn, $auth_sql, $auth_types, $auth_params);
    if ($auth_res) {
        $author_ids = [];
        while ($ar = db_fetch_assoc($auth_res)) {
            $author_ids[] = (int)$ar['AuthorNumber'];
        }
        if (!empty($author_ids)) {
            $in_clause = implode(',', $author_ids);
            $auth_books_sql = "SELECT b.BookNumber,
                                      b.BookName,
                                      COALESCE(a.AuthorName, '') AS AuthorName,
                                      COALESCE(b.CallNumber, '') AS CallNumber,
                                      COALESCE(b.AccessionNumber, '') AS AccessionNumber,
                                      COALESCE(b.ISBNumber, '') AS ISBNumber,
                                      COALESCE(b.BookCode, '') AS BookCode,
                                      b.BookPrice,
                                      b.BookStatus
                               FROM MstrBookStock b
                               LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber
                               WHERE b.AuthorNumber IN ($in_clause)
                               LIMIT " . intval($limit);
            $ab_res = db_query($conn, $auth_books_sql);
            if ($ab_res) {
                while ($brow = db_fetch_assoc($ab_res)) {
                    $key = 'MSTR_' . $brow['BookNumber'];
                    if (!isset($seen_books[$key])) {
                        $seen_books[$key] = true;
                        $call_no = !empty($brow['CallNumber']) ? $brow['CallNumber'] : (!empty($brow['AccessionNumber']) ? $brow['AccessionNumber'] : 'ACC' . str_pad($brow['BookNumber'], 4, '0', STR_PAD_LEFT));
                        $results[] = [
                            'BookNumber'      => (int)$brow['BookNumber'],
                            'BookName'        => $brow['BookName'],
                            'AuthorName'      => $brow['AuthorName'],
                            'CallNumber'      => $call_no,
                            'AccessionNumber' => $brow['AccessionNumber'],
                            'ISBNumber'       => $brow['ISBNumber'],
                            'BookCode'        => $brow['BookCode'],
                            'PublisherName'   => '',
                            'CategoryName'    => '',
                            'BookPrice'       => (float)$brow['BookPrice'],
                            'BookStatus'      => (int)$brow['BookStatus'],
                            'source'          => 'MstrBookStock'
                        ];
                    }
                }
            }
        }
    }

    // 3. Search ExcelBookMaster (legacy catalog master table)
    $remaining_limit = $limit - count($results);
    if ($remaining_limit > 0) {
        $has_e_title_key = db_table_has_column($conn, 'ExcelBookMaster', 'title_search_key');
        $has_e_author_key = db_table_has_column($conn, 'ExcelBookMaster', 'author_search_key');

        $excel_where = [
            "e.BookName LIKE ?",
            "e.AutherName LIKE ?",
            "e.CallNumber LIKE ?",
            "e.BookCode LIKE ?",
            "e.BookRemarks LIKE ?"
        ];
        $e_params = [$raw_like, $raw_like, $raw_like, $raw_like, $raw_like];
        $e_types = "sssss";

        foreach ($variations as $var) {
            $excel_where[] = "e.BookName LIKE ?";
            $excel_where[] = "e.AutherName LIKE ?";
            $e_params[] = '%' . $var . '%';
            $e_params[] = '%' . $var . '%';
            $e_types .= "ss";

            if ($has_e_title_key) {
                $excel_where[] = "e.title_search_key LIKE ?";
                $e_params[] = '%' . $var . '%';
                $e_types .= "s";
            }
            if ($has_e_author_key) {
                $excel_where[] = "e.author_search_key LIKE ?";
                $e_params[] = '%' . $var . '%';
                $e_types .= "s";
            }
        }

        $e_where_sql = implode(" OR ", $excel_where);
        $excel_sql = "SELECT e.BookNumber,
                             e.BookName,
                             COALESCE(e.AutherName, '') AS AuthorName,
                             COALESCE(e.CallNumber, '') AS CallNumber,
                             COALESCE(e.BookCode, '') AS BookCode,
                             COALESCE(e.BookRemarks, '') AS BookRemarks,
                             COALESCE(e.BookPrice, 0.0) AS BookPrice
                      FROM ExcelBookMaster e
                      WHERE $e_where_sql
                      ORDER BY e.BookNumber ASC
                      LIMIT " . intval($remaining_limit);

        $e_res = @db_query($conn, $excel_sql, $e_types, $e_params);
        if ($e_res) {
            while ($erow = db_fetch_assoc($e_res)) {
                $key = 'EXCEL_' . $erow['BookNumber'];
                if (!isset($seen_books[$key])) {
                    $seen_books[$key] = true;
                    $call_no = !empty($erow['CallNumber']) ? $erow['CallNumber'] : ('A' . str_pad($erow['BookNumber'], 4, '0', STR_PAD_LEFT));
                    $results[] = [
                        'BookNumber'      => (int)$erow['BookNumber'],
                        'BookName'        => $erow['BookName'],
                        'AuthorName'      => $erow['AuthorName'],
                        'CallNumber'      => $call_no,
                        'AccessionNumber' => $call_no,
                        'ISBNumber'       => '',
                        'BookCode'        => $erow['BookCode'],
                        'PublisherName'   => '',
                        'CategoryName'    => '',
                        'BookPrice'       => (float)$erow['BookPrice'],
                        'BookStatus'      => 1,
                        'source'          => 'ExcelBookMaster'
                    ];
                }
            }
        }
    }

    return [
        'query'      => $raw_query,
        'translated' => $translated,
        'variations' => $variations,
        'count'      => count($results),
        'results'    => $results
    ];
}
}
