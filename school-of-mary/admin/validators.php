<?php
function v_email($s){ 
    return filter_var($s, FILTER_VALIDATE_EMAIL) && strlen($s)<=35; 
}

function v_varchar($s,$max){ 
    return is_string($s) && $s!=='' && mb_strlen($s)<= $max; 
}

function v_char_nullable($s,$len){ 
    return $s==='' || (is_string($s) && mb_strlen($s)<= $len); 
}

function v_enum_exists(PDO $pdo, $table, $col, $val){
  $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $col=? LIMIT 1"); $stmt->execute([$val]); return (bool)$stmt->fetchColumn();
}

function v_date_nullable($s){ 
    if($s==='') return true; $d = date_create($s); return $d && $s===date_format($d,'Y-m-d'); 
}

function v_number_nullable($s){ 
    return $s==='' || is_numeric($s); 
}

function guardFail($msg){ 
    http_response_code(422); die($msg); 
}
