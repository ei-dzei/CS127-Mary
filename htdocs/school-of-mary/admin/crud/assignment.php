<?php
$pageTitle = 'Assignments (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
require_once __DIR__ . '/../validators.php';

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $fkExists = function(PDO $pdo, string $table, string $col, $val): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$col} = ? LIMIT 1");
    $stmt->execute([$val]);
    return (bool)$stmt->fetchColumn();
  };
  $isDate = function($s): bool {
    if (!$s) return false;
    $d = date_create($s);
    return $d && $s === $d->format('Y-m-d');
  };

  if ($action === 'create') {
    // Validate FKs + date
    if (!$fkExists($pdo, 'FACULTY', 'FACULTY_ID', $_POST['FACULTY_ID'] ?? null)) guardFail('Invalid faculty');
    if (!$fkExists($pdo, 'RESEARCH', 'RESEARCH_ID', $_POST['RESEARCH_ID'] ?? null)) guardFail('Invalid research');
    if (!v_enum_exists($pdo, 'ROLE', 'ROLE_ID', $_POST['ROLE_ID'] ?? '')) guardFail('Invalid role');
    if (!$isDate($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date (YYYY-MM-DD)');

    $stmt = $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)");
    $stmt->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
        ->execute([$_SESSION['admin_user'], 'CREATE', 'ASSIGNMENT']);
    header('Location: assignment.php?ok=1'); exit;
  }

  if ($action === 'update') {
    if (!$fkExists($pdo, 'FACULTY', 'FACULTY_ID', $_POST['FACULTY_ID'] ?? null)) guardFail('Invalid faculty');
    if (!$fkExists($pdo, 'RESEARCH', 'RESEARCH_ID', $_POST['RESEARCH_ID'] ?? null)) guardFail('Invalid research');
    if (!v_enum_exists($pdo, 'ROLE', 'ROLE_ID', $_POST['ROLE_ID'] ?? '')) guardFail('Invalid role');
    if (!$isDate($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date (YYYY-MM-DD)');

    $stmt = $pdo->prepare("UPDATE ASSIGNMENT
                           SET FACULTY_ID=?, RESEARCH_ID=?, ROLE_ID=?, DATE_ASSIGNED=?
                           WHERE ASSIGNMENT_ID=?");
    $stmt->execute([
      $_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'],
      $_POST['DATE_ASSIGNED'], $_POST['ASSIGNMENT_ID']
    ]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'UPDATE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);
    header('Location: assignment.php?ok=1'); exit;
  }

  if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?");
    $stmt->execute([$_POST['ASSIGNMENT_ID']]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'DELETE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);
    header('Location: assignment.php?ok=1'); exit;
  }
}

/* Lists for selects */
$fac   = $pdo->query("SELECT FACULTY_ID, CONCAT(FACULTY_LNAME, ', ', FACULTY_FNAME, 
                    CASE WHEN FACULTY_INITIAL IS NOT NULL AND FACULTY_INITIAL<>'' THEN CONCAT(' ', FACULTY_INITIAL) ELSE '' END
                  ) AS name
                  FROM FACULTY ORDER BY FACULTY_LNAME, FACULTY_FNAME")->fetchAll();

$res   = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll();
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll();

/* Search */
$q = trim($_GET['q'] ?? '');
$sql = "SELECT a.*,
               CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME, 
                 CASE WHEN f.FACULTY_INITIAL IS NOT NULL AND f.FACULTY_INITIAL<>'' THEN CONCAT(' ', f.FACULTY_INITIAL) ELSE '' END
               ) AS FACULTY_NAME,
               r.RESEARCH_TITLE
        FROM ASSIGNMENT a
        JOIN FACULTY f ON a.FACULTY_ID=f.FACULTY_ID
        JOIN RESEARCH r ON a.RESEARCH_ID=r.RESEARCH_ID
        WHERE 1=1";
$params = [];
if ($q !== '') {
  $sql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
  $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= " ORDER BY a.ASSIGNMENT_ID DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>

<h1>Assignments</h1>

<!-- Toolbar: search + New Assignment -->
<div class="panel" style="margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <form method="get" class="grid" style="flex:1;min-width:540px">
    <div class="field" style="grid-column: span 10">
      <label>Search (faculty or research title)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" />
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn">Filter</button>
    </div>
  </form>

  <!-- New Assignment (modal) -->
  <button class="btn primary"
    data-modal="edit"
    data-title="Add Assignment"
    data-assignment_id=""
    data-faculty_id="<?php echo htmlspecialchars($fac[0]['FACULTY_ID'] ?? ''); ?>"
    data-research_id="<?php echo htmlspecialchars($res[0]['RESEARCH_ID'] ?? ''); ?>"
    data-role_id="<?php echo htmlspecialchars($roles[0]['ROLE_ID'] ?? ''); ?>"
    data-date_assigned="<?php echo date('Y-m-d'); ?>"
  >+ New Assignment</button>
</div>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-top:0">Create Assignment</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 4">
      <label>Faculty</label>
      <select class="input" name="FACULTY_ID" required>
        <?php foreach($fac as $f){ echo '<option value="'.$f['FACULTY_ID'].'">'.htmlspecialchars($f['name']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($res as $r){ echo '<option value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Role</label>
      <select class="input" name="ROLE_ID" required>
        <?php foreach($roles as $r){ echo '<option value="'.$r['ROLE_ID'].'">'.htmlspecialchars($r['ROLE_ID'].' — '.$r['ROLE_DESCRIPTION']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Assigned</label>
      <input class="input" type="date" name="DATE_ASSIGNED" required value="<?php echo date('Y-m-d'); ?>">
    </div>

    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn" type="submit">Add</button>
    </div>
  </form>
</div>

<!-- Records -->
<div class="panel">
  <h3 style="margin-top:0">Recent Records</h3>
  <table style="width:100%;border-collapse:collapse" class="table">
    <thead>
      <tr>
        <th align="left">ID</th>
        <th align="left">Faculty</th>
        <th align="left">Research</th>
        <th align="left">Role</th>
        <th align="left">Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $row): ?>
        <tr>
          <td><?php echo (int)$row['ASSIGNMENT_ID']; ?></td>
          <td><?php echo htmlspecialchars($row['FACULTY_NAME']); ?></td>
          <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
          <td><?php echo htmlspecialchars($row['ROLE_ID']); ?></td>
          <td><?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?></td>
          <td style="white-space:nowrap">
            
            <form method="post" class="inline-save" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">

              <select name="FACULTY_ID" class="input" style="min-width:180px">
                <?php foreach($fac as $f){
                  $sel = ($f['FACULTY_ID'] == $row['FACULTY_ID']) ? ' selected' : '';
                  echo '<option'.$sel.' value="'.$f['FACULTY_ID'].'">'.htmlspecialchars($f['name']).'</option>';
                } ?>
              </select>

              <select name="RESEARCH_ID" class="input" style="min-width:220px">
                <?php foreach($res as $r){
                  $sel = ($r['RESEARCH_ID'] == $row['RESEARCH_ID']) ? ' selected' : '';
                  echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>';
                } ?>
              </select>

              <select name="ROLE_ID" class="input" style="min-width:160px">
                <?php foreach($roles as $r){
                  $sel = ($r['ROLE_ID'] === $row['ROLE_ID']) ? ' selected' : '';
                  echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.htmlspecialchars($r['ROLE_ID'].' — '.$r['ROLE_DESCRIPTION']).'</option>';
                } ?>
              </select>

              <input type="date" name="DATE_ASSIGNED" class="input" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>">

              <button class="btn" style="padding:6px 10px">Save</button>
            </form>

            <!-- Edit via modal -->
            <button class="btn"
              data-modal="edit" data-title="Edit Assignment"
              data-assignment_id="<?php echo (int)$row['ASSIGNMENT_ID'];?>"
              data-faculty_id="<?php echo (int)$row['FACULTY_ID'];?>"
              data-research_id="<?php echo (int)$row['RESEARCH_ID'];?>"
              data-role_id="<?php echo htmlspecialchars($row['ROLE_ID']);?>"
              data-date_assigned="<?php echo htmlspecialchars($row['DATE_ASSIGNED']);?>"
            >Edit</button>

            <!-- Delete -->
            <form method="post" onsubmit="return confirm('Delete this record?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
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

  const ensureAction = () => {
    const id = (form.querySelector('[name=ASSIGNMENT_ID]')?.value || '').trim();
    form.querySelector('[name=action]').value = id ? 'update' : 'create';
  };

  form.insertAdjacentHTML('afterbegin', `
    <input type="hidden" name="ASSIGNMENT_ID">

    <div class="field" style="grid-column: span 6">
      <label>Faculty</label>
      <select class="input" name="FACULTY_ID" required>
        <?php foreach($fac as $f): ?>
          <option value="<?php echo (int)$f['FACULTY_ID']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($res as $r): ?>
          <option value="<?php echo (int)$r['RESEARCH_ID']; ?>"><?php echo htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Role</label>
      <select class="input" name="ROLE_ID" required>
        <?php foreach($roles as $r): ?>
          <option value="<?php echo htmlspecialchars($r['ROLE_ID']); ?>">
            <?php echo htmlspecialchars($r['ROLE_ID'].' — '.$r['ROLE_DESCRIPTION']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Date Assigned</label>
      <input class="input" type="date" name="DATE_ASSIGNED" required value="<?php echo date('Y-m-d'); ?>">
    </div>
  `);

  document.addEventListener('modal:populated', ensureAction);
  form.addEventListener('submit', ensureAction);
})();
</script>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
