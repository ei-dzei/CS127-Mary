<?php
$pageTitle = 'Faculty';
require_once __DIR__ . '/../partials/site_header.php';

/* --- Lookups for filters --- */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();

/* --- Detail view (if ?id=) --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* --- Pagination setup --- */
$perPage = 6;
$page    = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$offset  = ($page - 1) * $perPage;

if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
           f.RANK_ID, r.RANK_DESCRIPTION,
           f.DEPT_ID, d.DEPT_SPECIALIZATION AS DEPARTMENT, d.DEPT_CLASSIFICATION
    FROM FACULTY f
    JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
    JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
    WHERE f.FACULTY_ID = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $faculty = $stmt->fetch();

  if ($faculty) {
    $rs = $pdo->prepare("
      SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS, re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE,
             a.ROLE_ID
      FROM ASSIGNMENT a
      JOIN RESEARCH re ON re.RESEARCH_ID = a.RESEARCH_ID
      WHERE a.FACULTY_ID = ?
      ORDER BY re.RESEARCH_STARTDATE DESC
    ");
    $rs->execute([$id]);
    $projects = $rs->fetchAll();
  }
  ?>
  <section class="panel fade-in">
    <?php if (!$faculty): ?>
      <h1>Faculty</h1>
      <p class="muted">Record not found.</p>
      <p><a class="btn small" href="<?= BASE_URL ?>/public/faculty.php">Back to list</a></p>
    <?php else: ?>
      <a class="btn small" href="<?= BASE_URL ?>/public/faculty.php" style="float:right;margin-top:-4px;">← Back</a>
      <h1 style="margin-bottom:6px">
        <?php
          echo htmlspecialchars($faculty['FACULTY_LNAME'].', '.$faculty['FACULTY_FNAME']);
          if (!empty($faculty['FACULTY_INITIAL'])) echo ' '.htmlspecialchars($faculty['FACULTY_INITIAL']);
        ?>
      </h1>
      <div class="muted" style="margin-bottom:10px">
        <?= htmlspecialchars($faculty['RANK_DESCRIPTION']); ?> ·
        <?= htmlspecialchars($faculty['DEPARTMENT']); ?> (<?= htmlspecialchars($faculty['DEPT_CLASSIFICATION']); ?>)
      </div>
      <div class="panel" style="background:#fff;">
        <div class="grid">
          <div class="field" style="grid-column:span 6">
            <label>Email</label>
            <input class="input" value="<?= htmlspecialchars($faculty['FACULTY_EMAIL']); ?>" readonly />
          </div>
          <div class="field" style="grid-column:span 3">
            <label>Rank</label>
            <input class="input" value="<?= htmlspecialchars($faculty['RANK_DESCRIPTION']); ?>" readonly />
          </div>
          <div class="field" style="grid-column:span 3">
            <label>Department</label>
            <input class="input" value="<?= htmlspecialchars($faculty['DEPARTMENT']); ?>" readonly />
          </div>
        </div>
      </div>

      <h2 style="font-family:'Patua One',serif; margin-top:16px;">Research Projects</h2>
      <?php if (empty($projects)): ?>
        <div class="panel">No assignments found for this faculty.</div>
      <?php else: ?>
        <div class="grid" style="gap:12px">
          <?php foreach ($projects as $p): ?>
            <a class="panel slide-up" href="<?= BASE_URL ?>/public/research.php?id=<?= (int)$p['RESEARCH_ID']; ?>" style="grid-column:span 6; text-decoration:none; color:inherit;">
              <h3 style="margin-top:0"><?= htmlspecialchars($p['RESEARCH_TITLE']); ?></h3>
              <div class="muted" style="font-size:.95rem; margin-top:4px;">
                <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;"><?= htmlspecialchars($p['RESEARCH_STATUS']); ?></span>
                <span style="margin-left:6px;">
                  Start: <?= htmlspecialchars($p['RESEARCH_STARTDATE']); ?>
                  <?php if (!empty($p['RESEARCH_ENDDATE'])) echo " · End: ".htmlspecialchars($p['RESEARCH_ENDDATE']); ?>
                </span>
                <span style="margin-left:6px;">Role: <?= htmlspecialchars($p['ROLE_ID']); ?></span>
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
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');

/* Build WHERE and bind parameters (all named) */
$where  = " WHERE 1=1 ";
$params = [];

if ($q !== '') {
  $where .= " AND (f.FACULTY_LNAME LIKE :q1 OR f.FACULTY_FNAME LIKE :q2 OR f.FACULTY_EMAIL LIKE :q3)";
  $params[':q1'] = "%{$q}%";
  $params[':q2'] = "%{$q}%";
  $params[':q3'] = "%{$q}%";
}
if ($rank !== '') {
  $where .= " AND f.RANK_ID = :rank";
  $params[':rank'] = $rank;
}
if ($dept !== '') {
  $where .= " AND f.DEPT_ID = :dept";
  $params[':dept'] = $dept;
}

/* Count with the same filters (no ORDER/LIMIT) */
$countSql = "
  SELECT COUNT(*)
  FROM FACULTY f
  JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
  JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
" . $where;

$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

/* Paged SELECT using the same WHERE */
$sql = "
  SELECT
    f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
    r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
  FROM FACULTY f
  JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
  JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
" . $where . "
  ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME
  LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
/* Bind filter params */
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
/* Bind pagination as integers */
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$rows  = $stmt->fetchAll();
$total = count($rows);
?>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Explore the faculty of School of Mary</p>

  <!-- Filter Bar -->
  <form method="get" class="filterbar" style="margin-bottom:14px;">
    <!-- Inputs row -->
    <div class="filter-inputs">
      <!-- Search pill -->
      <div class="searchbox" style="flex:1 1 360px;">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name or email…" />
      </div>

      <!-- Rank -->
      <div class="field" style="min-width:200px;">
        <label>Rank</label>
        <select class="input" name="rank">
          <option value="">All</option>
          <?php foreach ($ranks as $r): ?>
            <option value="<?= htmlspecialchars($r['RANK_ID']) ?>"<?= $rank===$r['RANK_ID'] ? ' selected' : '' ?>>
              <?= htmlspecialchars($r['RANK_DESCRIPTION']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Department -->
      <div class="field" style="min-width:220px;">
        <label>Department</label>
        <select class="input" name="dept">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= htmlspecialchars($d['DEPT_ID']) ?>"<?= $dept===$d['DEPT_ID'] ? ' selected' : '' ?>>
              <?= htmlspecialchars($d['DEPT_SPECIALIZATION']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Actions row (buttons under the search bar) -->
    <div class="filter-actions">
      <button class="btn" type="submit">Apply</button>
      <a class="clear-btn" href="<?= BASE_URL ?>/public/faculty.php">Clear</a>
    </div>
  </form>

  <p class="muted" style="margin:6px 0 12px;">Showing <?= (int)$total ?> <?= $total===1 ? 'faculty' : 'faculty members' ?></p>

  <!-- Cards -->
  <?php if (!$rows): ?>
    <div class="panel">No matching faculty.</div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($rows as $row): ?>
        <div class="card">
          <div class="card__icon">👩‍🏫</div>
          <div class="card__content">
            <h3 class="card__title">
              <?= htmlspecialchars($row['FACULTY_LNAME'] . ', ' . $row['FACULTY_FNAME']); ?>
            </h3>
            <p class="card__desc">
              <?= htmlspecialchars($row['RANK_DESCRIPTION']); ?> · <?= htmlspecialchars($row['DEPT_SPECIALIZATION']); ?>
            </p>
            <div class="card__meta">
              📧 <?= htmlspecialchars($row['FACULTY_EMAIL']); ?>
            </div>
          </div>
          <div class="card__actions">
            <button class="btn small"
              data-read-more
              data-type="faculty"
              data-id="<?= (int)$row['FACULTY_ID']; ?>">
              Read More
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <div class="pagination">
    <?php
      $queryParams = $_GET;
      unset($queryParams['page']);
      $baseQuery = http_build_query($queryParams);
      $baseUrl   = '?' . ($baseQuery ? $baseQuery . '&' : '');
    ?>

    <?php if ($page > 1): ?>
      <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="page-btn">&#x276E;</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="<?= $baseUrl ?>page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="page-btn">&#x276F;</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
