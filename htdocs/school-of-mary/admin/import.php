<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/validators.php';
session_start(); if (empty($_SESSION['admin_user'])) { http_response_code(403); die('Forbidden'); }
if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_FILES['csv'])) { http_response_code(400); die('Upload CSV'); }
if (!hash_equals($_POST['csrf'] ?? '', $_SESSION['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }

$defs = [
  'FACULTY'=> [
    'cols'=>['FACULTY_FNAME','FACULTY_INITIAL','FACULTY_LNAME','FACULTY_EMAIL','RANK_ID','DEPT_ID'],
    'validate'=>function($pdo,$r){
      if (!v_varchar($r['FACULTY_FNAME'],50)) return 'First name';
      if (!v_char_nullable($r['FACULTY_INITIAL'],2)) return 'Initial';
      if (!v_varchar($r['FACULTY_LNAME'],50)) return 'Last name';
      if (!v_email($r['FACULTY_EMAIL'])) return 'Email';
      if (!v_enum_exists($pdo,'`RANK`','RANK_ID',$r['RANK_ID'])) return 'Rank';
      if (!v_enum_exists($pdo,'DEPARTMENT','DEPT_ID',$r['DEPT_ID'])) return 'Department';
      return true;
    },
    'insert'=>"INSERT INTO FACULTY (FACULTY_FNAME,FACULTY_INITIAL,FACULTY_LNAME,FACULTY_EMAIL,RANK_ID,DEPT_ID) VALUES (?,?,?,?,?,?)"
  ],
  'RESEARCH'=> [
    'cols'=>['RESEARCH_TITLE','RESEARCH_STARTDATE','RESEARCH_ENDDATE','RESEARCH_STATUS'],
    'validate'=>function($pdo,$r){
      if (!v_varchar($r['RESEARCH_TITLE'],255)) return 'Title';
      if (!v_date_nullable($r['RESEARCH_STARTDATE']) || $r['RESEARCH_STARTDATE']==='') return 'Start date';
      if (!v_date_nullable($r['RESEARCH_ENDDATE'])) return 'End date';
      if (!v_enum_exists($pdo,'RESEARCH_STATUS','STATUS_CODE',$r['RESEARCH_STATUS'])) return 'Status';
      return true;
    },
    'insert'=>"INSERT INTO RESEARCH (RESEARCH_TITLE,RESEARCH_STARTDATE,RESEARCH_ENDDATE,RESEARCH_STATUS) VALUES (?,?,?,?)"
  ],
  'AGENCY'=> [
    'cols'=>['AGENCY_NAME','AGENCY_TYPE','AGENCY_CONTACTINFO'],
    'validate'=>function($pdo,$r){
      if (!v_varchar($r['AGENCY_NAME'],255)) return 'Name';
      if (!v_enum_exists($pdo,'TYPE_AGENCY','TYPE_CODE',$r['AGENCY_TYPE'])) return 'Type';
      if (!v_varchar($r['AGENCY_CONTACTINFO'],35)) return 'Contact';
      return true;
    },
    'insert'=>"INSERT INTO AGENCY (AGENCY_NAME,AGENCY_TYPE,AGENCY_CONTACTINFO) VALUES (?,?,?)"
  ],
  'FUNDING'=> [
    'cols'=>['RESEARCH_ID','AGENCY_ID','FUNDING_AMOUNT','DATE_FUNDED'],
    'validate'=>function($pdo,$r){
      if (!v_enum_exists($pdo,'RESEARCH','RESEARCH_ID',$r['RESEARCH_ID'])) return 'Research';
      if (!v_enum_exists($pdo,'AGENCY','AGENCY_ID',$r['AGENCY_ID'])) return 'Agency';
      if (!v_number_nullable($r['FUNDING_AMOUNT'])) return 'Amount';
      if (!v_date_nullable($r['DATE_FUNDED'])) return 'Date funded';
      return true;
    },
    'insert'=>"INSERT INTO FUNDING (RESEARCH_ID,AGENCY_ID,FUNDING_AMOUNT,DATE_FUNDED) VALUES (?,?,?,?)"
  ],
  'ASSIGNMENT'=> [
    'cols'=>['FACULTY_ID','RESEARCH_ID','ROLE_ID','DATE_ASSIGNED'],
    'validate'=>function($pdo,$r){
      if (!v_enum_exists($pdo,'FACULTY','FACULTY_ID',$r['FACULTY_ID'])) return 'Faculty';
      if (!v_enum_exists($pdo,'RESEARCH','RESEARCH_ID',$r['RESEARCH_ID'])) return 'Research';
      if (!v_enum_exists($pdo,'ROLE','ROLE_ID',$r['ROLE_ID'])) return 'Role';
      if (!v_date_nullable($r['DATE_ASSIGNED']) || $r['DATE_ASSIGNED']==='') return 'Date assigned';
      return true;
    },
    'insert'=>"INSERT INTO ASSIGNMENT (FACULTY_ID,RESEARCH_ID,ROLE_ID,DATE_ASSIGNED) VALUES (?,?,?,?)"
  ],
];

$table = strtoupper($_POST['table'] ?? '');
if (!isset($defs[$table])) { http_response_code(400); die('Bad table'); }
$def = $defs[$table];

$fh = fopen($_FILES['csv']['tmp_name'],'r');
$header = fgetcsv($fh);
if ($header!==$def['cols']) { http_response_code(422); die('CSV headers mismatch. Expected: '.implode(',',$def['cols'])); }

$pdo->beginTransaction();
try{
  $stmt = $pdo->prepare($def['insert']);
  $rownum=1; $ok=0;
  while(($row=fgetcsv($fh))!==false){
    $rownum++;
    $rec = array_combine($def['cols'],$row);
    $valid = $def['validate']($pdo,$rec);
    if ($valid!==true) throw new Exception("Row $rownum invalid: $valid");
    foreach($rec as $k=>$v){ if ($v==='') $rec[$k]=null; }
    $stmt->execute(array_values($rec));
    $ok++;
  }
  $pdo->commit();
  echo "Imported $ok rows into $table.";
} catch(Throwable $e){
  $pdo->rollBack();
  http_response_code(422);
  echo "Import failed: ".$e->getMessage();
}
