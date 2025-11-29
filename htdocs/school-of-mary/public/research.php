<?php
$pageTitle = 'Research';
require_once __DIR__ . '/../partials/site_header.php';

$admin = is_admin();
/* --- Lookups --- */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

/* --- Detail view --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pagination setup (These are only used for list view if no JS is present, but kept for list view parameters)
$perPage = 6;
$page    = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$offset  = ($page - 1) * $perPage;

if ($id > 0) {
  // ... (Detail view remains unchanged)
  $stmt = $pdo->prepare("
    SELECT re.*
    FROM RESEARCH re
    WHERE re.RESEARCH_ID = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $research = $stmt->fetch();

  if ($research) {
    // Assignments/Faculty fetch
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

    // Funding fetch
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
        <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
          <?= htmlspecialchars($research['RESEARCH_STATUS']); ?>
        </span>
        <span style="margin-left:6px;">Start: <?= htmlspecialchars($research['RESEARCH_STARTDATE']); ?></span>
        <?php if ($research['RESEARCH_ENDDATE']) : ?>
          <span style="margin-left:6px;">End: <?= htmlspecialchars($research['RESEARCH_ENDDATE']); ?></span>
        <?php endif; ?>
      </div>
      <?php if($admin):?>
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
      <?php else: ?>
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
      <?php endif; ?>
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

/* --- List view (filters + AJAX container) --- */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

?>

<style>
.panel {
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.filterbar {
    position: relative; /* Essential for absolute positioning of the dropdown */
    margin-bottom: 20px;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.field label {
    font-weight: 500;
    color: #4b5563;
}
.field .input { 
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    height: 38px; 
    box-sizing: border-box;
}

/* --- SEARCH BAR AND TOGGLE STYLES (Full Width, Matching Look) --- */
.searchbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 15px;
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    width: 100%; /* Full width */
    box-sizing: border-box;
}
.searchbox svg:first-child {
    color: #6b7280;
}
.searchbox input[type="search"] { 
    flex-grow: 1;
    border: none;
    padding: 0;
    height: 1.5em; 
    font-size: 1rem;
}
.filter-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #4b5563; 
}
.filter-toggle-btn:hover {
    color: #1f2937;
}

/* --- ADVANCED FILTER PANEL STYLES --- */
#filter-dropdown {
    display: none; /* Initially hidden */
    position: absolute;
    top: 100%; 
    right: 0; /* Aligned to the right edge of the filterbar/container */
    margin-top: 8px;
    width: min(100%, 480px); /* Max width for panel */
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 15px;
}
#filter-options {
    display: grid;
    /* 3 columns for Status, Start, End */
    grid-template-columns: repeat(3, 1fr); 
    gap: 10px;
    margin-bottom: 15px;
}
.clear-btn-container {
    text-align: left;
}
.clear-btn-container .btn-primary {
    background-color: #2563eb;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
.clear-btn-container .btn-primary:hover {
    background-color: #1d4ed8;
}
</style>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Browse the research database system of School of Mary.</p>

  <form method="get" class="filterbar" id="research-filter-form">
    
    <div class="searchbox">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search research titles…" id="q-input" />
      
      <button class="filter-toggle-btn" id="filter-btn" type="button" onclick="toggleFilters(event)">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 6h18L12 18 3 6z"/>
          <polyline points="7 10 12 15 17 10"></polyline>
        </svg>
      </button>
    </div>
    
    <div id ="filter-dropdown">
        <div id="filter-options">
          <div class="field">
            <label>Status</label>
            <select class="input" name="status" id="status-select">
              <option value="">All</option>
              <?php foreach ($statuses as $s): ?>
                <option value="<?= htmlspecialchars($s['STATUS_CODE']) ?>"<?= $status===$s['STATUS_CODE'] ? ' selected' : '' ?>>
                  <?= htmlspecialchars($s['STATUS_LABEL']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="field">
            <label>Start from</label>
            <input class="input" type="date" name="from" id="from-input" value="<?= htmlspecialchars($from) ?>" />
          </div>

          <div class="field">
            <label>End by</label>
            <input class="input" type="date" name="to" id="to-input" value="<?= htmlspecialchars($to) ?>" />
          </div>
        </div>
        
        <div class="clear-btn-container">
          <button class="btn-primary" type="button" onclick="clearFilters(event)">Clear Filters</button>
        </div>
    </div>
  </form>

  <div id="research-results" class="fade-in">
    <div class='loading'>Loading initial results...</div>
  </div>

</section>

<script>
  const resultsContainer = document.querySelector('#research-results');
  const qInput = document.querySelector('#q-input');
  const statusSelect = document.querySelector('#status-select');
  const fromInput = document.querySelector('#from-input');
  const toInput = document.querySelector('#to-input');
  const filterDropdown = document.querySelector('#filter-dropdown');
  let timer = null;
  
  // Toggle visibility of the filter dropdown
  function toggleFilters(e) {
      if (e) e.preventDefault();
      if (filterDropdown.style.display === "none" || filterDropdown.style.display === "") {
        filterDropdown.style.display = "block";
      } else {
        filterDropdown.style.display = "none";
      }
  }

  // Clear button function
  function clearFilters(e) {
      if (e) e.preventDefault();
      
      // Reset inputs
      qInput.value = '';
      statusSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      
      // Fetch results to show the unfiltered list
      fetchResults(1);

      // Hide the filter panel
      filterDropdown.style.display = "none";
  }

  // --- Live Search / Fetching Logic ---
  function fetchResults(page) {
    const q = qInput.value;
    const status = statusSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    
    // Construct the URL to the API endpoint
    const url = `api/search_research.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&page=${page}`;
    
    resultsContainer.innerHTML = "<div class='loading'>Loading results...</div>";
    
    fetch(url)
      .then(res => {
        if (!res.ok) throw new Error('Network response was not ok.');
        return res.text();
      })
      .then(html => {
        resultsContainer.innerHTML = html;
        attachPaginationEvents(); 
      })
      .catch (err => {
        resultsContainer.innerHTML = "<div class='error'>Failed to load research results.</div>";
        console.error("Error fetching research: ", err);
      });
  }
  
  // Debounced input for text search (prevents excessive API calls)
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  
  // Instant fetch for select/date changes
  function handleFilterChange() {
    clearTimeout(timer); // Clear any pending debounced search
    fetchResults(1); 
  }

  // --- Event Handlers ---
  
  // 1. Text Search Input (Debounced)
  qInput.addEventListener('input', handleLiveInput);
  
  // 2. Select and Date Inputs (Immediate fetch on change)
  statusSelect.addEventListener('change', handleFilterChange);
  fromInput.addEventListener('change', handleFilterChange);
  toInput.addEventListener('change', handleFilterChange);
  
  // 3. Prevent form default submission
  document.getElementById('research-filter-form').addEventListener('submit', function(e) {
      e.preventDefault();
      fetchResults(1);
  });
  
  // 4. Pagination Event Listener Attachment
  function attachPaginationEvents() {
    const links = document.querySelectorAll('.pagination .page-btn');

    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            const url = new URL(this.href);
            const page = url.searchParams.get('page') || 1;
            
            // Sync filters with URL parameters
            qInput.value = url.searchParams.get('q') || '';
            statusSelect.value = url.searchParams.get('status') || '';
            fromInput.value = url.searchParams.get('from') || '';
            toInput.value = url.searchParams.get('to') || '';

            fetchResults(page);
        });
    });
  }

  // Initial load when the page is ready
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>