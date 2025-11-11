<?php
// Printable audit log
$pageTitle = 'Audit Log (Print View)';

// Core first (sessions, DB, helpers)
require_once __DIR__ . '/../partials/init.php';

if (!is_admin()) { redirect_to('/admin/login.php'); }

// Resolve audit table PK/timestamp columns and alias them
function audit_resolve_cols(PDO $pdo): array {
  $idCandidates   = ['ID','id','log_id','audit_id'];
  $timeCandidates = ['CREATED_AT','created_at','logged_at','timestamp','createdOn'];

  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $pdo->query("SELECT {$idCol} AS ID, {$tCol} AS CREATED_AT FROM AUDIT_LOG ORDER BY {$idCol} DESC LIMIT 1");
        return [$idCol, $tCol]; // works
      } catch (Throwable $e) { /* try next */ }
    }
  }
  // Fallback
  return ['ID', 'CREATED_AT'];
}
[$AUDIT_ID, $AUDIT_TIME] = audit_resolve_cols($pdo);

/* --- Filters --- */
$actor = trim($_GET['actor'] ?? '');
$action = trim($_GET['action'] ?? '');    // CREATE/UPDATE/DELETE
$table = trim($_GET['table'] ?? '');      // FACULTY/RESEARCH/AGENCY/FUNDING/ASSIGNMENT
$from  = trim($_GET['from'] ?? '');       // YYYY-MM-DD
$to    = trim($_GET['to'] ?? '');         // YYYY-MM-DD

$sql = "
  SELECT {$AUDIT_ID} AS ID, {$AUDIT_TIME} AS CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
  FROM AUDIT_LOG
  WHERE 1=1
";
$params = [];

if ($actor !== '') { $sql .= " AND ACTOR LIKE ?";        $params[] = "%$actor%"; }
if ($action !== '') { $sql .= " AND ACTION_ENUM = ?";    $params[] = $action; }
if ($table !== '') { $sql .= " AND TABLE_NAME = ?";      $params[] = $table; }
if ($from  !== '') { $sql .= " AND DATE({$AUDIT_TIME}) >= ?"; $params[] = $from; }
if ($to    !== '') { $sql .= " AND DATE({$AUDIT_TIME}) <= ?"; $params[] = $to; }

$sql .= " ORDER BY {$AUDIT_ID} DESC LIMIT 500"; // cap to 500 for print

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Utility: options for selects
$tables  = ['FACULTY','RESEARCH','AGENCY','FUNDING','ASSIGNMENT'];
$actions = ['CREATE','UPDATE','DELETE','IMPORT'];

// Include header AFTER all logic
require_once __DIR__ . '/../partials/site_header.php';
?>

<!-- Screen-only filter panel (hidden when printing by print.css) -->
<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Audit Log — Print View</h1>
  <p class="muted" style="margin-bottom:10px;">
    Use filters then press <strong>Ctrl/Cmd + P</strong> to print. This page uses formal print styles automatically.
  </p>
  <form method="get" class="grid">
    <div class="field" style="grid-column:span 3;">
      <label>Actor</label>
      <input class="input" name="actor" value="<?php echo htmlspecialchars($actor); ?>" placeholder="admin, user id, etc." />
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Action</label>
      <select class="input" name="action">
        <option value="">All</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?php echo $a; ?>"<?php if ($action===$a) echo ' selected'; ?>><?php echo $a; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Table</label>
      <select class="input" name="table">
        <option value="">All</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?php echo $t; ?>"<?php if ($table===$t) echo ' selected'; ?>><?php echo $t; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>From</label>
      <input class="input" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>To</label>
      <input class="input" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <button class="btn">Apply</button>
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <a class="btn" href="<?php echo app_url('/admin/audit_print.php'); ?>" style="background:#234b7a;">Clear</a>
    </div>
  </form>
</section>

<!-- Printable content -->
<section class="panel fade-in" style="background:#fff;">
  <div class="container" style="width:100%;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
      <div>
        <h2 style="font-family:'Patua One', serif; margin:0;">School of Mary — Audit Log</h2>
        <div class="muted">
          Generated: <?php echo date('Y-m-d H:i:s'); ?>
          <?php if ($actor||$action||$table||$from||$to): ?>
            · Filters:
            <?php
              $chips=[];
              if ($actor)  $chips[]="Actor: ".htmlspecialchars($actor);
              if ($action) $chips[]="Action: ".htmlspecialchars($action);
              if ($table)  $chips[]="Table: ".htmlspecialchars($table);
              if ($from)   $chips[]="From: ".htmlspecialchars($from);
              if ($to)     $chips[]="To: ".htmlspecialchars($to);
              echo implode(' · ', $chips);
            ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="muted">Records: <?php echo number_format(count($rows)); ?></div>
    </div>

    <?php if (!$rows): ?>
      <div class="panel" style="background:#f8fafc; border-color:#e5eaf0;">No audit rows match your filters.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:160px;">When</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Table</th>
            <th>PK</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['ID']; ?></td>
              <td><?php echo htmlspecialchars($r['CREATED_AT']); ?></td>
              <td><?php echo htmlspecialchars($r['ACTOR']); ?></td>
              <td><?php echo htmlspecialchars($r['ACTION_ENUM']); ?></td>
              <td><?php echo htmlspecialchars($r['TABLE_NAME']); ?></td>
              <td><?php echo htmlspecialchars($r['PK_VALUE']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
