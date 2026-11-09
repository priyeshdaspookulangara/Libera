<?php
// config/db.php - Centralized Pure MySQL Database Connection Layer using MySQLi

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'Library');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

$conn = null;

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli_conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($mysqli_conn) {
    $conn = $mysqli_conn;
    mysqli_set_charset($conn, "utf8mb4");
} else {
    error_log("MySQL Connection Failed: " . mysqli_connect_error());
}

/**
 * Check if a specific column exists in a MySQL table (cached per request)
 */
function db_table_has_column($conn, $table, $column) {
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) return $cache[$key];
    if (!$conn) return false;

    $safe_table = mysqli_real_escape_string($conn, (string)$table);
    $safe_col = mysqli_real_escape_string($conn, (string)$column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$safe_table` LIKE '$safe_col'");
    $has = ($res && mysqli_num_rows($res) > 0);
    $cache[$key] = $has;
    return $has;
}

/**
 * Transaction Control Helpers (MySQLi)
 */
function db_begin_transaction($conn) {
    if (!$conn) return false;
    return mysqli_begin_transaction($conn);
}

function db_commit($conn) {
    if (!$conn) return false;
    return mysqli_commit($conn);
}

function db_rollback($conn) {
    if (!$conn) return false;
    return mysqli_rollback($conn);
}

function db_escape($conn, $string) {
    if (!$conn) return addslashes((string)$string);
    return mysqli_real_escape_string($conn, (string)$string);
}

/**
 * Execute MySQL SQL query with optional prepared statement parameter binding
 *
 * @param mysqli $conn
 * @param string $sql
 * @param string $types e.g. "ssi"
 * @param array $params
 * @return mysqli_result|array|false
 */
function db_query($conn, $sql, $types = "", $params = []) {
    if (!$conn) return false;

    if (empty($params)) {
        return mysqli_query($conn, $sql);
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("MySQL Prepare Error: " . mysqli_error($conn) . " in SQL: " . $sql);
        return false;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $executed = mysqli_stmt_execute($stmt);
    if (!$executed) {
        error_log("MySQL Execute Error: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        // DML statement (INSERT, UPDATE, DELETE)
        $affected = mysqli_stmt_affected_rows($stmt);
        $insert_id = mysqli_stmt_insert_id($stmt);
        mysqli_stmt_close($stmt);
        return ['affected_rows' => $affected, 'insert_id' => $insert_id];
    }

    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Safe fetch associative array helper for MySQLi results
 */
function db_fetch_assoc($res) {
    if ($res instanceof mysqli_result) {
        return mysqli_fetch_assoc($res);
    }
    return false;
}

/**
 * Safe num rows helper for MySQLi results
 */
function db_num_rows($res) {
    if ($res instanceof mysqli_result) {
        return mysqli_num_rows($res);
    }
    return 0;
}

/**
 * Safe seek helper for MySQLi results
 */
function db_data_seek($res, $offset) {
    if ($res instanceof mysqli_result) {
        return mysqli_data_seek($res, $offset);
    }
    return false;
}
