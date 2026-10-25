<?php
// login.php Staff Authentication Page
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';

if (is_logged_in()) {
    header("Location: /dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $res = db_query($conn, "SELECT * FROM MstrUsers WHERE UserName = ? AND UserStatus = 1 LIMIT 1", "s", [$username]);
        if ($res && db_num_rows($res) > 0) {
            $user = db_fetch_assoc($res);
            // Support standard password verify or fallback plain text check for initial default
            if (password_verify($password, $user['UserPassword']) || $password === $user['UserPassword']) {
                $_SESSION['user_id'] = $user['UserNumber'];
                $_SESSION['username'] = $user['UserName'];
                $_SESSION['user_level'] = $user['UserLevelNumber'];
                header("Location: /dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or account is inactive.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}

$page_title = "Staff Login";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-primary"><i class="fa-solid fa-book-bookmark me-2"></i>Libera</h3>
                    <p class="text-muted small">Library Management & Double-Entry Accounting</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-bold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                    </button>
                </form>
                <div class="mt-3 text-center">
                    <span class="text-muted small">Default credentials: <strong>admin</strong> / <strong>admin123</strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
