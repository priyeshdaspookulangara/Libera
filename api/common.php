<?php
// api/common.php - Common Helper & Security Middleware for Libera Mobile REST API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Get JSON input body
 */
function get_json_input() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

/**
 * Send JSON Response
 */
function send_json($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Send JSON Error Response
 */
function send_json_error($message, $status_code = 400) {
    send_json([
        'status' => 'error',
        'message' => $message
    ], $status_code);
}

/**
 * Send JSON Success Response
 */
function send_json_success($data = [], $message = 'Success') {
    send_json([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ], 200);
}

/**
 * Require Valid Mobile Bearer Token Authentication
 */
function require_api_auth() {
    global $conn;
    $token = '';

    // Check Authorization: Bearer <token>
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
        $token = $matches[1];
    }

    if (empty($token)) {
        $input = get_json_input();
        $token = trim($_GET['token'] ?? $_POST['token'] ?? $input['token'] ?? '');
    }

    if (empty($token)) {
        send_json_error("Unauthorized. Bearer token missing.", 401);
    }

    // Verify token against ApiTokens table (using standard MySQL NOW() compatible with SQLite driver wrapper)
    $res = db_query($conn, "SELECT t.*, u.UserName, u.UserLevelNumber
                           FROM ApiTokens t
                           JOIN MstrUsers u ON t.UserNumber = u.UserNumber
                           WHERE t.TokenValue = ? AND (t.ExpiresAt IS NULL OR t.ExpiresAt > NOW())
                           LIMIT 1", "s", [$token]);

    if (!$res || db_num_rows($res) === 0) {
        send_json_error("Unauthorized. Invalid or expired token.", 401);
    }

    return db_fetch_assoc($res);
}
