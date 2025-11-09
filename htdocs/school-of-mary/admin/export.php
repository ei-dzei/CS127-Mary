<?php
require_once __DIR__ . '/config/db.php';
session_start(); if (empty($_SESSION['admin_user'])) { http_response_code(403); die('Forbidden'); }

$allow = [
  'FACULTY'=> ['FACULTY_FNAME','FACULTY_INITIAL','FACULTY_LNAME','FACULTY_EMAIL','RANK_ID','DEPT_ID'],
  'RESEARCH'=> ['RESEARCH_TITLE','RESEARCH_STARTDATE','RESEARCH_ENDDATE','RESEARCH_STATUS'],
  'AGENCY'=> ['AGENCY_NAME','AGENCY_TYPE','AGENCY_CONTACTINFO'],
  'FUNDING'=> ['RESEARCH_ID','AGENCY_ID','FUNDING_AMOUNT','DATE_FUNDED'],
  'ASSIGNMENT'=> ['FACULTY_ID','RESEARCH_ID','ROLE_ID','DATE_ASSIGNED'],
];
$table = strtoupper($_GET['table'] ?? '');
if (!isset($allow[$table])) { http_response_code(400); die('Bad table'); }

$cols = $allow[$table];
$csvname = strtolower($table).'-'.date('Ymd-His').'.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$csvname.'"');

$out = fopen('php://output','w');
fputcsv($out,$cols);
$q = $pdo->query("SELECT ".implode(',',$cols)." FROM $table");
while($row = $q->fetch(PDO::FETCH_ASSOC)){ fputcsv($out, $row); }
fclose($out);
