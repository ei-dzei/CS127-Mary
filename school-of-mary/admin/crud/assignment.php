<?php
$pageTitle = 'Assignments';
require_once __DIR__ . '/../partials/admin_header.php';
csrf_check();

/* Handle Create/Update/Delete */
$action = $_POST['action'] ?? '';
if ($action==='create') {
  $stmt = $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)");
  $stmt->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','ASSIGNMENT']);
  header('Location: assignment.php?ok=1'); exit;
}
if ($action==='update') {
  $stmt = $pdo->prepare("UPDATE ASSIGNMENT SET FACULTY_ID=?, RESEARCH_ID=?, ROLE_ID=?, DATE_ASSIGNED=? WHERE ASSIGNMENT_ID=?");
  $stmt->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED'], $_POST['ASSIGNMENT_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);
  header('Location: assignment.php?ok=1'); exit;
}
if ($action==='delete') {
  $stmt = $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?");
  $stmt->execute([$_POST['ASSIGNMENT_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'DELETE','ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);
  header('Location: assignment.php?ok=1'); exit;
}

/* Lists for selects */
$fac = $pdo->query("SELECT FACULTY_ID, CONCAT(FACULTY_LNAME, ', ', FACULTY_FNAME) AS name FROM FACULTY ORDER BY FACULTY_LNAME")->fetchAll();
$res = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll();
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll();

/* Search/paginate */
$q = trim($_GET['q'] ?? '');
$sql = "SELECT a.*, CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME) AS FACULTY_NAME, r.RESEARCH_TITLE
        FROM ASSIGNMENT a
        JOIN FACULTY f ON a.FACULTY_ID=f.FACULTY_ID
        JOIN RESEARCH r ON a.RESEARCH_ID=r.RESEARCH_ID
        WHERE 1=1";
$params = [];
if ($q!==''){ $sql.=" AND (f.FACULTY_LNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)"; $params=["%$q%","%$q%"]; }
$sql.=" ORDER BY a.ASSIGNMENT_ID DESC LIMIT 40";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>

<h1>Assignments</h1>

<div class="panel" style="margin-bottom:16px">
  <form method="get" class="grid">
    <div class="field" style="grid-column: span 10">
      <label>Search (faculty or title)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" />
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>
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
</div>

<div class="panel">
  <h3 style="margin-top:0">Recent Records</h3>
  <table style="width:100%;border-collapse:collapse">
    <thead><tr>
      <th align="left">ID</th><th align="left">Faculty</th><th align="left">Research</th><th align="left">Role</th><th align="left">Date</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
        <tr>
          <td><?php echo (int)$row['ASSIGNMENT_ID']; ?></td>
          <td><?php echo htmlspecialchars($row['FACULTY_NAME']); ?></td>
          <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
          <td><?php echo htmlspecialchars($row['ROLE_ID']); ?></td>
          <td><?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?></td>
          <td>
            <!-- edit -->
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              <!-- quick inline edits (role/date) -->
              <select name="ROLE_ID">
                <?php foreach($roles as $r){ $sel=$r['ROLE_ID']===$row['ROLE_ID']?' selected':''; echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.$r['ROLE_ID'].'</option>'; } ?>
              </select>
              <input type="date" name="DATE_ASSIGNED" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>">
              <!-- keep same FK values unless changed -->
              <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
              <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">
              <button class="btn" style="padding:6px 10px">Save</button>
            </form>
            <!-- delete -->
            <form method="post" onsubmit="return confirm('Delete this record?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              <button class="btn" style="padding:6px 10px;background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
