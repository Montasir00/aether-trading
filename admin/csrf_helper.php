<?php
/**
 * csrf_helper.php — Admin CSRF Protection
 * Generates and validates CSRF tokens scoped to the admin session.
 */

/**
 * Generate (or retrieve existing) CSRF token for the admin session.
 */
function csrf_get_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Output a hidden CSRF input field ready to embed in a form.
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_get_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

/**
 * Validate the CSRF token from a POST request.
 * Terminates with 403 if invalid.
 */
function csrf_validate_token(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['admin_csrf_token'] ?? '';

    if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('<h1>403 — CSRF validation failed.</h1>');
    }
}
