<?php
// =============================================================================
// auth.php — Session & Authentication Helper
// =============================================================================
// TEACHING NOTE: session_start() must be called BEFORE any HTML output or
// header() calls. It tells PHP to resume (or create) a session, making
// $_SESSION available to store data between page requests.
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================================================
// FUNCTION: require_login()
// Call this at the top of any page that needs authentication.
// If the user is not logged in, they are sent back to index.php immediately.
// =============================================================================
function require_login(): void {
    // TEACHING NOTE: We check if the 'user_id' key exists inside $_SESSION.
    // This key is set in index.php after a successful login.
    if (empty($_SESSION['user_id'])) {
        // TEACHING NOTE: header('Location: ...') sends an HTTP redirect response
        // to the browser. The browser then requests the new URL automatically.
        // exit() MUST follow header() to stop further PHP execution.
        header('Location: index.php');
        exit();
    }
}

// =============================================================================
// FUNCTION: require_role($role)
// Call this on pages restricted to a specific role (e.g., 'admin').
// If the user's role doesn't match, they are bounced to their own dashboard.
// =============================================================================
function require_role(string $role): void {
    require_login(); // First make sure they are logged in at all.

    if ($_SESSION['role'] !== $role) {
        // TEACHING NOTE: A regular 'user' trying to visit admin_dashboard.php
        // will be caught here and redirected safely away.
        header('Location: user_dashboard.php');
        exit();
    }
}

// =============================================================================
// FUNCTION: is_logged_in()
// Returns true/false — useful for conditional rendering in templates.
// =============================================================================
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

// =============================================================================
// FUNCTION: flash($key, $message)
// Stores a one-time message in the session (e.g., "Meal added!").
// The message is shown once, then deleted.
// =============================================================================
function flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

// =============================================================================
// FUNCTION: get_flash($key)
// Retrieves and DELETES a flash message. Returns null if it doesn't exist.
// =============================================================================
function get_flash(string $key): ?string {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]); // Delete after reading — "flash" = one-time
        return $msg;
    }
    return null;
}

// =============================================================================
// FUNCTION: sanitize($str)
// Converts special HTML characters to entities to prevent XSS attacks.
// XSS (Cross-Site Scripting) is when an attacker injects malicious HTML/JS.
// Always use this when outputting user-supplied data to the browser!
// =============================================================================
function sanitize(string $str): string {
    // TEACHING NOTE: htmlspecialchars() turns < into &lt;, > into &gt;, etc.
    // ENT_QUOTES converts both single and double quotes.
    // 'UTF-8' ensures multi-byte characters are handled correctly.
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
