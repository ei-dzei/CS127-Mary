<?php
$pageTitle = 'Research';
require_once __DIR__ . '/../partials/site_header.php';

/* --- Lookups --- */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

/* --- Detail view --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
  <section class="container fade-in">
    <?php if (!$research): ?>
      <h1>Research</h1>
      <p class="muted">Record not found.</p>
      <p><a class="btn small" href="<?= BASE_URL ?>/public/research.php">Back to list</a></p>
    <?php else: ?>
      <a class="btn small" href="<?= BASE_URL ?>/public/research.php" style="float:right;margin-top:-4px;">← Back</a>
      <h1 style="margin-bottom:6px"><?= htmlspecialchars($research['RESEARCH_TITLE']); ?></h1>
      <div class="muted" style="margin-bottom:10px;">
        <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
          <?= htmlspecialchars($research['RESEARCH_STATUS']); ?>
        </span>
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

/* --- List view (filters + pagination) --- */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

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
        <button class="btn filter-btn" id="filter-btn" type="button" onclick="showHide()">Filter</button>
        <div id="filter-dropdown">
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
          <button class="btn clear-btn" type="button" onclick="clearFilter()" id="clear-btn">Clear</button>
          </div>
        </div>
      </div>

      
    </div>

  </form>

  <div id="research-results"></div>
</section>
<script>
  const researchResults = document.querySelector('#research-results');
  const queryInput =  document.querySelector('input[name="q"]');
  const statusSelect = document.querySelector('select[name="status"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');
  let timer = null;

  function showHide() {
    var f = document.getElementById("filter-dropdown");
    if (f.style.display === "none") {
      f.style.display = "block";
    } else {
      f.style.display = "none";
    }
  }
  function clearFilter() {
    if((queryInput.value == '') && (statusSelect.value == '') && (fromInput.value == '') && (toInput.value == '')) {
      return;
    } else {
      // queryInput.value = '';
      statusSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      fetchResults(1);
    }
  }
  //fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const status = statusSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    const url =  `api/search_research.php?q=${q}&status=${status}&from=${from}&to=${to}&page=${page}`;
    
    
    researchResults.innerHTML = "<div class='loading'>Loading...</div>";
    fetch(url)
      .then(res => res.text())
      .then(html => {
        researchResults.innerHTML = html;
        attachPaginationEvents();
      })
      .catch (err => {
        researchResults.innerHTML = "<div class='error'>Failed to load results. </div>";
        console.error("Error: ", err);
      })
  }
  
  //Debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);//300ms
  }

  queryInput.addEventListener('input', handleLiveInput);
  statusSelect.addEventListener('change', () => fetchResults(1));
  fromInput.addEventListener('input', handleLiveInput);
  toInput.addEventListener('input', handleLiveInput);

  function attachPaginationEvents() {
    const links = document.querySelectorAll('.page-btn');

    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            const url = new URL(this.href);
            const page = url.searchParams.get('page') || 1;

            fetchResults(page);
        });
    });
  }
  //Load all faculty
  fetchResults(1);
  attachPaginationEvents();
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>