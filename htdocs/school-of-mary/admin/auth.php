<?php
require_once __DIR__ . '/../config/utils.php';
require_once __DIR__ . '/../config/db.php';

$ADMIN_USER = 'admin@schoolofmary.edu';
$ADMIN_PASS = '01234'; 

function require_admin(){
  if (empty($_SESSION['admin_user'])) redirect('/schoolofmary/admin/login.php');
}
function try_login($email, $password){
  global $ADMIN_USER, $ADMIN_PASS;
  if ($email === $ADMIN_USER && $password === $ADMIN_PASS){
    $_SESSION['admin_user'] = $email;
    return true;
  }
  return false;
}
function logout_admin(){ unset($_SESSION['admin_user']); session_regenerate_id(true); }
