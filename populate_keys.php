<?php
/**
 * populate_keys.php
 * One-time batch migration CLI/Web script to populate `title_search_key` and `author_search_key`
 * in the books table (MstrBookStock) in MySQL.
 *
 * Usage:
 *   CLI: php populate_keys.php
 *   Web: Open http://your-domain/populate_keys.php in browser
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/manglish_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================================\n";
echo " Libera Manglish Search Keys Batch Migration Script (MySQL)\n";
echo "========================================================\n\n";

if (!$conn) {
    echo "ERROR: Unable to connect to MySQL database. Please check DB credentials in config/db.php.\n";
    exit(1);
}

echo "[1/3] Checking MySQL table schema...\n";

// Check if columns exist in MySQL MstrBookStock
$col_check = db_query($conn, "SHOW COLUMNS FROM MstrBookStock LIKE 'title_search_key'");
if (!$col_check || db_num_rows($col_check) === 0) {
    echo "Adding `title_search_key` and `author_search_key` columns to MstrBookStock in MySQL...\n";
    db_query($conn, "ALTER TABLE MstrBookStock
                     ADD COLUMN title_search_key VARCHAR(255) NULL AFTER BookName,
                     ADD COLUMN author_search_key VARCHAR(255) NULL AFTER title_search_key,
                     ADD INDEX idx_manglish_keys (title_search_key, author_search_key)");
} else {
    echo "Columns already exist in MstrBookStock.\n";
}

// 2. Fetch existing book records joined with author names
echo "\n[2/3] Fetching book catalog records from MySQL...\n";

$query = "SELECT b.BookNumber, b.BookName, a.AuthorName, b.title_search_key, b.author_search_key
          FROM MstrBookStock b
          LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber";

$res = db_query($conn, $query);
$total = $res ? db_num_rows($res) : 0;
echo "Found {$total} books in catalog.\n\n";

// 3. Loop and update search keys for MstrBookStock
echo "[3/3] Generating Manglish search keys and updating MySQL records...\n";

$updated_count = 0;
$skipped_count = 0;

db_begin_transaction($conn);

$stmt_sql = "UPDATE MstrBookStock SET title_search_key = ?, author_search_key = ? WHERE BookNumber = ?";

if ($res) {
    while ($row = db_fetch_assoc($res)) {
        $book_id = $row['BookNumber'];
        $book_name = $row['BookName'] ?? '';
        $author_name = $row['AuthorName'] ?? '';

        $title_key = generate_manglish_key($book_name);
        $author_key = generate_manglish_key($author_name);

        $upd = db_query($conn, $stmt_sql, "ssi", [$title_key, $author_key, $book_id]);
        if ($upd !== false) {
            $updated_count++;
            if ($updated_count <= 10 || $updated_count % 50 === 0) {
                echo "  [MstrBookStock #{$book_id}] '{$book_name}' => '{$title_key}' | '{$author_name}' => '{$author_key}'\n";
            }
        } else {
            $skipped_count++;
        }
    }
}

// Also populate ExcelBookMaster if records exist in ExcelBookMaster
$excel_res = db_query($conn, "SELECT BookNumber, BookName, AutherName FROM ExcelBookMaster");
if ($excel_res && db_num_rows($excel_res) > 0) {
    echo "\nAlso updating " . db_num_rows($excel_res) . " records in legacy ExcelBookMaster table...\n";
    $excel_upd_sql = "UPDATE ExcelBookMaster SET title_search_key = ?, author_search_key = ? WHERE BookNumber = ?";
    while ($erow = db_fetch_assoc($excel_res)) {
        $eb_id = $erow['BookNumber'];
        $eb_title = $erow['BookName'] ?? '';
        $eb_author = $erow['AutherName'] ?? '';
        $et_key = generate_manglish_key($eb_title);
        $ea_key = generate_manglish_key($eb_author);
        db_query($conn, $excel_upd_sql, "ssi", [$et_key, $ea_key, $eb_id]);
        $updated_count++;
    }
}

db_commit($conn);

echo "\n========================================================\n";
echo " Migration Complete!\n";
echo " Successfully updated: {$updated_count} records\n";
if ($skipped_count > 0) {
    echo " Failed / Skipped: {$skipped_count} records\n";
}
echo "========================================================\n";
