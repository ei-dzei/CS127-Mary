<?php

require_once __DIR__ . '/../partials/init.php';

// Only do logout if someone is logged in
if (is_admin()) {
  unset($_SESSION['admin_user']);

  // Wipe all session data and cookie for extra safety
  $_SESSION = [];
  // Delete session cookie
  // session_destroy() removes data on the server, but the browser still holds the Session ID cookie
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Overwrite the cookie with an empty value and set the expiration time to the past (time() - 42000) to ensure the browser deletes it
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  // Destroy the session data on the server storage
  session_destroy();
  // Explicitly close the write buffer
  session_write_close();
}

// Always redirect to public landing page
redirect_to('/public/');
