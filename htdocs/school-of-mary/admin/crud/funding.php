<?php
$pageTitle = 'Funding (Admin)';
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
    if ($s === null || $s === '') return false;
    $d = date_create($s);
    return $d && $s === $d->format('Y-m-d');
  };
 
  $normalizeAmount = function($s) {
    if ($s === null || $s === '') return null;
    if (!is_numeric($s)) guardFail('Amount must be a number');
    $n = (float)$s;
    if ($n < 0) guardFail('Amount cannot be negative');
    if ($n > 99999999.99) guardFail('Amount too large');
    return number_format($n, 2, '.', '');
  };

  if ($action === 'create') {
    if (!$fkExists($pdo, 'RESEARCH', 'RESEARCH_ID', $_POST['RESEARCH_ID'] ?? null)) guardFail('Invalid research');
    if (!$fkExists($pdo, 'AGENCY', 'AGENCY_ID', $_POST['AGENCY_ID'] ?? null)) guardFail('Invalid agency');

    $amount = $normalizeAmount($_POST['FUNDING_AMOUNT'] ?? null);
    $date   = $_POST['DATE_FUNDED'] ?? null;
    if ($date !== null && $date !== '' && !$isDate($date)) guardFail('Invalid date (YYYY-MM-DD)');

    $sql = "INSERT INTO FUNDING (RESEARCH_ID, AGENCY_ID, FUNDING_AMOUNT, DATE_FUNDED) VALUES (?,?,?,?)";
    $pdo->prepare($sql)->execute([
      $_POST['RESEARCH_ID'],
      $_POST['AGENCY_ID'],
      $amount,
      ($date === '' ? null : $date)
    ]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
        ->execute([$_SESSION['admin_user'],'CREATE','FUNDING']);
    header('Location: funding.php?ok=1'); exit;
  }

  if ($action === 'update') {
    if (!$fkExists($pdo, 'RESEARCH', 'RESEARCH_ID', $_POST['RESEARCH_ID'] ?? null)) guardFail('Invalid research');
    if (!$fkExists($pdo, 'AGENCY', 'AGENCY_ID', $_POST['AGENCY_ID'] ?? null)) guardFail('Invalid agency');

    $amount = $normalizeAmount($_POST['FUNDING_AMOUNT'] ?? null);
    $date   = $_POST['DATE_FUNDED'] ?? null;
    if ($date !== null && $date !== '' && !$isDate($date)) guardFail('Invalid date (YYYY-MM-DD)');

    $sql = "UPDATE FUNDING SET RESEARCH_ID=?, AGENCY_ID=?, FUNDING_AMOUNT=?, DATE_FUNDED=? WHERE FUNDING_ID=?";
    $pdo->prepare($sql)->execute([
      $_POST['RESEARCH_ID'],
      $_POST['AGENCY_ID'],
      $amount,
      ($date === '' ? null : $date),
      $_POST['FUNDING_ID']
    ]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'],'UPDATE','FUNDING', $_POST['FUNDING_ID']]);
    header('Location: funding.php?ok=1'); exit;
  }

  if ($action === 'delete') {
    $pdo->prepare("DELETE FROM FUNDING WHERE FUNDING_ID=?")->execute([$_POST['FUNDING_ID']]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'],'DELETE','FUNDING', $_POST['FUNDING_ID']]);
    header('Location: funding.php?ok=1'); exit;
  }
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

<!-- Toolbar: search + New Funding (modal) -->
<div class="panel" style="margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <form method="get" class="grid" style="flex:1;min-width:540px">
    <div class="field" style="grid-column: span 10">
      <label>Search (research or agency)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn">Filter</button>
    </div>
  </form>

  <!-- + New Funding opens modal -->
  <button class="btn primary"
    data-modal="edit"
    data-title="Add Funding"
    data-funding_id=""
    data-research_id="<?php echo htmlspecialchars($research[0]['RESEARCH_ID'] ?? ''); ?>"
    data-agency_id="<?php echo htmlspecialchars($agencies[0]['AGENCY_ID'] ?? ''); ?>"
    data-funding_amount=""
    data-date_funded="<?php echo date('Y-m-d'); ?>"
  >+ New Funding</button>
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

    <div class="field" style="grid-column: span 2">
      <label>Amount (₱)</label>
      <input class="input" type="number" step="0.01" min="0" name="FUNDING_AMOUNT" placeholder="optional">
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Funded</label>
      <input class="input" type="date" name="DATE_FUNDED" value="<?php echo date('Y-m-d'); ?>">
    </div>

    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn">Add</button>
    </div>
  </form>
</div>

<div class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table style="width:100%;border-collapse:collapse" class="table">
    <thead>
      <tr>
        <th align="left">ID</th>
        <th align="left">Research</th>
        <th align="left">Agency</th>
        <th align="left">Amount</th>
        <th align="left">Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['FUNDING_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo $row['FUNDING_AMOUNT']!==null ? '₱'.number_format($row['FUNDING_AMOUNT'],2) : '—'; ?></td>
        <td><?php echo htmlspecialchars($row['DATE_FUNDED']); ?></td>
        <td style="white-space:nowrap">
          
          <form method="post" class="inline-save" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="FUNDING_ID" value="<?php echo (int)$row['FUNDING_ID']; ?>">

            <select name="RESEARCH_ID" class="input" style="min-width:220px">
              <?php foreach($research as $r){
                $sel = ($r['RESEARCH_ID'] == $row['RESEARCH_ID']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>';
              } ?>
            </select>

            <select name="AGENCY_ID" class="input" style="min-width:220px">
              <?php foreach($agencies as $a){
                $sel = ($a['AGENCY_ID'] == $row['AGENCY_ID']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.$a['AGENCY_ID'].'">'.htmlspecialchars($a['AGENCY_NAME']).'</option>';
              } ?>
            </select>

            <input class="input" type="number" step="0.01" min="0" name="FUNDING_AMOUNT"
                   value="<?php echo htmlspecialchars($row['FUNDING_AMOUNT']); ?>" style="width:140px" placeholder="optional">

            <input class="input" type="date" name="DATE_FUNDED" value="<?php echo htmlspecialchars($row['DATE_FUNDED']); ?>">

            <button class="btn" style="padding:6px 10px">Save</button>
          </form>

          <!-- Edit via modal -->
          <button class="btn"
            data-modal="edit" data-title="Edit Funding"
            data-funding_id="<?php echo (int)$row['FUNDING_ID'];?>"
            data-research_id="<?php echo (int)$row['RESEARCH_ID'];?>"
            data-agency_id="<?php echo (int)$row['AGENCY_ID'];?>"
            data-funding_amount="<?php echo htmlspecialchars($row['FUNDING_AMOUNT']);?>"
            data-date_funded="<?php echo htmlspecialchars($row['DATE_FUNDED']);?>"
          >Edit</button>

          <!-- Delete -->
          <form method="post" onsubmit="return confirm('Delete funding row?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="FUNDING_ID" value="<?php echo (int)$row['FUNDING_ID']; ?>">
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
    const id = (form.querySelector('[name=FUNDING_ID]')?.value || '').trim();
    form.querySelector('[name=action]').value = id ? 'update' : 'create';
  };

  form.insertAdjacentHTML('afterbegin', `
    <input type="hidden" name="FUNDING_ID">

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($research as $r): ?>
          <option value="<?php echo (int)$r['RESEARCH_ID']; ?>"><?php echo htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Agency</label>
      <select class="input" name="AGENCY_ID" required>
        <?php foreach($agencies as $a): ?>
          <option value="<?php echo (int)$a['AGENCY_ID']; ?>"><?php echo htmlspecialchars($a['AGENCY_NAME']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Amount (₱)</label>
      <input class="input" type="number" step="0.01" min="0" name="FUNDING_AMOUNT" placeholder="optional">
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Date Funded</label>
      <input class="input" type="date" name="DATE_FUNDED" value="<?php echo date('Y-m-d'); ?>">
    </div>
  `);

  document.addEventListener('modal:populated', ensureAction);
  form.addEventListener('submit', ensureAction);
})();
</script>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
