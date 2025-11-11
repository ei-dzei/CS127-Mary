<?php
$pageTitle = 'Research';
require_once __DIR__ . '/../partials/site_header.php';

/* --- Lookups --- */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

/* --- Detail view --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pagination setup
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT re.*
    FROM RESEARCH re
    WHERE re.RESEARCH_ID = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $research = $stmt->fetch();

  if ($research) {
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
      <p><a class="btn small" href="<?= BASE_URL ?>/public/research.php">Back to list</a></p>
    <?php else: ?>
      <a class="btn small" href="<?= BASE_URL ?>/public/research.php" style="float:right;margin-top:-4px;">← Back</a>
      <h1 style="margin-bottom:6px"><?= htmlspecialchars($research['RESEARCH_TITLE']); ?></h1>
      <div class="muted" style="margin-bottom:10px;">
        <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;"><?= htmlspecialchars($research['RESEARCH_STATUS']); ?></span>
        <span style="margin-left:6px;">Start: <?= htmlspecialchars($research['RESEARCH_STARTDATE']); ?></span>
        <?php if ($research['RESEARCH_ENDDATE']) : ?>
          <span style="margin-left:6px;">End: <?= htmlspecialchars($research['RESEARCH_ENDDATE']); ?></span>
        <?php endif; ?>
      </div>

      <div class="grid">
        <div class="panel" style="grid-column: span 6; background:#fff;">
          <h3 style="margin-top:0; font-family:'Patua One',serif;">Overview</h3>
          <div class="field">
            <label>Title</label>
            <input class="input" value="<?= htmlspecialchars($research['RESEARCH_TITLE']); ?>" readonly />
          </div>
          <div class="grid">
            <div class="field" style="grid-column: span 6;">
              <label>Status</label>
              <input class="input" value="<?= htmlspecialchars($research['RESEARCH_STATUS']); ?>" readonly />
            </div>
            <div class="field" style="grid-column: span 3;">
              <label>Start</label>
              <input class="input" value="<?= htmlspecialchars($research['RESEARCH_STARTDATE']); ?>" readonly />
            </div>
            <div class="field" style="grid-column: span 3;">
              <label>End</label>
              <input class="input" value="<?= htmlspecialchars($research['RESEARCH_ENDDATE'] ?? '—'); ?>" readonly />
            </div>
          </div>
        </div>

        <div class="panel" style="grid-column: span 6; background:#fff;">
          <h3 style="margin-top:0; font-family:'Patua One',serif;">Funding</h3>
          <div class="field" style="grid-column: span 6;">
            <label>Total Funding</label>
            <input class="input" value="<?= '₱' . number_format($totalFunding, 2); ?>" readonly />
          </div>
          <?php if ($funds): ?>
            <table style="margin-top:8px;">
              <thead><tr><th>Agency</th><th>Amount</th><th>Date Funded</th></tr></thead>
              <tbody>
                <?php foreach ($funds as $f): ?>
                  <tr>
                    <td><?= htmlspecialchars($f['AGENCY_NAME']); ?></td>
                    <td><?= $f['FUNDING_AMOUNT'] !== null ? '₱'.number_format($f['FUNDING_AMOUNT'],2) : '—'; ?></td>
                    <td><?= htmlspecialchars($f['DATE_FUNDED'] ?? '—'); ?></td>
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
            <a class="panel slide-up" href="<?= BASE_URL ?>/public/faculty.php?id=<?= (int)$p['FACULTY_ID']; ?>" style="grid-column: span 6; text-decoration:none; color:inherit;">
              <h3 style="margin-top:0">
                <?php
                  echo htmlspecialchars($p['FACULTY_LNAME'].', '.$p['FACULTY_FNAME']);
                  if (!empty($p['FACULTY_INITIAL'])) echo ' '.htmlspecialchars($p['FACULTY_INITIAL']);
                ?>
              </h3>
              <div class="muted" style="font-size:.95rem; margin-top:4px;">
                <?= htmlspecialchars($p['RANK_DESCRIPTION']); ?>
                · Role: <?= htmlspecialchars($p['ROLE_ID']); ?>
                · Assigned: <?= htmlspecialchars($p['DATE_ASSIGNED']); ?>
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
  $sql .= " AND (re.RESEARCH_ENDDATE <= ? OR (re.RESEARCH_ENDDATE IS NULL AND re.RESEARCH_STARTDATE <= ?))";
  $params[] = $to;
  $params[] = $to;
}
$sql .= " ORDER BY re.RESEARCH_STARTDATE DESC, re.RESEARCH_ID DESC";

// Count total for pagination with same filters
$countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS count_query";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Add LIMIT + OFFSET for paginated results
$sql .= " LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $p) {
  $stmt->bindValue($i + 1, $p);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
$total = count($rows);
?>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Browse the research database system of School of Mary.</p>

  <!-- Filter Bar -->
  <form method="get" class="filterbar" style="margin-bottom:14px;">
    <!-- Inputs row -->
    <div class="filter-inputs">
      <!-- Search pill -->
      <div class="searchbox" style="flex:1 1 360px;">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search research titles…" />
      </div>

      <!-- Status -->
      <div class="field" style="min-width:200px;">
        <label>Status</label>
        <select class="input" name="status">
          <option value="">All</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= htmlspecialchars($s['STATUS_CODE']) ?>"<?= $status===$s['STATUS_CODE'] ? ' selected' : '' ?>>
              <?= htmlspecialchars($s['STATUS_LABEL']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date from -->
      <div class="field" style="min-width:180px;">
        <label>Start from</label>
        <input class="input" type="date" name="from" value="<?= htmlspecialchars($from) ?>" />
      </div>

      <!-- Date to -->
      <div class="field" style="min-width:180px;">
        <label>End by</label>
        <input class="input" type="date" name="to" value="<?= htmlspecialchars($to) ?>" />
      </div>
    </div>

    <!-- Actions row (buttons under the search bar) -->
    <div class="filter-actions">
      <button class="btn" type="submit">Apply</button>
      <a class="clear-btn" href="<?= BASE_URL ?>/public/research.php">Clear</a>
    </div>
  </form>


  <p class="muted" style="margin:6px 0 12px;">Showing <?= (int)$total ?> <?= $total===1 ? 'project' : 'projects' ?></p>

  <!-- Cards -->
  <?php if (!$rows): ?>
    <div class="panel">No matching research.</div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($rows as $row): ?>
        <div class="card">
          <div class="card__icon">🔬</div>
          <div class="card__content">
            <h3 class="card__title">
              <?= htmlspecialchars($row['RESEARCH_TITLE']); ?>
            </h3>
            <p class="card__desc">
              Status: <?= htmlspecialchars($row['RESEARCH_STATUS']); ?>
            </p>
            <div class="card__meta">
              🗓 Start: <?= htmlspecialchars($row['RESEARCH_STARTDATE']); ?>
              <?php if ($row['RESEARCH_ENDDATE']) echo ' · End: ' . htmlspecialchars($row['RESEARCH_ENDDATE']); ?>
            </div>
          </div>
          <div class="card__actions">
            <button class="btn small"
              data-read-more
              data-type="research"
              data-id="<?= (int)$row['RESEARCH_ID']; ?>">
              Read More
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="pagination">
    <?php
    $queryParams = $_GET;
    unset($queryParams['page']);
    $baseQuery = http_build_query($queryParams);
    $baseUrl = '?' . ($baseQuery ? $baseQuery . '&' : '');
    ?>

    <?php if ($page > 1): ?>
      <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="page-btn">Previous</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="<?= $baseUrl ?>page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>


</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
