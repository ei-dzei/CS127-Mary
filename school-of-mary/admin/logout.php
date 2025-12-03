<?php

require_once __DIR__ . '/../partials/init.php';

// Only do logout if someone is logged in
if (is_admin()) {
  unset($_SESSION['admin_user']);

  // Wipe all session data and cookie for extra safety
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  session_destroy();
  session_write_close();
}

// Always redirect to public landing page
redirect_to('/public/');
