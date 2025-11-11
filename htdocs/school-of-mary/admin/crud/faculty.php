<?php
$pageTitle = 'Faculty (Admin)';

// Load core first (sessions, DB, csrf, shared validators)
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
// Only check CSRF on POST requests
csrf_check();

/* Handle Create/Update/Delete */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    INSERT INTO FACULTY (FACULTY_FNAME, FACULTY_INITIAL, FACULTY_LNAME, FACULTY_EMAIL, RANK_ID, DEPT_ID)
    VALUES (?,?,?,?,?,?)
  ")->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'FACULTY']);
  redirect_to('/admin/crud/faculty.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    UPDATE FACULTY
    SET FACULTY_FNAME=?, FACULTY_INITIAL=?, FACULTY_LNAME=?, FACULTY_EMAIL=?, RANK_ID=?, DEPT_ID=?
    WHERE FACULTY_ID=?
  ")->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID'], $_POST['FACULTY_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'FACULTY', $_POST['FACULTY_ID']]);
  redirect_to('/admin/crud/faculty.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  $pdo->prepare("DELETE FROM FACULTY WHERE FACULTY_ID=?")->execute([$_POST['FACULTY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'FACULTY', $_POST['FACULTY_ID']]);
  redirect_to('/admin/crud/faculty.php?ok=1');
}

/* Lookup options */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();

/* Filters */
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');

$sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
        FROM FACULTY f
        JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
        JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
        WHERE 1=1";
