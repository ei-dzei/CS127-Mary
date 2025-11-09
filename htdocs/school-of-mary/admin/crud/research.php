<?php
$pageTitle = 'Research (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
require_once __DIR__ . '/../validators.php';

$fkExists = function(PDO $pdo, string $table, string $col, $val): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$col}=? LIMIT 1");
  $stmt->execute([$val]);
  return (bool)$stmt->fetchColumn();
};
$isDate = function($s): bool {
  if ($s === null || $s === '') return false;
  $d = date_create($s);
  return $d && $s === $d->format('Y-m-d');
};

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  if ($action === 'create' || $action === 'update') {
    // Validate inputs
    if (!v_varchar($_POST['RESEARCH_TITLE'] ?? '', 255)) guardFail('Invalid title (max 255)');
    if (!$isDate($_POST['RESEARCH_STARTDATE'] ?? '')) guardFail('Invalid start date (YYYY-MM-DD)');
    $end = $_POST['RESEARCH_ENDDATE'] ?? null;
    if ($end !== null && $end !== '' && !$isDate($end)) guardFail('Invalid end date (YYYY-MM-DD)');
    if (!v_enum_exists($pdo, 'RESEARCH_STATUS', 'STATUS_CODE', $_POST['RESEARCH_STATUS'] ?? '')) guardFail('Invalid status');

    // Start <= End (if end given)
    if ($end !== null && $end !== '') {
      if (strtotime($end) < strtotime($_POST['RESEARCH_STARTDATE'])) {
        guardFail('End date cannot be earlier than start date');
      }
    }
  }

  if ($action === 'create') {
    $sql = "INSERT INTO RESEARCH (RESEARCH_TITLE, RESEARCH_STARTDATE, RESEARCH_ENDDATE, RESEARCH_STATUS)
            VALUES (?,?,?,?)";
    $pdo->prepare($sql)->execute([
      $_POST['RESEARCH_TITLE'],
      $_POST['RESEARCH_STARTDATE'],
      ($_POST['RESEARCH_ENDDATE'] === '' ? null : $_POST['RESEARCH_ENDDATE']),
      $_POST['RESEARCH_STATUS']
    ]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
        ->execute([$_SESSION['admin_user'],'CREATE','RESEARCH']);
    header('Location: research.php?ok=1'); exit;
  }

  if ($action === 'update') {
    if (!isset($_POST['RESEARCH_ID'])) guardFail('Missing ID');
    $sql = "UPDATE RESEARCH
            SET RESEARCH_TITLE=?, RESEARCH_STARTDATE=?, RESEARCH_ENDDATE=?, RESEARCH_STATUS=?
            WHERE RESEARCH_ID=?";
    $pdo->prepare($sql)->execute([
      $_POST['RESEARCH_TITLE'],
      $_POST['RESEARCH_STARTDATE'],
      ($_POST['RESEARCH_ENDDATE'] === '' ? null : $_POST['RESEARCH_ENDDATE']),
      $_POST['RESEARCH_STATUS'],
      $_POST['RESEARCH_ID']
    ]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'],'UPDATE','RESEARCH', $_POST['RESEARCH_ID']]);
    header('Location: research.php?ok=1'); exit;
  }

  if ($action === 'delete') {
    if (!isset($_POST['RESEARCH_ID'])) guardFail('Missing ID');
    $pdo->prepare("DELETE FROM RESEARCH WHERE RESEARCH_ID=?")->execute([$_POST['RESEARCH_ID']]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'],'DELETE','RESEARCH', $_POST['RESEARCH_ID']]);
    header('Location: research.php?ok=1'); exit;
  }
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
if ($to!==''){
  
  $sql.=" AND (RESEARCH_ENDDATE <= ? OR (RESEARCH_ENDDATE IS NULL AND RESEARCH_STARTDATE <= ?))";
  $params[]=$to; $params[]=$to;
}
$sql.=" ORDER BY RESEARCH_STARTDATE DESC, RESEARCH_ID DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?>

<h1>Research</h1>

<!-- Toolbar: filters + New Research -->
<div class="panel" style="margin-bottom:16px">
  <form method="get" class="grid">
    <div class="field" style="grid-column: span 4">
      <label>Title</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php foreach($statuses as $s){ $sel=$status===$s['STATUS_CODE']?' selected':''; echo '<option'.$sel.' value="'.$s['STATUS_CODE'].'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2">
      <label>Start from</label>
      <input class="input" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div class="field" style="grid-column: span 2">
      <label>End by</label>
      <input class="input" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="field" style="grid-column: span 1;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>

  <div style="margin-top:10px">
    <button class="btn primary"
      data-modal="edit"
      data-title="Add Research"
      data-research_id=""
      data-research_title=""
      data-research_startdate="<?php echo date('Y-m-d'); ?>"
      data-research_enddate=""
      data-research_status="<?php echo htmlspecialchars($statuses[0]['STATUS_CODE'] ?? 'ONGOING'); ?>"
    >+ New Research</button>
  </div>
</div>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-top:0">Create Research</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="grid-column: span 8"><label>Title</label><input class="input" name="RESEARCH_TITLE" required maxlength="255"></div>
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
</div>

<div class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table style="width:100%;border-collapse:collapse" class="table">
    <thead>
      <tr>
        <th align="left">ID</th>
        <th align="left">Title</th>
        <th align="left">Status</th>
        <th align="left">Start</th>
        <th align="left">End</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['RESEARCH_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_STATUS']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_STARTDATE']); ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_ENDDATE']); ?></td>
        <td style="white-space:nowrap">
          
          <form method="post" class="inline-save" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">
            <input type="hidden" name="RESEARCH_TITLE" value="<?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?>">

            <select name="RESEARCH_STATUS" class="input" style="min-width:160px">
              <?php foreach($statuses as $s){
                $sel = ($s['STATUS_CODE'] === $row['RESEARCH_STATUS']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.$s['STATUS_CODE'].'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>';
              } ?>
            </select>

            <input class="input" type="date" name="RESEARCH_STARTDATE" value="<?php echo htmlspecialchars($row['RESEARCH_STARTDATE']); ?>">
            <input class="input" type="date" name="RESEARCH_ENDDATE" value="<?php echo htmlspecialchars($row['RESEARCH_ENDDATE']); ?>">

            <button class="btn" style="padding:6px 10px">Save</button>
          </form>

          <!-- Edit via modal -->
          <button class="btn"
            data-modal="edit" data-title="Edit Research"
            data-research_id="<?php echo (int)$row['RESEARCH_ID'];?>"
            data-research_title="<?php echo htmlspecialchars($row['RESEARCH_TITLE']);?>"
            data-research_status="<?php echo htmlspecialchars($row['RESEARCH_STATUS']);?>"
            data-research_startdate="<?php echo htmlspecialchars($row['RESEARCH_STARTDATE']);?>"
            data-research_enddate="<?php echo htmlspecialchars($row['RESEARCH_ENDDATE']);?>"
          >Edit</button>

          <!-- Delete -->
          <form method="post" onsubmit="return confirm('Delete research?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">
            <button class="btn" style="padding:6px 10px;background:#b91c1c;border-color:#b91c1c;color:#fff">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<script>
(function(){
  const form = document.getElementById('modal-form');
  if (!form) return;

  form.action = location.pathname;

  form.insertAdjacentHTML('afterbegin', `
    <input type="hidden" name="RESEARCH_ID">

    <div class="field" style="grid-column: span 12">
      <label>Title</label>
      <input class="input" name="RESEARCH_TITLE" maxlength="255" required>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Status</label>
      <select class="input" name="RESEARCH_STATUS" required>
        <?php foreach($statuses as $s): ?>
          <option value="<?php echo htmlspecialchars($s['STATUS_CODE']); ?>">
            <?php echo htmlspecialchars($s['STATUS_LABEL']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Start</label>
      <input class="input" type="date" name="RESEARCH_STARTDATE" required>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>End</label>
      <input class="input" type="date" name="RESEARCH_ENDDATE">
    </div>
  `);

  const ensureAction = () => {
    const id = (form.querySelector('[name=RESEARCH_ID]')?.value || '').trim();
    (form.querySelector('[name=action]') || (() => {
      const h = document.createElement('input'); h.type='hidden'; h.name='action'; form.appendChild(h); return h;
    })()).value = id ? 'update' : 'create';
  };

  document.addEventListener('modal:populated', ensureAction);
  form.addEventListener('submit', ensureAction);
})();
</script>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
