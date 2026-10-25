<?php
// api/auth.php - Mobile Staff Authentication API Endpoint
require_once __DIR__ . '/common.php';

$input = get_json_input();
$username = trim($input['username'] ?? $_POST['username'] ?? '');
$password = trim($input['password'] ?? $_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    send_json_error("Username and password are required.", 400);
}

$res = db_query($conn, "SELECT * FROM MstrUsers WHERE UserName = ? AND UserStatus = 1 LIMIT 1", "s", [$username]);

if ($res && db_num_rows($res) > 0) {
    $user = db_fetch_assoc($res);
    if (password_verify($password, $user['UserPassword']) || $password === $user['UserPassword']) {
        // Generate mobile session token
        $token = bin2hex(random_bytes(32));
        $created_at = date('Y-m-d H:i:s');
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Save Token in ApiTokens
        db_query($conn, "INSERT INTO ApiTokens (TokenValue, UserNumber, CreatedAt, ExpiresAt) VALUES (?, ?, ?, ?)", "siss", [$token, $user['UserNumber'], $created_at, $expires_at]);

        send_json_success([
            'token' => $token,
            'expires_at' => $expires_at,
            'user' => [
                'user_id' => $user['UserNumber'],
                'username' => $user['UserName'],
                'user_code' => $user['UserCode'],
                'user_level' => $user['UserLevelNumber']
            ]
        ], "Login successful.");
    } else {
        send_json_error("Invalid credentials.", 401);
    }
} else {
    send_json_error("User not found or account is inactive.", 401);
}
