<?php
session_start();
require_once __DIR__ . '/includes/config.php';
$count = (int)$dbh->query('SELECT COUNT(*) FROM admin')->fetchColumn();
if ($count > 0) { header('Location: admin-login.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($username) < 3 || strlen($username) > 100) $error = 'Username must be between 3 and 100 characters.';
    elseif (strlen($password) < 10) $error = 'Use a password with at least 10 characters.';
    elseif ($password !== $confirm) $error = 'Passwords do not match.';
    else {
        $stmt = $dbh->prepare('INSERT INTO admin (UserName, Password) VALUES (:username, :password)');
        $stmt->execute([':username' => $username, ':password' => password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['alogin'] = $username;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: modern/index.php'); exit;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up SRMS administrator</title><link rel="stylesheet" href="modern/css/style.css"></head><body class="setup-body"><main class="setup-card"><div class="brand-mark">SR</div><p class="eyebrow">FIRST-TIME SETUP</p><h1>Create administrator</h1><p class="setup-copy">Create the first admin account for this local installation. This page becomes unavailable after an account exists.</p><?php if ($error !== ''): ?><div class="setup-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="post"><label>Username<input name="username" required minlength="3" maxlength="100" autocomplete="username"></label><label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"></label><button class="btn btn-primary" type="submit">Create admin account</button></form></main></body></html>
