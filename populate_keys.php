<?php
/**
 * populate_keys.php
 * One-time batch migration CLI/Web script to populate `title_search_key` and `author_search_key`
 * in the books table (MstrBookStock).
 *
 * Usage:
 *   CLI: php populate_keys.php
 *   Web: Open http://your-domain/populate_keys.php in browser
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/manglish_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================================\n";
echo " Libera Manglish Search Keys Batch Migration Script\n";
echo "========================================================\n\n";

// 1. Ensure columns exist (for MySQL and SQLite environments)
global $db_driver;

echo "[1/3] Checking table schema...\n";

if ($db_driver === 'mysqli') {
    // Check if columns exist in MySQL
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
} else {
    // SQLite environment
    $has_title = false;
    $has_author = false;
    $cols = db_query($conn, "PRAGMA table_info(MstrBookStock)");
    while ($r = db_fetch_assoc($cols)) {
        if ($r['name'] === 'title_search_key') $has_title = true;
        if ($r['name'] === 'author_search_key') $has_author = true;
    }
    if (!$has_title) {
        db_query($conn, "ALTER TABLE MstrBookStock ADD COLUMN title_search_key VARCHAR(255) NULL");
    }
    if (!$has_author) {
        db_query($conn, "ALTER TABLE MstrBookStock ADD COLUMN author_search_key VARCHAR(255) NULL");
    }
    db_query($conn, "CREATE INDEX IF NOT EXISTS idx_manglish_keys ON MstrBookStock(title_search_key, author_search_key)");
    echo "Columns and composite index verified in SQLite.\n";
}

// 2. Fetch existing book records joined with author names
echo "\n[2/3] Fetching book catalog records...\n";

$query = "SELECT b.BookNumber, b.BookName, a.AuthorName, b.title_search_key, b.author_search_key
          FROM MstrBookStock b
          LEFT JOIN MstrAuthor a ON b.AuthorNumber = a.AuthorNumber";

$res = db_query($conn, $query);
$total = db_num_rows($res);
echo "Found {$total} books in catalog.\n\n";

// 3. Loop and update search keys for MstrBookStock
echo "[3/3] Generating Manglish search keys and updating records...\n";

$updated_count = 0;
$skipped_count = 0;

db_begin_transaction($conn);

$stmt_sql = "UPDATE MstrBookStock SET title_search_key = ?, author_search_key = ? WHERE BookNumber = ?";

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
