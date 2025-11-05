<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $user = trim($_POST['username'] ?? '');
  $pass = trim($_POST['password'] ?? '');

  $stmt = $pdo->prepare("SELECT USERNAME, PASSWORD_HASH FROM ADMIN_USER WHERE USERNAME=?");
  $stmt->execute([$user]);
  $row = $stmt->fetch();

  if ($row && (password_verify($pass, $row['PASSWORD_HASH']) || hash_equals($row['PASSWORD_HASH'], $pdo->query("SELECT PASSWORD(".$pdo->quote($pass).")")->fetchColumn()))) {
    $_SESSION['admin_user'] = $row['USERNAME'];
    header('Location: /admin/dashboard.php');
    exit;
  } else {
    $error = 'Invalid credentials.';
  }
}
?>
<!DOCTYPE html>
<html><head>
  <meta charset="utf-8"><title>Admin Login — School of Mary</title>
  <link href="/assets/styles.css" rel="stylesheet" />
</head>
<body>
  <div class="container" style="max-width:420px;margin-top:80px">
    <div class="detail">
      <h2>Admin Login</h2>
      <?php if($error): ?><p style="color:#b91c1c"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
      <form method="post">
        <label>Username</label>
        <input class="input" name="username" required />
        <label style="margin-top:8px">Password</label>
        <input class="input" name="password" type="password" required />
        <button class="btn" style="margin-top:12px" type="submit">Sign in</button>
      </form>
    </div>
  </div>
</body></html>
