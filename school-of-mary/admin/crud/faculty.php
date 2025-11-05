<?php
$pageTitle = 'Faculty (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
csrf_check();

/* Handle Create/Update/Delete */
$action = $_POST['action'] ?? '';

if ($action==='create') {
  $sql = "INSERT INTO FACULTY (FACULTY_FNAME, FACULTY_INITIAL, FACULTY_LNAME, FACULTY_EMAIL, RANK_ID, DEPT_ID)
          VALUES (?,?,?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','FACULTY']);
  header('Location: faculty.php?ok=1'); exit;
}

if ($action==='update') {
  $sql = "UPDATE FACULTY SET FACULTY_FNAME=?, FACULTY_INITIAL=?, FACULTY_LNAME=?, FACULTY_EMAIL=?, RANK_ID=?, DEPT_ID=?
          WHERE FACULTY_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID'], $_POST['FACULTY_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','FACULTY', $_POST['FACULTY_ID']]);
  header('Location: faculty.php?ok=1'); exit;
}

if ($action==='delete') {
  $pdo->prepare("DELETE FROM FACULTY WHERE FACULTY_ID=?")->execute([$_POST['FACULTY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'DELETE','FACULTY', $_POST['FACULTY_ID']]);
  header('Location: faculty.php?ok=1'); exit;
}

/* Lookup options */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();

/* Filters */
$q = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');

$sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
        FROM FACULTY f
        JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
        JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
        WHERE 1=1";
$params=[];
if ($q!==''){ $sql.=" AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)"; $params=["%$q%","%$q%","%$q%"]; }
if ($rank!==''){ $sql.=" AND f.RANK_ID = ?"; $params[]=$rank; }
if ($dept!==''){ $sql.=" AND f.DEPT_ID = ?"; $params[]=$dept; }
$sql.=" ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>

<h1>Faculty</h1>

<div class="panel" style="margin-bottom:16px">
  <form method="get" class="grid">
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
</div>

<div class="panel" style="margin-bottom:16px">
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
</div>

<div class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table style="width:100%;border-collapse:collapse">
    <thead><tr><th align="left">ID</th><th align="left">Name</th><th align="left">Email</th><th align="left">Rank</th><th align="left">Dept</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
        <tr>
          <td><?php echo (int)$row['FACULTY_ID']; ?></td>
          <td><?php echo htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME'].' '.$row['FACULTY_INITIAL']); ?></td>
          <td><?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?></td>
          <td><?php echo htmlspecialchars($row['RANK_DESCRIPTION']); ?></td>
          <td><?php echo htmlspecialchars($row['DEPT_SPECIALIZATION']); ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
              <input type="hidden" name="FACULTY_FNAME"  value="<?php echo htmlspecialchars($row['FACULTY_FNAME']); ?>">
              <input type="hidden" name="FACULTY_INITIAL"value="<?php echo htmlspecialchars($row['FACULTY_INITIAL']); ?>">
              <input type="hidden" name="FACULTY_LNAME"  value="<?php echo htmlspecialchars($row['FACULTY_LNAME']); ?>">
              <input type="hidden" name="FACULTY_EMAIL"  value="<?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?>">
              <input type="hidden" name="RANK_ID" value="<?php echo htmlspecialchars($row['RANK_ID']); ?>">
              <input type="hidden" name="DEPT_ID" value="<?php echo htmlspecialchars($row['DEPT_ID']); ?>">
              <button class="btn" style="padding:6px 10px">Save (no changes)</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete faculty?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
              <button class="btn" style="padding:6px 10px;background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
