<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $apdo = get_admin_pdo();
        $stmt = $apdo->prepare("
            SELECT u.user_id, u.full_name, u.password_hash, u.account_status, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['account_status'] !== 'active') {
            $error = 'Your account is not active.';
        } elseif ($password !== $user['password_hash']) {
            $error = 'Invalid email or password.';
        } else {
        
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role_name'];

            set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Find My Doctor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Find My Doctor</h1>
    <div class="nav-right">
        <a href="register.php">Register</a>
    </div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Login</h2>

        <?php echo display_flash(); ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <p style="margin-top:20px; text-align:center;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</div>
</body>
</html>
