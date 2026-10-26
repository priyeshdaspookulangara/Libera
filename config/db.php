<?php
// config/db.php Centralized Database Layer supporting MySQLi and SQLite fallback with Transaction Support

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Library');
define('DB_PORT', 3306);

$conn = null;
$db_driver = 'mysqli';

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli_conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($mysqli_conn) {
    $conn = $mysqli_conn;
    $db_driver = 'mysqli';
    mysqli_set_charset($conn, "utf8mb4");
} else {
    // Fallback to SQLite3 driver for sandbox testing environment
    $db_driver = 'sqlite';
    $sqlite_file = __DIR__ . '/../library.sqlite3';
    $is_new = !file_exists($sqlite_file);

    try {
        $conn = new PDO("sqlite:" . $sqlite_file);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        if ($is_new) {
            init_sqlite_schema($conn);
        }
    } catch (PDOException $e) {
        error_log("Database Connection Failed: " . $e->getMessage());
    }
}

/**
 * Transaction Control Helpers
 */
function db_begin_transaction($conn) {
    global $db_driver;
    if (!$conn) return false;
    if ($db_driver === 'mysqli') {
        return mysqli_begin_transaction($conn);
    } else {
        return $conn->beginTransaction();
    }
}

function db_commit($conn) {
    global $db_driver;
    if (!$conn) return false;
    if ($db_driver === 'mysqli') {
        return mysqli_commit($conn);
    } else {
        return $conn->commit();
    }
}

function db_rollback($conn) {
    global $db_driver;
    if (!$conn) return false;
    if ($db_driver === 'mysqli') {
        return mysqli_rollback($conn);
    } else {
        return $conn->rollBack();
    }
}

/**
 * Execute SQL query with parameter binding across MySQLi or SQLite PDO
 */
function db_query($conn, $sql, $types = "", $params = []) {
    global $db_driver;
    if (!$conn) return false;

    if ($db_driver === 'mysqli') {
        if (empty($params)) {
            return mysqli_query($conn, $sql);
        }
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            $affected = mysqli_stmt_affected_rows($stmt);
            $insert_id = mysqli_stmt_insert_id($stmt);
            mysqli_stmt_close($stmt);
            return ['affected_rows' => $affected, 'insert_id' => $insert_id];
        }
        mysqli_stmt_close($stmt);
        return $result;
    } else {
        // SQLite via PDO
        $sqlite_sql = preg_replace('/ON DUPLICATE KEY UPDATE.*/i', '', $sql);
        $sqlite_sql = preg_replace('/ON CONFLICT DO UPDATE.*/i', '', $sqlite_sql);
        $sqlite_sql = str_replace('NOW()', "datetime('now')", $sqlite_sql);

        $stmt = $conn->prepare($sqlite_sql);
        if (!$stmt) return false;

        $res = $stmt->execute($params);
        if (!$res) return false;

        if (preg_match('/^\s*(SELECT|PRAGMA|EXPLAIN)/i', $sqlite_sql)) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return new SQLiteResultWrapper($rows);
        } else {
            $affected = $stmt->rowCount();
            $insert_id = $conn->lastInsertId();
            return ['affected_rows' => $affected, 'insert_id' => $insert_id];
        }
    }
}

/**
 * Wrapper to mimic mysqli_result methods for SQLite array results
 */
class SQLiteResultWrapper implements IteratorAggregate {
    private $rows;
    private $pointer = 0;
    public $num_rows = 0;

    public function __construct($rows) {
        $this->rows = is_array($rows) ? $rows : [];
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc() {
        if ($this->pointer < $this->num_rows) {
            return $this->rows[$this->pointer++];
        }
        return null;
    }

    public function data_seek($offset) {
        if ($offset >= 0 && $offset < $this->num_rows) {
            $this->pointer = $offset;
            return true;
        }
        return false;
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->rows);
    }
}

// Override PHP standard mysqli helper functions when running SQLite mode
if ($db_driver === 'sqlite') {
    function db_num_rows($res) {
        if (is_object($res) && isset($res->num_rows)) return $res->num_rows;
        if (is_object($res) && method_exists($res, 'num_rows')) return $res->num_rows();
        if ($res instanceof mysqli_result) return mysqli_num_rows($res);
        return 0;
    }

    function db_fetch_assoc($res) {
        if (is_object($res) && method_exists($res, 'fetch_assoc')) return $res->fetch_assoc();
        if ($res instanceof mysqli_result) return mysqli_fetch_assoc($res);
        return false;
    }

    function db_data_seek($res, $offset) {
        if (is_object($res) && method_exists($res, 'data_seek')) return $res->data_seek($offset);
        if ($res instanceof mysqli_result) return mysqli_data_seek($res, $offset);
        return false;
    }
} else {
    function db_num_rows($res) {
        return ($res instanceof mysqli_result) ? mysqli_num_rows($res) : 0;
    }

    function db_fetch_assoc($res) {
        return ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : false;
    }

    function db_data_seek($res, $offset) {
        return ($res instanceof mysqli_result) ? mysqli_data_seek($res, $offset) : false;
    }
}

function init_sqlite_schema($pdo) {
    $sql_file = __DIR__ . '/../schema.sql';
    if (!file_exists($sql_file)) return;

    $raw_sql = file_get_contents($sql_file);

    // Convert MySQL syntax to SQLite syntax
    $clean_sql = preg_replace('/CREATE DATABASE.*/i', '', $raw_sql);
    $clean_sql = preg_replace('/USE `?Library`?;/i', '', $clean_sql);
    $clean_sql = preg_replace('/ENGINE=InnoDB/i', '', $clean_sql);
    $clean_sql = str_replace('LONGBLOB', 'BLOB', $clean_sql);

    // SQLite autoincrement rule
    $clean_sql = preg_replace('/`(.*?)`\s+(smallint|tinyint|bigint|int)\s+NOT\s+NULL\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', '`$1` INTEGER PRIMARY KEY AUTOINCREMENT', $clean_sql);
    $clean_sql = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $clean_sql);
    $clean_sql = str_replace('double', 'REAL', $clean_sql);
    $clean_sql = str_replace('datetime', 'TEXT', $clean_sql);

    // Remove MySQL comments
    $lines = explode("\n", $clean_sql);
    $clean_lines = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), "--") === 0) continue;
        $clean_lines[] = $line;
    }
    $clean_sql = implode("\n", $clean_lines);

    // Split queries by semicolon
    $queries = explode(';', $clean_sql);
    foreach ($queries as $q) {
        $q_trim = trim($q);
        if (empty($q_trim)) continue;

        if (stripos($q_trim, 'INSERT INTO') === 0 && stripos($q_trim, 'ON DUPLICATE KEY UPDATE') !== false) {
            $q_trim = preg_replace('/ON DUPLICATE KEY UPDATE.*/is', '', $q_trim);
        }

        $q_trim = preg_replace('/AUTO_INCREMENT=\d+/i', '', $q_trim);

        if (stripos($q_trim, "Harry Potter and the Sorcerer\'s Stone") !== false) {
            $q_trim = str_replace("Harry Potter and the Sorcerer\'s Stone", "Harry Potter and the Sorcerer''s Stone", $q_trim);
        }

        try {
            $pdo->exec($q_trim);
        } catch (Exception $e) {
            error_log("SQLite Exec Notice: " . $e->getMessage());
        }
    }
}