$params = [];
if ($q !== '')   { $sql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($rank !== ''){ $sql .= " AND f.RANK_ID = ?"; $params[] = $rank; }
if ($dept !== ''){ $sql .= " AND f.DEPT_ID = ?"; $params[] = $dept; }
$sql .= " ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header AFTER all redirects/handlers
require_once __DIR__ . '/../../partials/site_header.php';
?>

<div class="admin-wide"> 
  <section class="panel fade-in">
    <h1 style="margin-bottom:8px;">Faculty</h1>
    <p class="muted" style="margin-bottom:8px;">Create, update, delete; inline edit Rank/Department; CSV import/export.</p>

    <!-- export/import -->
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
      <a class="btn small" href="<?= app_url('/admin/api/export.php?table=FACULTY'); ?>">Export CSV</a>
      <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
        <input type="hidden" name="table" value="FACULTY">
        <input class="input" type="file" name="file" accept=".csv" required>
        <button class="btn small">Import CSV</button>
      </form>
    </div>

    <!-- filter -->
    <form method="get" class="grid" style="margin-bottom:10px;">
      <div class="field" style="grid-column: span 5">
        <label>Search (name or email)</label>
        <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>">
      </div>
      <div class="field" style="grid-column: span 3">
        <label>Rank</label>
        <select class="input" name="rank">
          <option value="">All</option>
          <?php foreach($ranks as $r){ $sel=$rank===$r['RANK_ID']?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
        </select>
      </div>
      <div class="field" style="grid-column: span 3">
        <label>Department</label>
        <select class="input" name="dept">
          <option value="">All</option>
          <?php foreach($depts as $d){ $sel=$dept===$d['DEPT_ID']?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
        </select>
      </div>
      <div class="field" style="grid-column: span 1;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
    </form>
  </section>

  <section class="panel" style="margin-bottom:16px;">
    <h3 style="margin-top:0">Create Faculty</h3>
    <form method="post" class="grid">
      <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="create">
      <div class="field" style="grid-column: span 3"><label>First name</label><input class="input" name="FACULTY_FNAME" required></div>
      <div class="field" style="grid-column: span 2"><label>Initial</label><input class="input" name="FACULTY_INITIAL" maxlength="2"></div>
      <div class="field" style="grid-column: span 3"><label>Last name</label><input class="input" name="FACULTY_LNAME" required></div>
      <div class="field" style="grid-column: span 4"><label>Email</label><input class="input" type="email" name="FACULTY_EMAIL" required></div>
      <div class="field" style="grid-column: span 3">
        <label>Rank</label>
        <select class="input" name="RANK_ID" required><?php foreach($ranks as $r){ echo '<option value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?></select>
      </div>
      <div class="field" style="grid-column: span 3">
        <label>Department</label>
        <select class="input" name="DEPT_ID" required><?php foreach($depts as $d){ echo '<option value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?></select>
      </div>
      <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Add</button></div>
    </form>
  </section>

  <section class="panel">
    <h3 style="margin-top:0">Records</h3>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Rank</th><th>Dept</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($rows as $row): ?>
          <tr>
            <td><?php echo (int)$row['FACULTY_ID']; ?></td>
            <td><?php echo htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME'].' '.$row['FACULTY_INITIAL']); ?></td>
            <td><?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?></td>
            <td><?php echo htmlspecialchars($row['RANK_DESCRIPTION']); ?></td>
            <td><?php echo htmlspecialchars($row['DEPT_SPECIALIZATION']); ?></td>
            <td class="actions-cell">
              <!-- inline rank/department -->
              <form method="post" class="inline-save" style="display:inline-flex;gap:6px;align-items:center">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
                <input type="hidden" name="FACULTY_FNAME" value="<?php echo htmlspecialchars($row['FACULTY_FNAME']); ?>">
                <input type="hidden" name="FACULTY_INITIAL" value="<?php echo htmlspecialchars($row['FACULTY_INITIAL']); ?>">
                <input type="hidden" name="FACULTY_LNAME" value="<?php echo htmlspecialchars($row['FACULTY_LNAME']); ?>">
                <input type="hidden" name="FACULTY_EMAIL" value="<?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?>">
                <select name="RANK_ID" class="input" style="width:140px">
                  <?php foreach($ranks as $r){ $sel=($r['RANK_ID']===$row['RANK_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
                </select>
                <select name="DEPT_ID" class="input" style="width:200px">
                  <?php foreach($depts as $d){ $sel=($d['DEPT_ID']===$row['DEPT_ID'])?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
                </select>
                <button class="btn small">Save</button>
              </form>

              <!-- modal edit trigger -->
              <button
                class="btn small" data-modal="edit" data-title="Edit Faculty"
                data-template="#tpl-edit-<?php echo (int)$row['FACULTY_ID'];?>"
                data-action="update"
                data-hidden-FACULTY_ID="<?php echo (int)$row['FACULTY_ID']; ?>"
              >Quick Edit</button>

              <!-- delete -->
              <form method="post" onsubmit="return confirm('Delete faculty?');" style="display:inline">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
                <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
              </form>

              <!-- modal template -->
              <template id="tpl-edit-<?php echo (int)$row['FACULTY_ID'];?>">
                <div class="grid">
                  <div class="field" style="grid-column:span 4"><label>First name</label><input class="input" name="FACULTY_FNAME" value="<?php echo htmlspecialchars($row['FACULTY_FNAME']); ?>" required></div>
                  <div class="field" style="grid-column:span 2"><label>Initial</label><input class="input" name="FACULTY_INITIAL" maxlength="2" value="<?php echo htmlspecialchars($row['FACULTY_INITIAL']); ?>"></div>
                  <div class="field" style="grid-column:span 6"><label>Last name</label><input class="input" name="FACULTY_LNAME" value="<?php echo htmlspecialchars($row['FACULTY_LNAME']); ?>" required></div>
                  <div class="field" style="grid-column:span 6"><label>Email</label><input class="input" type="email" name="FACULTY_EMAIL" value="<?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?>" required></div>
                  <div class="field" style="grid-column:span 3">
                    <label>Rank</label>
                    <select class="input" name="RANK_ID" required>
                      <?php foreach($ranks as $r){ $sel=($r['RANK_ID']===$row['RANK_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
                    </select>
                  </div>
                  <div class="field" style="grid-column:span 3">
                    <label>Department</label>
                    <select class="input" name="DEPT_ID" required>
                      <?php foreach($depts as $d){ $sel=($d['DEPT_ID']===$row['DEPT_ID'])?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
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
</div>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
