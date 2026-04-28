<?php
// =============================================================================
// index.php — Login & Registration Page
// =============================================================================
// TEACHING NOTE: require_once() includes a file exactly ONE time no matter
// how many times the statement is encountered. We use it for files that
// define functions/classes, preventing "function already declared" errors.
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';

// TEACHING NOTE: If the user is already logged in, there is no reason for them
// to see the login page. We redirect them to their appropriate dashboard.
if (is_logged_in()) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE LOGOUT
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: A clean logout requires three steps:
// 1. Clear all data from the $_SESSION superglobal array.
// 2. Destroy the session file on the server with session_destroy().
// 3. Redirect to the login page so the user sees a fresh state.
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// REMEMBER ME — Pre-fill email from cookie if it exists
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: $_COOKIE is a superglobal that holds all cookies sent by the
// browser. We stored the email (NOT the password) in a cookie earlier.
// We use the null coalescing operator (??) to safely get the value or ''.
$remembered_email = $_COOKIE['ns_remember'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// ERROR / SUCCESS HOLDERS
// ─────────────────────────────────────────────────────────────────────────────
$login_error  = get_flash('login_error');
$reg_error    = get_flash('reg_error');
$reg_success  = get_flash('reg_success');
$show_reg_tab = get_flash('show_register'); // JS uses this to switch to Register tab

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE LOGIN (POST)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    // TEACHING NOTE: We read user input from $_POST then immediately trim()
    // it (removes leading/trailing whitespace) and store it in a local variable.
    // We never use $_POST values directly in logic without sanitizing them first.
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password']   ?? '');

    // TEACHING NOTE: Server-side validation — never trust the browser alone.
    // Even if HTML "required" is set, a malicious user can bypass it with tools
    // like curl or Postman. PHP must always re-check.
    if (empty($identifier) || empty($password)) {
        flash('login_error', 'Please fill in all fields.');
        header('Location: index.php');
        exit();
    }

    // TEACHING NOTE: This is a PREPARED STATEMENT. Instead of inserting
    // $identifier directly into the SQL string (which would allow SQL Injection),
    // we use a placeholder (:identifier) and bind the actual value separately.
    // PDO sends them as two separate packets — the DB never misinterprets the
    // user's input as SQL code.
    // We check BOTH email AND username columns so the user can log in either way.
// 1. Give each placeholder a unique name in the SQL
$stmt = $pdo->prepare(
    'SELECT id, username, email, password_hash, role
       FROM users
      WHERE email = :email_input OR username = :username_input
      LIMIT 1'
);

// 2. Pass the user's input variable to BOTH unique placeholders
$stmt->execute([
    ':email_input' => $identifier,    // Assuming $identifier is the variable holding their input
    ':username_input' => $identifier  // Pass the exact same variable again
]);
    $user = $stmt->fetch(); // Returns one row as an associative array, or false.

    // TEACHING NOTE: password_verify($plain, $hash) is the secure way to check
    // a password. NEVER compare plaintext passwords directly. password_hash()
    // uses bcrypt by default, which is intentionally slow and salted —
    // this defeats brute-force and rainbow-table attacks.
    if ($user && password_verify($password, $user['password_hash'])) {

        // TEACHING NOTE: session_regenerate_id(true) creates a brand-new session
        // ID and deletes the old one. This prevents "Session Fixation" attacks —
        // where an attacker pre-sets a session ID and waits for you to log in.
        session_regenerate_id(true);

        // Store key user info in the session — available on every subsequent page.
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['role']     = $user['role'];

        // TEACHING NOTE: "Remember Me" — we save only the email in a cookie.
        // setcookie() arguments: name, value, expiry (unix timestamp), path.
        // time() + 30*24*3600 = 30 days from now, in seconds.
        // We NEVER save the password in a cookie — that would be a security disaster.
        if (!empty($_POST['remember_me'])) {
            setcookie('ns_remember', $user['email'], time() + (30 * 24 * 3600), '/');
        } else {
            // If not checked, delete any existing remember cookie.
            setcookie('ns_remember', '', time() - 3600, '/');
        }

        // Redirect based on role
        $redirect = $user['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php';
        header('Location: ' . $redirect);
        exit();

    } else {
        // TEACHING NOTE: We give a vague error on purpose ("Invalid credentials")
        // rather than "Email not found" or "Wrong password." Specific messages
        // help attackers know which part of the attempt was correct (enumeration).
        flash('login_error', 'Invalid credentials. Please try again.');
        header('Location: index.php');
        exit();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE REGISTRATION (POST)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {

    $username  = trim($_POST['reg_username'] ?? '');
    $email     = trim($_POST['reg_email']    ?? '');
    $password  = trim($_POST['reg_password'] ?? '');
    $password2 = trim($_POST['reg_password2']?? '');

    // ── Validation chain ──
    // TEACHING NOTE: We validate one condition at a time. The moment one fails,
    // we flash the error, redirect (to show the register tab), and exit().
    // This is called "early return" or "guard clause" style.

    if (empty($username) || empty($email) || empty($password) || empty($password2)) {
        flash('reg_error', 'All fields are required.');
        flash('show_register', '1');
        header('Location: index.php');
        exit();
    }

    // TEACHING NOTE: filter_var() with FILTER_VALIDATE_EMAIL uses PHP's built-in
    // email format checker. It returns false if the email is malformed.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('reg_error', 'Please enter a valid email address.');
        flash('show_register', '1');
        header('Location: index.php');
        exit();
    }

    if (strlen($password) < 8) {
        flash('reg_error', 'Password must be at least 8 characters.');
        flash('show_register', '1');
        header('Location: index.php');
        exit();
    }

    if ($password !== $password2) {
        flash('reg_error', 'Passwords do not match.');
        flash('show_register', '1');
        header('Location: index.php');
        exit();
    }

    // Check if email or username already taken
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
    $stmt->execute([':email' => $email, ':username' => $username]);
    if ($stmt->fetch()) {
        flash('reg_error', 'That email or username is already registered.');
        flash('show_register', '1');
        header('Location: index.php');
        exit();
    }

    // TEACHING NOTE: password_hash() with PASSWORD_DEFAULT applies bcrypt
    // and automatically generates a unique salt. The resulting string
    // (e.g. "$2y$12$...") encodes the algorithm, cost factor, salt, AND hash —
    // everything password_verify() needs to check it later.
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // INSERT the new user — role defaults to 'user' (set in the DB schema).
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :h)'
    );
    $stmt->execute([':u' => $username, ':e' => $email, ':h' => $hash]);

    flash('reg_success', 'Account created! You can now log in.');
    header('Location: index.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// RENDER THE PAGE
// ─────────────────────────────────────────────────────────────────────────────
$page_title = 'Welcome';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NutriShift — Adaptive nutrition tracking built around your biological cycle.">
    <title>Welcome | NutriShift</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-wrapper">

        <!-- Brand / Logo -->
        <div class="auth-brand">
            <span class="logo-icon">⚡</span>
            <h1>NutriShift</h1>
            <p>Track nutrition by cycle, not the clock.</p>
        </div>

        <!-- Theme toggle (standalone, no nav on login page) -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:.75rem;">
            <button id="theme-toggle" class="btn-icon" aria-label="Toggle dark/light mode">
                <span id="theme-icon">🌙</span>
            </button>
        </div>

        <!-- Flash messages -->
        <?php if ($reg_success): ?>
            <div class="alert alert-success">✅ <?= sanitize($reg_success) ?></div>
        <?php endif; ?>

        <!-- Tab switcher -->
        <div class="auth-tabs" role="tablist">
            <button class="auth-tab <?= !$show_reg_tab ? 'active' : '' ?>" data-tab="login"    role="tab" id="tab-login">Sign In</button>
            <button class="auth-tab <?= $show_reg_tab  ? 'active' : '' ?>" data-tab="register" role="tab" id="tab-register">Create Account</button>
        </div>

        <!-- Hidden marker so JS can auto-switch to Register tab on error -->
        <?php if ($show_reg_tab): ?><span id="show-register" hidden></span><?php endif; ?>

        <!-- ── LOGIN PANEL ── -->
        <div class="card auth-panel <?= !$show_reg_tab ? 'active' : '' ?>" id="panel-login" role="tabpanel">
            <?php if ($login_error): ?>
                <div class="alert alert-error">⚠️ <?= sanitize($login_error) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php" novalidate>
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="identifier">Email or Username</label>
                    <!-- Pre-fill from Remember Me cookie if present -->
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        value="<?= sanitize($remembered_email) ?>"
                        autocomplete="username"
                        placeholder="you@example.com"
                        required>
                </div>

                <div class="form-group">
                    <label for="password">
                        Password
                        <span class="forgot-link text-muted text-small">Use "Admin@1234" for demo admin</span>
                    </label>
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember_me"
                               <?= !empty($remembered_email) ? 'checked' : '' ?>>
                        Remember me for 30 days (saves email only)
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="login-submit-btn">Sign In</button>
            </form>
        </div>

        <!-- ── REGISTER PANEL ── -->
        <div class="card auth-panel <?= $show_reg_tab ? 'active' : '' ?>" id="panel-register" role="tabpanel">
            <?php if ($reg_error): ?>
                <div class="alert alert-error">⚠️ <?= sanitize($reg_error) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php" novalidate>
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label for="reg_username">Username</label>
                    <input type="text" id="reg_username" name="reg_username" autocomplete="username" placeholder="nightowl42" required>
                </div>

                <div class="form-group">
                    <label for="reg_email">Email Address</label>
                    <input type="email" id="reg_email" name="reg_email" autocomplete="email" placeholder="you@example.com" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <input type="password" id="reg_password" name="reg_password" autocomplete="new-password" placeholder="Min. 8 chars" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="reg_password2">Confirm Password</label>
                        <input type="password" id="reg_password2" name="reg_password2" autocomplete="new-password" placeholder="Repeat password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="register-submit-btn">Create Account</button>
            </form>
        </div>

    </div><!-- /auth-wrapper -->
</div><!-- /auth-page -->

<script src="assets/js/main.js"></script>
</body>
</html>
