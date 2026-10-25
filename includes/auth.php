<?php
// includes/auth.php - Secure Session Management & Privilege Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if staff user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user login or redirect
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /login.php");
        exit();
    }
}

/**
 * Sanitize user input strings
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get active financial year record
 */
function get_active_fiscal_year($conn) {
    $res = db_query($conn, "SELECT * FROM MstrFiscalYear WHERE CurrentYear = 1 LIMIT 1");
    if ($res && db_num_rows($res) > 0) {
        return db_fetch_assoc($res);
    }
    return ['YearNumber' => 25, 'YearDescription' => '2024-2025'];
}

/**
 * Get company profile
 */
function get_company_profile($conn) {
    $res = db_query($conn, "SELECT * FROM ConfigCompany WHERE CompanyNumber = 1 LIMIT 1");
    if ($res && db_num_rows($res) > 0) {
        return db_fetch_assoc($res);
    }
    return ['CompanyName' => 'Libera Library & Accounting System', 'CompanyAddress' => 'Main Branch'];
}
