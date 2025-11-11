<?php
$pageTitle = 'Assignments (Admin)';

// Load core first (sessions, DB, csrf, shared helpers)
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

if (!function_exists('v_date')) {
  function v_date($s): bool {
    if (!is_string($s) || $s === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
  }
}
if (!function_exists('v_date_nullable')) {
  function v_date_nullable($s): bool {
    if ($s === null || $s === '') return true;
    return v_date($s);
  }
}

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
csrf_check();

/* Handle Create/Update/Delete */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $stmt = $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)");
  $stmt->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'ASSIGNMENT']);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $stmt = $pdo->prepare("UPDATE ASSIGNMENT SET FACULTY_ID=?, RESEARCH_ID=?, ROLE_ID=?, DATE_ASSIGNED=? WHERE ASSIGNMENT_ID=?");
  $stmt->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED'], $_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');

  $stmt = $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?");
  $stmt->execute([$_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

/* Lists for selects */
$fac   = $pdo->query("SELECT FACULTY_ID, CONCAT(FACULTY_LNAME, ', ', FACULTY_FNAME) AS name FROM FACULTY ORDER BY FACULTY_LNAME")->fetchAll(PDO::FETCH_ASSOC);
$res   = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll(PDO::FETCH_ASSOC);

/* Search/paginate */
$q = trim($_GET['q'] ?? '');

$sql = "SELECT a.*, CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME) AS FACULTY_NAME, r.RESEARCH_TITLE
        FROM ASSIGNMENT a
        JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
        JOIN RESEARCH r ON a.RESEARCH_ID = r.RESEARCH_ID
        WHERE 1=1";
$params = [];
if ($q !== '') {
  $sql .= " AND (f.FACULTY_LNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
  $params = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY a.ASSIGNMENT_ID DESC LIMIT 60";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header AFTER all action handlers/redirects
require_once __DIR__ . '/../../partials/site_header.php';
?>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Assignments</h1>
  <p class="muted" style="margin-bottom:8px;">Manage who is assigned to which research and in what role. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=ASSIGNMENT">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="ASSIGNMENT">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 10">
      <label>Search (faculty or title)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" />
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
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
        <?php foreach($roles as $r){ echo '<option value="'.$r['ROLE_ID'].'">'.htmlspecialchars($r['ROLE_ID']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Assigned</label>
      <input class="input" type="date" name="DATE_ASSIGNED" required>
    </div>

    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn" type="submit">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Recent Records</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Faculty</th><th>Research</th><th>Role</th><th>Date</th><th>Actions</th>
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
            <!-- inline quick edit -->
            <form method="post" style="display:inline-flex; gap:6px; align-items:center">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">

              <select name="ROLE_ID" class="input" style="width:100px">
                <?php foreach($roles as $r){ $sel = ($r['ROLE_ID'] === $row['ROLE_ID']) ? ' selected' : ''; echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.$r['ROLE_ID'].'</option>'; } ?>
              </select>

              <input type="date" name="DATE_ASSIGNED" class="input" style="width:160px" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>">

              <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
              <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">

              <button class="btn small">Save</button>
            </form>

            <!-- modal edit -->
            <button class="btn small"
                    data-modal="edit" data-title="Edit Assignment"
                    data-template="#tpl-edit-<?php echo (int)$row['ASSIGNMENT_ID'];?>"
                    data-action="update"
                    data-hidden-ASSIGNMENT_ID="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              Full Edit
            </button>

            <!-- delete -->
            <form method="post" onsubmit="return confirm('Delete this record?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>

            <!-- modal template -->
            <template id="tpl-edit-<?php echo (int)$row['ASSIGNMENT_ID'];?>">
              <div class="grid">
                <div class="field" style="grid-column: span 4">
                  <label>Faculty</label>
                  <select class="input" name="FACULTY_ID" required>
                    <?php foreach($fac as $f){ $sel = ($f['FACULTY_ID'] === $row['FACULTY_ID']) ? ' selected' : ''; echo '<option'.$sel.' value="'.$f['FACULTY_ID'].'">'.htmlspecialchars($f['name']).'</option>'; } ?>
                  </select>
                </div>
                <div class="field" style="grid-column: span 6">
                  <label>Research</label>
                  <select class="input" name="RESEARCH_ID" required>
                    <?php foreach($res as $r){ $sel = ($r['RESEARCH_ID'] === $row['RESEARCH_ID']) ? ' selected' : ''; echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
                  </select>
                </div>
                <div class="field" style="grid-column: span 2">
                  <label>Role</label>
                  <select class="input" name="ROLE_ID" required>
                    <?php foreach($roles as $r){ $sel = ($r['ROLE_ID'] === $row['ROLE_ID']) ? ' selected' : ''; echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.$r['ROLE_ID'].'</option>'; } ?>
                  </select>
                </div>
                <div class="field" style="grid-column: span 3">
                  <label>Date Assigned</label>
                  <input class="input" type="date" name="DATE_ASSIGNED" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>" required>
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
