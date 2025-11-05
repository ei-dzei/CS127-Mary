<?php
require_once __DIR__ . '/config/db.php';
session_start(); if (empty($_SESSION['admin_user'])) { http_response_code(403); die('Forbidden'); }
$rows = $pdo->query("SELECT * FROM AUDIT_LOG ORDER BY LOG_ID DESC LIMIT 200")->fetchAll();
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"><title>Audit Log — Print</title>
<style>
  body{
    font:14px system-ui,Segoe UI,Roboto,Arial
    }

  h1{
    margin:0 0 10px
    }

  table{
    width:100%;
    border-collapse:collapse
    }

  th,td{
    border:1px solid #ddd;
    padding:6px
    }

  @media print {
    .noprint{display:none}
    }
</style>
</head>
<body>
  <div class="noprint" style="margin:10px 0"><button onclick="window.print()">Print</button></div>
  <h1>Audit Log (latest 200)</h1>
  <table>
    <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Table</th><th>PK</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['LOG_ID']; ?></td>
        <td><?php echo htmlspecialchars($r['ACTOR']); ?></td>
        <td><?php echo htmlspecialchars($r['ACTION_ENUM']); ?></td>
        <td><?php echo htmlspecialchars($r['TABLE_NAME']); ?></td>
        <td><?php echo htmlspecialchars($r['PK_VALUE']); ?></td>
        <td><?php echo htmlspecialchars($r['CREATED_AT']); ?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</body></html>
