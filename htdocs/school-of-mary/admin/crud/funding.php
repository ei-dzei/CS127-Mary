<?php
$pageTitle = 'Funding (Admin)';

// Load core first (sessions, DB, csrf, shared helpers)
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

/* ---- Local validators ---- */
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
if (!function_exists('v_decimal_nullable')) {
  function v_decimal_nullable($s): bool {
    if ($s === null || $s === '') return true;
    if (is_int($s) || is_float($s)) return true;
    if (!is_string($s)) return false;
    return (bool)preg_match('/^-?\d+(\.\d{1,2})?$/', trim($s));
  }
}

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
// csrf_check() enforces on POST
csrf_check();

/* ---------------- Actions ---------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['RESEARCH_ID'] ?? '') || !v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount');
  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "INSERT INTO FUNDING (RESEARCH_ID, AGENCY_ID, FUNDING_AMOUNT, DATE_FUNDED) VALUES (?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    ($_POST['FUNDING_AMOUNT'] ?? '') !== '' ? $_POST['FUNDING_AMOUNT'] : null,
    ($_POST['DATE_FUNDED'] ?? '')   !== '' ? $_POST['DATE_FUNDED']   : null
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'FUNDING']);

  redirect_to('/admin/crud/funding.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['FUNDING_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['RESEARCH_ID'] ?? '') || !v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount');
  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "UPDATE FUNDING
          SET RESEARCH_ID=?, AGENCY_ID=?, FUNDING_AMOUNT=?, DATE_FUNDED=?
          WHERE FUNDING_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    ($_POST['FUNDING_AMOUNT'] ?? '') !== '' ? $_POST['FUNDING_AMOUNT'] : null,
    ($_POST['DATE_FUNDED'] ?? '')   !== '' ? $_POST['DATE_FUNDED']   : null,
    $_POST['FUNDING_ID']
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'FUNDING', $_POST['FUNDING_ID']]);

  redirect_to('/admin/crud/funding.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['FUNDING_ID'] ?? '')) guardFail('Missing ID');

  $pdo->prepare("DELETE FROM FUNDING WHERE FUNDING_ID=?")->execute([$_POST['FUNDING_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'FUNDING', $_POST['FUNDING_ID']]);

  redirect_to('/admin/crud/funding.php?ok=1');
}

/* ---------------- Lookups ---------------- */
$research = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")
                ->fetchAll(PDO::FETCH_ASSOC);
$agencies = $pdo->query("SELECT AGENCY_ID, AGENCY_NAME FROM AGENCY ORDER BY AGENCY_NAME")
                ->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- Filters ---------------- */
$q = trim($_GET['q'] ?? '');  // search by research title or agency name
$sql = "SELECT fu.*, re.RESEARCH_TITLE, ag.AGENCY_NAME
        FROM FUNDING fu
        JOIN RESEARCH re ON fu.RESEARCH_ID = re.RESEARCH_ID
        JOIN AGENCY  ag ON fu.AGENCY_ID   = ag.AGENCY_ID
        WHERE 1=1";
$params = [];
if ($q !== '') {
  $sql .= " AND (re.RESEARCH_TITLE LIKE ? OR ag.AGENCY_NAME LIKE ?)";
  $params = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Include header AFTER all handlers/redirects */
require_once __DIR__ . '/../../partials/site_header.php';
?>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Funding</h1>
  <p class="muted" style="margin-bottom:8px;">Manage funding rows. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=FUNDING">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="FUNDING">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:8px;">
    <div class="field" style="grid-column: span 10">
      <label>Search (research or agency)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end">
      <button class="btn">Filter</button>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
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
      <input class="input" type="number" step="0.01" name="FUNDING_AMOUNT">
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Funded</label>
      <input class="input" type="date" name="DATE_FUNDED">
    </div>

    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end">
      <button class="btn">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <table>
    <thead>
      <tr><th>ID</th><th>Research</th><th>Agency</th><th>Amount</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?php echo (int)$row['FUNDING_ID']; ?></td>
        <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
        <td><?php echo htmlspecialchars($row['AGENCY_NAME']); ?></td>
        <td><?php echo $row['FUNDING_AMOUNT'] !== null ? '₱' . number_format((float)$row['FUNDING_AMOUNT'], 2) : '—'; ?></td>
        <td><?php echo htmlspecialchars((string)$row['DATE_FUNDED']); ?></td>
        <td style="white-space:nowrap">
          <button class="btn small"
                  data-modal="edit" data-title="Edit Funding"
                  data-template="#tpl-edit-<?php echo (int)$row['FUNDING_ID']; ?>"
                  data-action="update"
                  data-hidden-FUNDING_ID="<?php echo (int)$row['FUNDING_ID']; ?>">
            Quick Edit
          </button>

          <form method="post" onsubmit="return confirm('Delete funding row?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="FUNDING_ID" value="<?php echo (int)$row['FUNDING_ID']; ?>">
            <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
          </form>

          <template id="tpl-edit-<?php echo (int)$row['FUNDING_ID']; ?>">
            <div class="grid">
              <div class="field" style="grid-column: span 6">
                <label>Research</label>
                <select class="input" name="RESEARCH_ID" required>
                  <?php foreach($research as $r){
                    $sel = ($r['RESEARCH_ID'] === $row['RESEARCH_ID']) ? ' selected' : '';
                    echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>';
                  } ?>
                </select>
              </div>
              <div class="field" style="grid-column: span 4">
                <label>Agency</label>
                <select class="input" name="AGENCY_ID" required>
                  <?php foreach($agencies as $a){
                    $sel = ($a['AGENCY_ID'] === $row['AGENCY_ID']) ? ' selected' : '';
                    echo '<option'.$sel.' value="'.$a['AGENCY_ID'].'">'.htmlspecialchars($a['AGENCY_NAME']).'</option>';
                  } ?>
                </select>
              </div>
              <div class="field" style="grid-column: span 2">
                <label>Amount (₱)</label>
                <input class="input" type="number" step="0.01" name="FUNDING_AMOUNT"
                       value="<?php echo htmlspecialchars((string)$row['FUNDING_AMOUNT']); ?>">
              </div>
              <div class="field" style="grid-column: span 3">
                <label>Date Funded</label>
                <input class="input" type="date" name="DATE_FUNDED" value="<?php echo htmlspecialchars((string)$row['DATE_FUNDED']); ?>">
              </div>
            </div>
          </template>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
