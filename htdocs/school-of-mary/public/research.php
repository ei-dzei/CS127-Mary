<?php
$pageTitle = 'Research';
require_once __DIR__ . '/../partials/site_header.php';

/* --- Lookups --- */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

/* --- Detail view (?id=) --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  // main record
  $stmt = $pdo->prepare("
    SELECT re.*
    FROM RESEARCH re
    WHERE re.RESEARCH_ID = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $research = $stmt->fetch();

  if ($research) {
    // assigned faculty
    $as = $pdo->prepare("
      SELECT a.ASSIGNMENT_ID, a.DATE_ASSIGNED, a.ROLE_ID,
             f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME,
             r.RANK_DESCRIPTION
      FROM ASSIGNMENT a
      JOIN FACULTY f ON f.FACULTY_ID = a.FACULTY_ID
      JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
      WHERE a.RESEARCH_ID = ?
      ORDER BY a.DATE_ASSIGNED DESC, a.ASSIGNMENT_ID DESC
    ");
    $as->execute([$id]);
    $people = $as->fetchAll();

    // funding
    $fs = $pdo->prepare("
      SELECT fu.FUNDING_ID, fu.FUNDING_AMOUNT, fu.DATE_FUNDED,
             ag.AGENCY_ID, ag.AGENCY_NAME
      FROM FUNDING fu
      JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
      WHERE fu.RESEARCH_ID = ?
      ORDER BY fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC
    ");
    $fs->execute([$id]);
    $funds = $fs->fetchAll();

    $totalFunding = 0.0;
    foreach ($funds as $f) {
      if ($f['FUNDING_AMOUNT'] !== null) $totalFunding += (float)$f['FUNDING_AMOUNT'];
    }
  }
  ?>
  <section class="panel fade-in">
    <?php if (!$research): ?>
      <h1>Research</h1>
      <p class="muted">Record not found.</p>
      <p><a class="btn small" href="/public/research.php">Back to list</a></p>
    <?php else: ?>
      <a class="btn small" href="/public/research.php" style="float:right;margin-top:-4px;">← Back</a>
      <h1 style="margin-bottom:6px">
        <?php echo htmlspecialchars($research['RESEARCH_TITLE']); ?>
      </h1>
      <div class="muted" style="margin-bottom:10px;">
        <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
          <?php echo htmlspecialchars($research['RESEARCH_STATUS']); ?>
        </span>
        <span style="margin-left:6px;">Start: <?php echo htmlspecialchars($research['RESEARCH_STARTDATE']); ?></span>
        <?php if ($research['RESEARCH_ENDDATE']) : ?>
          <span style="margin-left:6px;">End: <?php echo htmlspecialchars($research['RESEARCH_ENDDATE']); ?></span>
        <?php endif; ?>
      </div>

      <div class="grid">
        <!-- summary card -->
        <div class="panel" style="grid-column: span 6; background:#fff;">
          <h3 style="margin-top:0; font-family:'Patua One',serif;">Overview</h3>
          <div class="field">
            <label>Title</label>
            <input class="input" value="<?php echo htmlspecialchars($research['RESEARCH_TITLE']); ?>" readonly />
          </div>
          <div class="grid">
            <div class="field" style="grid-column: span 6;">
              <label>Status</label>
              <input class="input" value="<?php echo htmlspecialchars($research['RESEARCH_STATUS']); ?>" readonly />
            </div>
            <div class="field" style="grid-column: span 3;">
              <label>Start</label>
              <input class="input" value="<?php echo htmlspecialchars($research['RESEARCH_STARTDATE']); ?>" readonly />
            </div>
            <div class="field" style="grid-column: span 3;">
              <label>End</label>
              <input class="input" value="<?php echo htmlspecialchars($research['RESEARCH_ENDDATE'] ?? '—'); ?>" readonly />
            </div>
          </div>
        </div>

        <!-- funding summary -->
        <div class="panel" style="grid-column: span 6; background:#fff;">
          <h3 style="margin-top:0; font-family:'Patua One',serif;">Funding</h3>
          <div class="field" style="grid-column: span 6;">
            <label>Total Funding</label>
            <input class="input" value="<?php echo '₱' . number_format($totalFunding, 2); ?>" readonly />
          </div>
          <?php if ($funds): ?>
            <table style="margin-top:8px;">
              <thead>
                <tr>
                  <th>Agency</th>
                  <th>Amount</th>
                  <th>Date Funded</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($funds as $f): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($f['AGENCY_NAME']); ?></td>
                    <td><?php echo $f['FUNDING_AMOUNT'] !== null ? '₱'.number_format($f['FUNDING_AMOUNT'],2) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($f['DATE_FUNDED'] ?? '—'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="muted">No recorded funding.</div>
          <?php endif; ?>
        </div>
      </div>

      <h2 style="font-family:'Patua One',serif; margin-top:16px;">Assigned Faculty</h2>
      <?php if (!$people): ?>
        <div class="panel">No assignments found.</div>
      <?php else: ?>
        <div class="grid" style="gap:12px;">
          <?php foreach ($people as $p): ?>
            <a class="panel slide-up"
               href="/public/faculty.php?id=<?php echo (int)$p['FACULTY_ID']; ?>"
               style="grid-column: span 6; text-decoration:none; color:inherit;">
              <h3 style="margin-top:0">
                <?php
                  echo htmlspecialchars($p['FACULTY_LNAME'].', '.$p['FACULTY_FNAME']);
                  if (!empty($p['FACULTY_INITIAL'])) echo ' '.htmlspecialchars($p['FACULTY_INITIAL']);
                ?>
              </h3>
              <div class="muted" style="font-size:.95rem; margin-top:4px;">
                <?php echo htmlspecialchars($p['RANK_DESCRIPTION']); ?>
                · Role: <?php echo htmlspecialchars($p['ROLE_ID']); ?>
                · Assigned: <?php echo htmlspecialchars($p['DATE_ASSIGNED']); ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </section>

  <?php
  require_once __DIR__ . '/../partials/site_footer.php';
  exit;
}

/* --- List view --- */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

