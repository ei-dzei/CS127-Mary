<?php
$pageTitle = 'Agencies (Admin)';
require_once __DIR__ . '/../partials/admin_header.php';
require_once __DIR__ . '/../validators.php';

/* Handle Create/Update/Delete */
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  if ($action === 'create') {
    // Validate
    if (!v_varchar($_POST['AGENCY_NAME'] ?? '', 255)) guardFail('Invalid agency name');
    if (!v_enum_exists($pdo, 'TYPE_AGENCY', 'TYPE_CODE', $_POST['AGENCY_TYPE'] ?? '')) guardFail('Invalid agency type');
    if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 35)) guardFail('Invalid contact (max 35 chars)');

    $sql = "INSERT INTO AGENCY (AGENCY_NAME, AGENCY_TYPE, AGENCY_CONTACTINFO) VALUES (?,?,?)";
    $pdo->prepare($sql)->execute([
      $_POST['AGENCY_NAME'],
      $_POST['AGENCY_TYPE'],
      $_POST['AGENCY_CONTACTINFO']
    ]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
        ->execute([$_SESSION['admin_user'], 'CREATE', 'AGENCY']);
    header('Location: agency.php?ok=1'); exit;
  }

  if ($action === 'update') {
    if (!v_varchar($_POST['AGENCY_NAME'] ?? '', 255)) guardFail('Invalid agency name');
    if (!v_enum_exists($pdo, 'TYPE_AGENCY', 'TYPE_CODE', $_POST['AGENCY_TYPE'] ?? '')) guardFail('Invalid agency type');
    if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 35)) guardFail('Invalid contact (max 35 chars)');

    $sql = "UPDATE AGENCY SET AGENCY_NAME=?, AGENCY_TYPE=?, AGENCY_CONTACTINFO=? WHERE AGENCY_ID=?";
    $pdo->prepare($sql)->execute([
      $_POST['AGENCY_NAME'],
      $_POST['AGENCY_TYPE'],
      $_POST['AGENCY_CONTACTINFO'],
      $_POST['AGENCY_ID']
    ]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'UPDATE', 'AGENCY', $_POST['AGENCY_ID']]);
    header('Location: agency.php?ok=1'); exit;
  }

  if ($action === 'delete') {
    $pdo->prepare("DELETE FROM AGENCY WHERE AGENCY_ID=?")->execute([$_POST['AGENCY_ID']]);
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'DELETE', 'AGENCY', $_POST['AGENCY_ID']]);
    header('Location: agency.php?ok=1'); exit;
  }
}

/* Lookups */
$types = $pdo->query("SELECT TYPE_CODE, TYPE_LABEL FROM TYPE_AGENCY ORDER BY TYPE_LABEL")->fetchAll();

/* Filters */
$q    = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');

$sql = "SELECT a.*, t.TYPE_LABEL
        FROM AGENCY a
        LEFT JOIN TYPE_AGENCY t ON a.AGENCY_TYPE = t.TYPE_CODE
        WHERE 1=1";
$params = [];
if ($q !== '')    { $sql .= " AND a.AGENCY_NAME LIKE ?"; $params[] = "%$q%"; }
if ($type !== '') { $sql .= " AND a.AGENCY_TYPE = ?";    $params[] = $type;  }
$sql .= " ORDER BY a.AGENCY_NAME LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>

<h1>Agencies</h1>

<!-- Toolbar: filters + New Agency (modal) -->
<div class="panel" style="margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <form method="get" class="grid" style="flex:1;min-width:540px">
    <div class="field" style="grid-column: span 6">
      <label>Name</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 4">
      <label>Type</label>
      <select class="input" name="type">
        <option value="">All</option>
        <?php foreach($types as $t){
          $sel = $type === $t['TYPE_CODE'] ? ' selected' : '';
          echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
        } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2;display:flex;align-items:flex-end">
      <button class="btn">Filter</button>
    </div>
  </form>

  <!-- New Agency (opens modal) -->
  <button class="btn primary"
    data-modal="edit"
    data-title="Add Agency"
    data-agency_id=""
    data-agency_name=""
    data-agency_type="<?php echo htmlspecialchars($types[0]['TYPE_CODE'] ?? ''); ?>"
    data-agency_contactinfo=""
  >+ New Agency</button>
</div>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-top:0">Create Agency</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="grid-column: span 6">
      <label>Name</label>
      <input class="input" name="AGENCY_NAME" required maxlength="255">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Type</label>
      <select class="input" name="AGENCY_TYPE" required>
        <?php foreach($types as $t){
          echo '<option value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
        } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Contact</label>
      <input class="input" name="AGENCY_CONTACTINFO" required maxlength="35" placeholder="email or phone">
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
        <th align="left">Name</th>
        <th align="left">Type</th>
        <th align="left">Contact</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['AGENCY_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo htmlspecialchars($row['TYPE_LABEL']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline-save" style="display:inline-flex;gap:6px;align-items:center">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">

            <input class="input" name="AGENCY_NAME" value="<?php echo htmlspecialchars($row['AGENCY_NAME']); ?>" maxlength="255" style="width:240px">

            <select name="AGENCY_TYPE" class="input" style="width:160px">
              <?php foreach($types as $t){
                $sel = $t['TYPE_CODE'] === $row['AGENCY_TYPE'] ? ' selected' : '';
                echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
              } ?>
            </select>

            <input class="input" name="AGENCY_CONTACTINFO" value="<?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']); ?>" maxlength="35" style="width:200px" placeholder="email or phone">

            <button class="btn" style="padding:6px 10px">Save</button>
          </form>

          <!-- Edit (full modal) -->
          <button class="btn"
            data-modal="edit" data-title="Edit Agency"
            data-agency_id="<?php echo (int)$row['AGENCY_ID'];?>"
            data-agency_name="<?php echo htmlspecialchars($row['AGENCY_NAME']);?>"
            data-agency_type="<?php echo htmlspecialchars($row['AGENCY_TYPE']);?>"
            data-agency_contactinfo="<?php echo htmlspecialchars($row['AGENCY_CONTACTINFO']);?>"
          >Edit</button>

          <!-- Delete -->
          <form method="post" onsubmit="return confirm('Delete agency?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="AGENCY_ID" value="<?php echo (int)$row['AGENCY_ID']; ?>">
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
    const id = (form.querySelector('[name=AGENCY_ID]')?.value || '').trim();
    form.querySelector('[name=action]').value = id ? 'update' : 'create';
  };
  form.insertAdjacentHTML('afterbegin', `
    <input type="hidden" name="AGENCY_ID">

    <div class="field" style="grid-column: span 7">
      <label>Name</label>
      <input class="input" name="AGENCY_NAME" required maxlength="255" autocomplete="off">
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Type</label>
      <select class="input" name="AGENCY_TYPE" required>
        <?php foreach($types as $t): ?>
          <option value="<?php echo htmlspecialchars($t['TYPE_CODE']); ?>">
            <?php echo htmlspecialchars($t['TYPE_LABEL']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Contact</label>
      <input class="input" name="AGENCY_CONTACTINFO" required maxlength="35" placeholder="email or phone" autocomplete="off">
    </div>
  `);

  document.addEventListener('modal:populated', ensureAction);
  form.addEventListener('submit', ensureAction);
})();
</script>

<?php require_once __DIR__ . '/../partials/admin_footer.php'; ?>
