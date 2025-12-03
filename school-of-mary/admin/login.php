<?php
$pageTitle = 'Login | Admin';

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

<style>
  .panel {box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;}
  /* Styling for the large, blue, circular indicator */
  #caps-lock-indicator {
    /* Style the button container */
    display: none; /* Initially hidden */
    /* background-color: #007bff; Bright blue background */
    color: #4b5563; /* White arrow */
    width: 40px; /* Size of the circle */
    height: 40px; /* Size of the circle */
    border-radius: 50%; /* Make it perfectly circular */
    margin-left: 1em;
    
    /* Center the arrow inside the circle */
    justify-content: center;
    align-items: center;
    
    /* Positioning near the password field */
    position: absolute; /* Position it relative to the parent container */
    right: 0; /* Adjust this value to position it correctly outside the field */
    top: 50%; /* Start at the vertical center */
    transform: translateY(-50%); /* Shift up by half its height to perfectly center */
    
    /* Visual enhancements */
    /* box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); Subtle shadow for depth */
    cursor: default; /* Indicate it's not clickable */
  }

  #caps-lock-indicator::before {
    /* Use a larger font size for the arrow icon */
    content: '⇧'; /* Using the Unicode up-arrow ⇧ or use &#8679; */
    font-size: 1.5em; 
    line-height: 1; /* Keep the icon centered */
  }

  /* Style for the password field container to align elements */
  .password-field-container {
    display: flex;
    align-items: center;
    position: relative; /* CRITICAL: Allows absolute positioning of the indicator */
  }

  /* Ensure the input takes up full width available */
  .password-field-container .input {
    width: 100%;
  }
</style>

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
  /**
   * Caps Lock Detection Script (Unchanged)
   */
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