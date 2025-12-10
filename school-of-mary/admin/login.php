<?php
// Page Title
$pageTitle = 'Login | Admin';

// Load init first (sessions, helpers, csrf) before any output
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
    // Verify Credentials
    // hash_equals() is used for the password check to prevent Timing Attacks
    if ($user === $ADMIN_USER && hash_equals($ADMIN_PASS, $pass)) {
      // Generate a new Session ID upon successful login so the user annot be tracked using a previous ID. 'true' deletes the old session file
      session_regenerate_id(true);
      // Set the session flag indicating the user is an admin
      $_SESSION['admin_user'] = $user;
      // Use project-aware redirect to avoid /admin/ missing when app is in a subfolder
      redirect_to('/admin/dashboard.php');
    } else {
      // Login Failed
      if(!($user === $ADMIN_USER)) {
        $error = 'Incorrect username.';
      } elseif (!(hash_equals($ADMIN_PASS, $pass))) {
        $error = 'Incorrect password.'; // shows at opening, if close tab dapat wala na
      }
    }
  }
}

// Only include the header after redirects are settled
require_once __DIR__ . '/../partials/site_header.php';
?>

<style>
  .panel {box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;}
  
  #caps-lock-indicator {
    display: none; /* Initially hidden */
    color: #4b5563; 
    width: 40px; 
    height: 40px;
    border-radius: 50%;
    margin-left: 1em;
    
    /* Center the arrow inside the circle */
    justify-content: center;
    align-items: center;
    
    /* Positioning near the password field */
    position: absolute;
    right: 0; 
    top: 50%; 
    transform: translateY(-50%);
    
    cursor: default; 
  }

  #caps-lock-indicator::before {
    content: '⇧'; 
    font-size: 1.5em; 
    line-height: 1; /* Keep the icon centered */
  }

  /* Style for the password field container to align elements */
  .password-field-container {
    display: flex;
    align-items: center;
    position: relative; /* Allows absolute positioning of the indicator */
  }

  /* Ensure the input takes up full width available */
  .password-field-container .input {
    width: 100%;
  }
</style>

<section class="panel fade-in" style="max-width: 560px; margin: 24px auto;">
  <h1 style="margin-bottom:8px;">Admin Login</h1>
  <p class="muted" style="margin-bottom:12px;">Admins view and manage database records in real time.</p>

  <?php if ($error): ?>
    <div class="panel" style="background:#fff3f3; border-color:#f3c2c2; color:#7a1111; margin-bottom:10px;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">

    <div class="field" style="grid-column: span 12;">
      <label>Username</label>
      <input class="input" name="username" autocomplete="username" required style="font-family: 'Newsreader', serif;">
    </div>

    <div class="field" style="grid-column: span 12;">
      <label>Password</label>
      <div class="password-field-container">
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        
        <span id="caps-lock-indicator" title="Caps Lock is On">
          </span>
      </div>
    </div>

    <div class="field" style="grid-column: span 12; display:flex; gap:8px;">
      <button class="btn" type="submit">Log In</button>
    </div>
  </form>
</section>

<script>
  // Caps Lock Detection Script
  document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const indicator = document.getElementById('caps-lock-indicator');

    if (!passwordInput || !indicator) {
      return;
    }

    // Function to check and update the indicator state
    function checkCapsLock(event) {
      const isCapsLockOn = event.getModifierState('CapsLock');

      if (isCapsLockOn) {
        // Use 'flex' instead of 'block' to match the CSS display property
        indicator.style.display = 'flex'; 
      } else {
        indicator.style.display = 'none'; 
      }
    }

    // Check on keypress/keyup inside the password field
    passwordInput.addEventListener('keydown', checkCapsLock);
    passwordInput.addEventListener('keyup', checkCapsLock);

    // Also check on focus, in case Caps Lock was pressed before entering the field
    passwordInput.addEventListener('focus', function(event) {
        setTimeout(() => checkCapsLock(event), 50);
    });

    // Hide the indicator when the user leaves the field
    passwordInput.addEventListener('blur', function() {
      indicator.style.display = 'none';
    });
  });
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>