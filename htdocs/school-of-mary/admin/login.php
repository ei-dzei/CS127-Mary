<?php
require_once __DIR__ . '/../config/utils.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $ok = try_login(trim($_POST['email']??''), trim($_POST['password']??''));
  if ($ok) redirect('/dashboard.php');
  $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/admin.css">
<title>Admin Login</title>
</head><body style="display:grid;place-items:center;min-height:100vh;background:#f8fafc">
  <form method="post" style="width:min(420px,92vw)" class="card">
    <h1>Admin Login</h1>
    <?php if (!empty($error)): ?>
      <div class="panel" style="border-left:4px solid #b91c1c;color:#7f1d1d"><?php echo htmlspecialchars($error);?></div>
    <?php endif; ?>
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <div class="field"><label>Email</label><input class="input" type="email" name="email" required></div>
    <div class="field"><label>Password</label><input class="input" type="password" name="password" required></div>
    <div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn primary">Login</button></div>
  </form>
</body></html>