$sql = "
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS,
         re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE
  FROM RESEARCH re
  WHERE 1=1
";
$params = [];
if ($q !== '') {
  $sql .= " AND re.RESEARCH_TITLE LIKE ?";
  $params[] = "%$q%";
}
if ($status !== '') {
  $sql .= " AND re.RESEARCH_STATUS = ?";
  $params[] = $status;
}
if ($from !== '') {
  $sql .= " AND re.RESEARCH_STARTDATE >= ?";
  $params[] = $from;
}
if ($to !== '') {
  // include records that end by this date OR ongoing that started before or equal to
  $sql .= " AND (re.RESEARCH_ENDDATE <= ? OR (re.RESEARCH_ENDDATE IS NULL AND re.RESEARCH_STARTDATE <= ?))";
  $params[] = $to;
  $params[] = $to;
}
$sql .= " ORDER BY re.RESEARCH_STARTDATE DESC, re.RESEARCH_ID DESC LIMIT 36";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Browse research or refine using status and dates.</p>

  <form method="get" class="grid" style="margin-bottom:8px;">
    <div class="field" style="grid-column: span 5;">
      <label>Title contains</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="e.g., AI, Climate, IoT..." />
    </div>
    <div class="field" style="grid-column: span 3;">
      <label>Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?php echo htmlspecialchars($s['STATUS_CODE']); ?>"<?php if ($status===$s['STATUS_CODE']) echo ' selected'; ?>>
            <?php echo htmlspecialchars($s['STATUS_LABEL']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2;">
      <label>Start from</label>
      <input class="input" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
    </div>
    <div class="field" style="grid-column: span 2;">
      <label>End by</label>
      <input class="input" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
    </div>
    <div class="field" style="grid-column: span 12; display:flex; gap:8px; align-items:flex-end;">
      <button class="btn">Apply</button>
      <a class="btn" href="/public/research.php" style="background:#234b7a">Clear</a>
    </div>
  </form>
</section>

<section class="container fade-in" style="margin-top:6px; margin-bottom:24px;">
  <?php if (!$rows): ?>
    <div class="panel">No matching research.</div>
  <?php else: ?>
    <div class="grid" style="gap:12px;">
      <?php foreach ($rows as $row): ?>
        <div class="panel slide-up" style="grid-column: span 6;">
          <h3 style="margin-top:0;"><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></h3>
          <div class="muted" style="font-size:.95rem; margin-top:4px;">
            <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
              <?php echo htmlspecialchars($row['RESEARCH_STATUS']); ?>
            </span>
            <span style="margin-left:6px;">
              Start: <?php echo htmlspecialchars($row['RESEARCH_STARTDATE']); ?>
              <?php if ($row['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($row['RESEARCH_ENDDATE']); ?>
            </span>
          </div>

          <button
            class="btn small"
            data-read-more
            data-type="research"
            data-id="<?php echo (int)$row['RESEARCH_ID']; ?>"
            data-title="<?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?>">
            Read More
          </button>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
