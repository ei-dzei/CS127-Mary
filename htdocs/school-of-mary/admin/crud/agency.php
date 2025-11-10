<?php
$pageTitle = 'Agencies (Admin)';
require_once __DIR__ . '/../../partials/site_header.php';
require_once __DIR__ . '/../../validators.php';

if (!is_admin()) { header('Location: /admin/login.php'); exit; }
csrf_check();

$action = $_POST['action'] ?? '';

if ($action==='create') {
  if (!v_varchar($_POST['AGENCY_NAME'],255)) guardFail('Invalid name');
  if (!v_enum_exists($pdo,'TYPE_AGENCY','TYPE_CODE',$_POST['AGENCY_TYPE'])) guardFail('Invalid type');
  if (!v_varchar($_POST['AGENCY_CONTACTINFO'],35)) guardFail('Invalid contact');

  $sql = "INSERT INTO AGENCY (AGENCY_NAME, AGENCY_TYPE, AGENCY_CONTACTINFO) VALUES (?,?,?)";
  $pdo->prepare($sql)->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','AGENCY']);
  header('Location: agency.php?ok=1'); exit;
}
if ($action==='update') {
  if (!v_int($_POST['AGENCY_ID'])) guardFail('Missing ID');
  if (!v_varchar($_POST['AGENCY_NAME'],255)) guardFail('Invalid name');
  if (!v_enum_exists($pdo,'TYPE_AGENCY','TYPE_CODE',$_POST['AGENCY_TYPE'])) guardFail('Invalid type');
  if (!v_varchar($_POST['AGENCY_CONTACTINFO'],35)) guardFail('Invalid contact');

  $sql = "UPDATE AGENCY SET AGENCY_NAME=?, AGENCY_TYPE=?, AGENCY_CONTACTINFO=? WHERE AGENCY_ID=?";
  $pdo->prepare($sql)->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO'], $_POST['AGENCY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','AGENCY', $_POST['AGENCY_ID']]);
  header('Location: agency.php?ok=1'); exit;
}
if ($action==='delete') {
  if (!v_int($_POST['AGENCY_ID'])) guardFail('Missing ID');
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

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Agencies</h1>
  <p class="muted" style="margin-bottom:8px;">Manage agencies and their types. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="/admin/api/export.php?table=AGENCY">Export CSV</a>
    <form method="post" action="/admin/api/import.php" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="AGENCY">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:10px;">
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
</section>

<section class="panel" style="margin-bottom:16px;">
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
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Contact</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['AGENCY_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo htmlspecialchars($row['TYPE_LABEL']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?></td>
        <td style="white-space:nowrap">
          <button class="btn small"
                  data-modal="edit" data-title="Edit Agency"
                  data-template="#tpl-edit-<?php echo (int)$row['AGENCY_ID'];?>"
                  data-action="update"
                  data-hidden-AGENCY_ID="<?php echo (int)$row['AGENCY_ID']; ?>">
            Quick Edit
          </button>
          <form method="post" onsubmit="return confirm('Delete agency?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">
            <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
          </form>

          <template id="tpl-edit-<?php echo (int)$row['AGENCY_ID'];?>">
            <div class="grid">
              <div class="field" style="grid-column: span 6"><label>Name</label><input class="input" name="AGENCY_NAME" value="<?php echo htmlspecialchars($row['AGENCY_NAME']); ?>" required></div>
              <div class="field" style="grid-column: span 3">
                <label>Type</label>
                <select class="input" name="AGENCY_TYPE" required>
                  <?php foreach($types as $t){ $sel=$t['TYPE_CODE']===$row['AGENCY_TYPE']?' selected':''; echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>'; } ?>
                </select>
              </div>
              <div class="field" style="grid-column: span 3"><label>Contact</label><input class="input" name="AGENCY_CONTACTINFO" value="<?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?>" required></div>
            </div>
          </template>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</section>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
