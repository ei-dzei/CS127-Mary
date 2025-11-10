<?php
$pageTitle = 'Research (Admin)';
require_once __DIR__ . '/../../partials/site_header.php';
require_once __DIR__ . '/../../validators.php';

if (!is_admin()) { header('Location: /admin/login.php'); exit; }
csrf_check();

/* Actions */
$action = $_POST['action'] ?? '';

if ($action==='create') {
  if (!v_varchar($_POST['RESEARCH_TITLE'],255)) guardFail('Invalid title');
  if (!v_date($_POST['RESEARCH_STARTDATE'])) guardFail('Invalid start date');
  if (!v_date_nullable($_POST['RESEARCH_ENDDATE'])) guardFail('Invalid end date');
  if (!v_enum_exists($pdo,'RESEARCH_STATUS','STATUS_CODE',$_POST['RESEARCH_STATUS'])) guardFail('Invalid status');

  $sql = "INSERT INTO RESEARCH (RESEARCH_TITLE, RESEARCH_STARTDATE, RESEARCH_ENDDATE, RESEARCH_STATUS)
          VALUES (?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_TITLE'], $_POST['RESEARCH_STARTDATE'],
    $_POST['RESEARCH_ENDDATE'] !== '' ? $_POST['RESEARCH_ENDDATE'] : null,
    $_POST['RESEARCH_STATUS']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','RESEARCH']);
  header('Location: research.php?ok=1'); exit;
}

if ($action==='update') {
  if (!v_int($_POST['RESEARCH_ID'])) guardFail('Missing ID');
  if (!v_varchar($_POST['RESEARCH_TITLE'],255)) guardFail('Invalid title');
  if (!v_date($_POST['RESEARCH_STARTDATE'])) guardFail('Invalid start date');
  if (!v_date_nullable($_POST['RESEARCH_ENDDATE'])) guardFail('Invalid end date');
  if (!v_enum_exists($pdo,'RESEARCH_STATUS','STATUS_CODE',$_POST['RESEARCH_STATUS'])) guardFail('Invalid status');

  $sql = "UPDATE RESEARCH SET RESEARCH_TITLE=?, RESEARCH_STARTDATE=?, RESEARCH_ENDDATE=?, RESEARCH_STATUS=?
          WHERE RESEARCH_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_TITLE'], $_POST['RESEARCH_STARTDATE'],
    $_POST['RESEARCH_ENDDATE'] !== '' ? $_POST['RESEARCH_ENDDATE'] : null,
    $_POST['RESEARCH_STATUS'], $_POST['RESEARCH_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','RESEARCH', $_POST['RESEARCH_ID']]);
  header('Location: research.php?ok=1'); exit;
}

if ($action==='delete') {
  if (!v_int($_POST['RESEARCH_ID'])) guardFail('Missing ID');
  $pdo->prepare("DELETE FROM RESEARCH WHERE RESEARCH_ID=?")->execute([$_POST['RESEARCH_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'DELETE','RESEARCH', $_POST['RESEARCH_ID']]);
  header('Location: research.php?ok=1'); exit;
}

/* Lookups */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

/* Filters */
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$sql = "SELECT * FROM RESEARCH WHERE 1=1";
$params=[];
if ($q!==''){ $sql.=" AND RESEARCH_TITLE LIKE ?"; $params[]="%$q%"; }
if ($status!==''){ $sql.=" AND RESEARCH_STATUS=?"; $params[]=$status; }
if ($from!==''){ $sql.=" AND RESEARCH_STARTDATE>=?"; $params[]=$from; }
if ($to!==''){ $sql.=" AND (RESEARCH_ENDDATE <= ? OR (RESEARCH_ENDDATE IS NULL AND RESEARCH_STARTDATE <= ?))"; array_push($params,$to,$to); }
$sql.=" ORDER BY RESEARCH_STARTDATE DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?>

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Research</h1>
  <p class="muted" style="margin-bottom:8px;">Manage research, status and dates. CSV import/export below.</p>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="/admin/api/export.php?table=RESEARCH">Export CSV</a>
    <form method="post" action="/admin/api/import.php" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="RESEARCH">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:8px;">
    <div class="field" style="grid-column: span 4"><label>Title</label><input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>"></div>
    <div class="field" style="grid-column: span 3">
      <label>Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php foreach($statuses as $s){ $sel=$status===$s['STATUS_CODE']?' selected':''; echo '<option'.$sel.' value="'.$s['STATUS_CODE'].'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2"><label>Start from</label><input class="input" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></div>
    <div class="field" style="grid-column: span 2"><label>End by</label><input class="input" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></div>
    <div class="field" style="grid-column: span 1;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>
</section>

<section class="panel" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Research</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="grid-column: span 8"><label>Title</label><input class="input" name="RESEARCH_TITLE" required></div>
    <div class="field" style="grid-column: span 2"><label>Start</label><input class="input" type="date" name="RESEARCH_STARTDATE" required></div>
    <div class="field" style="grid-column: span 2"><label>End</label><input class="input" type="date" name="RESEARCH_ENDDATE"></div>
    <div class="field" style="grid-column: span 3">
      <label>Status</label>
      <select class="input" name="RESEARCH_STATUS" required>
        <?php foreach($statuses as $s){ echo '<option value="'.$s['STATUS_CODE'].'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Add</button></div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table>
    <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Start</th><th>End</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['RESEARCH_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_STATUS']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_STARTDATE']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_ENDDATE']); ?></td>
        <td style="white-space:nowrap">
          <!-- modal edit -->
          <button class="btn small"
                  data-modal="edit" data-title="Edit Research"
                  data-template="#tpl-edit-<?php echo (int)$row['RESEARCH_ID'];?>"
                  data-action="update"
                  data-hidden-RESEARCH_ID="<?php echo (int)$row['RESEARCH_ID']; ?>">
            Quick Edit
          </button>

          <!-- delete -->
          <form method="post" onsubmit="return confirm('Delete research?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">
            <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
          </form>

          <!-- template -->
          <template id="tpl-edit-<?php echo (int)$row['RESEARCH_ID']; ?>">
            <div class="grid">
              <div class="field" style="grid-column: span 8"><label>Title</label><input class="input" name="RESEARCH_TITLE" value="<?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?>" required></div>
              <div class="field" style="grid-column: span 2"><label>Start</label><input class="input" type="date" name="RESEARCH_STARTDATE" value="<?php echo htmlspecialchars($row['RESEARCH_STARTDATE']); ?>" required></div>
              <div class="field" style="grid-column: span 2"><label>End</label><input class="input" type="date" name="RESEARCH_ENDDATE" value="<?php echo htmlspecialchars($row['RESEARCH_ENDDATE']); ?>"></div>
              <div class="field" style="grid-column: span 3">
                <label>Status</label>
                <select class="input" name="RESEARCH_STATUS" required>
                  <?php foreach($statuses as $s){ $sel=$s['STATUS_CODE']===$row['RESEARCH_STATUS']?' selected':''; echo '<option'.$sel.' value="'.$s['STATUS_CODE'].'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>'; } ?>
                </select>
              </div>
            </div>
          </template>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</section>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
