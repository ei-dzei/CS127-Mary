<?php
/* Validators used by admin CRUD */

function guardFail($msg='Invalid input'){
  http_response_code(400);
  echo '<section class="panel"><h3>Error</h3><p class="muted">'.htmlspecialchars($msg).'</p><p><a class="btn" href="javascript:history.back()">Back</a></p></section>';
  require_once __DIR__ . '/partials/site_footer.php';
  exit;
}

function csrf_token(){
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function csrf_check(){
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = $_POST['csrf'] ?? '';
    $sess = $_SESSION['csrf'] ?? '';
    if (!$in || !$sess || !hash_equals($sess, $in)) {
      guardFail('Invalid request token. Please retry.');
    }
  }
}

function v_int($v){
  return isset($v) && preg_match('/^\d+$/', (string)$v);
}
function v_decimal_nullable($v){
  if ($v === '' || $v === null) return true;
  return preg_match('/^\d+(\.\d{1,2})?$/', (string)$v);
}
function v_date($v){
  if (!$v) return false;
  $d = DateTime::createFromFormat('Y-m-d', $v);
  return $d && $d->format('Y-m-d') === $v;
}
function v_date_nullable($v){
  return ($v==='' || $v===null) ? true : v_date($v);
}
function v_varchar($v,$len){
  return isset($v) && mb_strlen(trim($v))>0 && mb_strlen($v) <= $len;
}
function v_char_nullable($v,$len){
  if ($v==='' || $v===null) return true;
  return mb_strlen($v) <= $len;
}
function v_email($v){
  return (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
}
function v_enum_exists(PDO $pdo, $table, $col, $value){
  $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $col = ? LIMIT 1");
  $stmt->execute([$value]);
  return (bool)$stmt->fetchColumn();
}
