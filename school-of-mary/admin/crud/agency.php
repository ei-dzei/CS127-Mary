<?php
$pageTitle = 'Agencies (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
csrf_check();

$action = $_POST['action'] ?? '';

if ($action==='create') {
  $sql = "INSERT INTO AGENCY (AGENCY_NAME, AGENCY_TYPE, AGENCY_CONTACTINFO) VALUES (?,?,?)";
  $pdo->prepare($sql)->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','AGENCY']);
  header('Location: agency.php?ok=1'); exit;
}
if ($action==='update') {
  $sql = "UPDATE AGENCY SET AGENCY_NAME=?, AGENCY_TYPE=?, AGENCY_CONTACTINFO=? WHERE AGENCY_ID=?";
  $pdo->prepare($sql)->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO'], $_POST['AGENCY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','AGENCY', $_POST['AGENCY_ID']]);
  header('Location: agency.php?ok=1'); exit;
}
if ($action==='delete') {
  $pdo->prepare("DELETE FROM AGENCY WHERE AGENCY_ID=?")->execute([$_POST['AGENCY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'DELETE','AGENCY', $_POST['AGENCY_ID']]);
  header('Location: agency.php?ok=1'); exit;
}

/* Lookups */
$types = $pdo->query("SELECT TYPE_CODE, TYPE_LABEL FROM TYPE_AGENCY ORDER BY TYPE_LABEL")->fetchAll();

/* Filters */
$q = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');

$sql = "SELECT a.*, t.TYPE_LABEL
        FROM AGENCY a
        LEFT JOIN TYPE_AGENCY t ON a.AGENCY_TYPE = t.TYPE_CODE
        WHERE 1=1";
$params=[];
if ($q!==''){ $sql.=" AND a.AGENCY_NAME LIKE ?"; $params[]="%$q%"; }
if ($type!==''){ $sql.=" AND a.AGENCY_TYPE=?"; $params[]=$type; }
$sql.=" ORDER BY a.AGENCY_NAME LIMIT 60";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?>

<h1>Agencies</h1>

<div class="panel" style="margin-bottom:16px">
  <form method="get" class="grid">
    <div class="field" style="grid-column: span 6"><label>Name</label><input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>"></div>
    <div class="field" style="grid-column: span 4">
      <label>Type</label>
      <select class="input" name="type">
        <option value="">All</option>
        <?php foreach($types as $t){ $sel=$type===$t['TYPE_CODE']?' selected':''; echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>
</div>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-top:0">Create Agency</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="grid-column: span 6"><label>Name</label><input class="input" name="AGENCY_NAME" required></div>
    <div class="field" style="grid-column: span 3">
      <label>Type</label>
      <select class="input" name="AGENCY_TYPE" required>
        <?php foreach($types as $t){ echo '<option value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3"><label>Contact</label><input class="input" name="AGENCY_CONTACTINFO" required></div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Add</button></div>
  </form>
</div>

<div class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table style="width:100%;border-collapse:collapse">
    <thead><tr><th align="left">ID</th><th align="left">Name</th><th align="left">Type</th><th align="left">Contact</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['AGENCY_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo htmlspecialchars($row['TYPE_LABEL']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">
            <input type="hidden" name="AGENCY_NAME" value="<?php echo htmlspecialchars($row['AGENCY_NAME']); ?>">
            <input type="hidden" name="AGENCY_TYPE" value="<?php echo htmlspecialchars($row['AGENCY_TYPE']); ?>">
            <input type="hidden" name="AGENCY_CONTACTINFO" value="<?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?>">
            <button class="btn" style="padding:6px 10px">Save (no changes)</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete agency?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">
            <button class="btn" style="padding:6px 10px;background:#b91c1c;border-color:#b91c1c">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
