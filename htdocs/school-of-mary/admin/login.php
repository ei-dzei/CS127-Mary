<?php
$pageTitle = 'Admin Login';
require_once __DIR__ . '/../partials/site_header.php';

// If already logged in, go to dashboard
if (is_admin()) {
  header('Location: /admin/dashboard.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['username'] ?? '');
  $pass = trim($_POST['password'] ?? '');
  $csrf = $_POST['csrf'] ?? '';

  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    $error = 'Invalid request. Please refresh and try again.';
  } else {
    $ADMIN_USER = getenv('SOM_ADMIN_USER') ?: 'admin';
    $ADMIN_PASS = getenv('SOM_ADMIN_PASS') ?: 'admin123'; //can be changed

    if ($user === $ADMIN_USER && hash_equals($ADMIN_PASS, $pass)) {
      $_SESSION['admin_user'] = $user;
      header('Location: /admin/dashboard.php');
      exit;
    } else {
      $error = 'Incorrect username or password.';
    }
  }
}
?>

<section class="panel fade-in" style="max-width: 560px; margin: 24px auto;">
  <h1 style="margin-bottom:8px;">Admin Login</h1>
  <p class="muted" style="margin-bottom:12px;">Admins manage faculty, research, agencies, funding, and assignments.</p>

  <?php if ($error): ?>
    <div class="panel" style="background:#fff3f3; border-color:#f3c2c2; color:#7a1111; margin-bottom:10px;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">

    <div class="field" style="grid-column: span 12;">
      <label>Username</label>
      <input class="input" name="username" autocomplete="username" required>
    </div>

    <div class="field" style="grid-column: span 12;">
      <label>Password</label>
      <input class="input" name="password" type="password" autocomplete="current-password" required>
    </div>

    <div class="field" style="grid-column: span 12; display:flex; gap:8px;">
      <button class="btn" type="submit">Sign In</button>
      <a class="btn" href="/public/" style="background:#234b7a;">Back to Home</a>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
