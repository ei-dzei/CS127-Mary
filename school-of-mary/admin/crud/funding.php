<?php
$pageTitle = 'Funding (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
csrf_check();

$action = $_POST['action'] ?? '';

if ($action==='create') {
  $sql = "INSERT INTO FUNDING (RESEARCH_ID, AGENCY_ID, FUNDING_AMOUNT, DATE_FUNDED) VALUES (?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'], $_POST['AGENCY_ID'],
    $_POST['FUNDING_AMOUNT'] !== '' ? $_POST['FUNDING_AMOUNT'] : null,
    $_POST['DATE_FUNDED'] !== '' ? $_POST['DATE_FUNDED'] : null
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'],'CREATE','FUNDING']);
  header('Location: funding.php?ok=1'); exit;
}
if ($action==='update') {
  $sql = "UPDATE FUNDING SET RESEARCH_ID=?, AGENCY_ID=?, FUNDING_AMOUNT=?, DATE_FUNDED=? WHERE FUNDING_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'], $_POST['AGENCY_ID'],
    $_POST['FUNDING_AMOUNT'] !== '' ? $_POST['FUNDING_AMOUNT'] : null,
    $_POST['DATE_FUNDED'] !== '' ? $_POST['DATE_FUNDED'] : null,
    $_POST['FUNDING_ID']
  ]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'UPDATE','FUNDING', $_POST['FUNDING_ID']]);
  header('Location: funding.php?ok=1'); exit;
}
if ($action==='delete') {
  $pdo->prepare("DELETE FROM FUNDING WHERE FUNDING_ID=?")->execute([$_POST['FUNDING_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'],'DELETE','FUNDING', $_POST['FUNDING_ID']]);
  header('Location: funding.php?ok=1'); exit;
}

/* Lookups */
$research = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll();
$agencies = $pdo->query("SELECT AGENCY_ID, AGENCY_NAME FROM AGENCY ORDER BY AGENCY_NAME")->fetchAll();

/* Filters */
$q = trim($_GET['q'] ?? '');  // search by research title or agency name
$sql = "SELECT fu.*, re.RESEARCH_TITLE, ag.AGENCY_NAME
        FROM FUNDING fu
        JOIN RESEARCH re ON fu.RESEARCH_ID=re.RESEARCH_ID
        JOIN AGENCY ag ON fu.AGENCY_ID=ag.AGENCY_ID
        WHERE 1=1";
$params=[];
if ($q!==''){ $sql.=" AND (re.RESEARCH_TITLE LIKE ? OR ag.AGENCY_NAME LIKE ?)"; $params=["%$q%","%$q%"]; }
$sql.=" ORDER BY fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC LIMIT 60";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?>

<h1>Funding</h1>

<div class="panel" style="margin-bottom:16px">
  <form method="get" class="grid">
    <div class="field" style="grid-column: span 10"><label>Search (research or agency)</label><input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>"></div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Filter</button></div>
  </form>
</div>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-top:0">Create Funding</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($research as $r){ echo '<option value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 4">
      <label>Agency</label>
      <select class="input" name="AGENCY_ID" required>
        <?php foreach($agencies as $a){ echo '<option value="'.$a['AGENCY_ID'].'">'.htmlspecialchars($a['AGENCY_NAME']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2"><label>Amount (₱)</label><input class="input" type="number" step="0.01" name="FUNDING_AMOUNT"></div>
    <div class="field" style="grid-column: span 3"><label>Date Funded</label><input class="input" type="date" name="DATE_FUNDED"></div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end"><button class="btn">Add</button></div>
  </form>
</div>

<div class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table style="width:100%;border-collapse:collapse">
    <thead><tr><th align="left">ID</th><th align="left">Research</th><th align="left">Agency</th><th align="left">Amount</th><th align="left">Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['FUNDING_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo $row['FUNDING_AMOUNT']!==null ? '₱'.number_format($row['FUNDING_AMOUNT'],2) : '—'; ?></td>
        <td><?php echo htmlspecialchars($row['DATE_FUNDED']); ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="FUNDING_ID" value="<?php echo (int)$row['FUNDING_ID']; ?>">
            <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">
            <input type="hidden" name="FUNDING_AMOUNT" value="<?php echo htmlspecialchars($row['FUNDING_AMOUNT']); ?>">
            <input type="hidden" name="DATE_FUNDED" value="<?php echo htmlspecialchars($row['DATE_FUNDED']); ?>">
            <button class="btn" style="padding:6px 10px">Save (no changes)</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete funding row?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="FUNDING_ID" value="<?php echo (int)$row['FUNDING_ID']; ?>">
            <button class="btn" style="padding:6px 10px;background:#b91c1c;border-color:#b91c1c">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
