<?php
$pageTitle = 'Admin Login';

// Load init first (sessions, helpers, csrf) BEFORE any output
require_once __DIR__ . '/../partials/init.php';

// If already logged in, go straight to dashboard
if (is_admin()) {
  redirect_to('/admin/dashboard.php');
}

// Handle form submit
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['username'] ?? '');
  $pass = trim($_POST['password'] ?? '');
  $csrf = $_POST['csrf'] ?? '';

  $user = filter_input(INPUT_POST, "username", FILTER_SANITIZE_SPECIAL_CHARS);
  $password = filter_input(INPUT_POST, "password", FILTER_SANITIZE_SPECIAL_CHARS);

  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    $error = 'Invalid request. Please refresh and try again.';
  } else {
    $ADMIN_USER = getenv('SOM_ADMIN_USER') ?: 'admin';
    $ADMIN_PASS = getenv('SOM_ADMIN_PASS') ?: 'admin123'; // can be changed

    if ($user === $ADMIN_USER && hash_equals($ADMIN_PASS, $pass)) {
      session_regenerate_id(true);
      $_SESSION['admin_user'] = $user;
      // Use project-aware redirect to avoid /admin/ missing when app is in a subfolder
      redirect_to('/admin/dashboard.php');
    } else {
      if(!($user === $ADMIN_USER)) {
        $error = 'Incorrect username.';
      } elseif (!(hash_equals($ADMIN_PASS, $pass))) {
        $error = 'Incorrect password.';//shows at opening, if close tab dapat wala na
      }
    }
  }
}

// Only include the header AFTER redirects are settled
require_once __DIR__ . '/../partials/site_header.php';
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
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
