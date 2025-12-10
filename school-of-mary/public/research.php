<?php
// Page Title
$pageTitle = 'Research';
// Header
require_once __DIR__ . '/../partials/site_header.php';

$admin = is_admin();

// Filter Lookups
// Fetch data to populate filter dropdowns
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();

// Detail View Logic
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Fetch Research Details
    $stmt = $pdo->prepare("SELECT re.* FROM RESEARCH re WHERE re.RESEARCH_ID = ? LIMIT 1");
    $stmt->execute([$id]);
    $research = $stmt->fetch();

    if ($research) {
        // Fetch Assigned Faculty
        $as = $pdo->prepare("
            SELECT a.ASSIGNMENT_ID, a.DATE_ASSIGNED, a.ROLE_ID,
                   f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME,
                   r.RANK_DESCRIPTION,
                   ro.ROLE_DESCRIPTION 
            FROM ASSIGNMENT a
            JOIN FACULTY f ON f.FACULTY_ID = a.FACULTY_ID
            JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
            JOIN `ROLE` ro ON ro.ROLE_ID = a.ROLE_ID 
            WHERE a.RESEARCH_ID = ?
            ORDER BY a.DATE_ASSIGNED DESC, a.ASSIGNMENT_ID DESC
        ");
        $as->execute([$id]);
        $people = $as->fetchAll();

        // Fetch Funding
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
        // If none (no assigned funding)
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
        <button class="btn small" style="float:right;margin-top:1px;" onclick="history.back()">Back</button>
        
        <h1 style="margin:6px;">
            <?= htmlspecialchars($research['RESEARCH_TITLE']); ?>
        </h1>
        
        <div class="muted" style="margin-bottom:10px;">
           <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
             <?= htmlspecialchars($research['RESEARCH_STATUS']); ?>
           </span>
           <span style="margin-left:6px;">
             Start: <?= htmlspecialchars($research['RESEARCH_STARTDATE']); ?>
             <?php if (!empty($research['RESEARCH_ENDDATE'])) echo ' · End: ' . htmlspecialchars($research['RESEARCH_ENDDATE']); ?>
           </span>
        </div>

        <div class="panel" style="background:#fff;">
          <div class="grid">
             <div class="field" style="grid-column: span 6;">
               <label>Title:</label>
               <span class="input" readonly style="color:#0b5394; font-family: 'Newsreader', serif; border: none"><?= htmlspecialchars($research['RESEARCH_TITLE']); ?></span>
             </div>
             <div class="field" style="grid-column: span 3;">
               <label>Status:</label>
               <span class="input" readonly style="color:#0b5394; font-family: 'Newsreader', serif; border: none"><?= htmlspecialchars($research['RESEARCH_STATUS']); ?></span>
             </div>
             <div class="field" style="grid-column: span 3;">
               <label>Total Funding:</label>
               <span class="input" readonly style="color:#0b5394; font-family: 'Newsreader', serif; border: none"><?= '₱ ' . number_format($totalFunding, 2); ?></span>
             </div>
          </div>
        </div>

        <h2 style="font-family:'Patua One',serif; margin-top:18px; margin-bottom:16px;">Assigned Faculty</h2>
        <?php if (empty($people)): ?>
            <div class="panel">No faculty assigned to this project.</div>
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
                    · <span style="color:#0b5394"><?= htmlspecialchars($p['ROLE_DESCRIPTION']); ?></span>
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

// List View Logic
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
?>

<style>
.panel {
    border-radius: 8px;
}
.filterbar {
    position: relative; /* Absolute positioning for dropdown */
    margin-bottom: 20px;
}
.field {
    display: flex;
    flex-direction: column;
}
.field label {
    font-weight: 500;
    color: #4b5563;
}
.field .input { 
    padding: 5px;
    height: 38px; 
    font-family: 'Newsreader', serif;
}
/* Search Bar and Toggle  */
.searchbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 15px;
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    width: 100%;
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

/* Advanced Filter Panel */
#filter-dropdown {
    display: none; 
    position: absolute;
    top: 100%; 
    right: 0; 
    margin-top: 8px;
    width: min(100%, 480px); 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 15px;
}
#filter-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr); 
    gap: 10px;
    margin-bottom: 15px;
}
.clear-btn-container {
    text-align: left;
}
.clear-btn-container .btn-primary {
    background-color: #0b5394;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    width: 100%; /* Full width within container */
}
.clear-btn-container .btn-primary:hover {
    background-color: #0b5394;
}
</style>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Browse the research project directory and explore faculty contributors.</p>
  <!-- Filter Bar -->
  <form method="get" class="filterbar" id="form" style="margin-bottom:14px;">
      <!-- Inputs row -->
      <div class="searchbox" style="font-family: 'Newsreader', serif;">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" style="width: 85%; font-family: 'Newsreader', serif;" placeholder="Search project title" autocomplete="off"/>
        <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" onclick="toggleFilters(event)">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
          </svg>
        </button>
      </div>
      <div id ="filter-dropdown">
          <div id="filter-options">
            <!-- Status -->
            <div class="field" style="grid-column: span 2;">
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
            <!-- Start From & End By -->
            <div class="field">
              <label>Start From</label>
              <input type="date" class="input" name="from" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="field">
              <label>End By</label>
              <input type="date" class="input" name="to" value="<?= htmlspecialchars($to) ?>">
            </div>
          </div>
          <div class="clear-btn-container">
            <button class="btn-primary" type="button" onclick="clearFilters(event)" style="font-family: 'Newsreader', serif;">Clear Filters</button>
          </div>
      </div>
  </form>

  <div id="research-results" class="fade-in"></div>
</section>

<script>
  // DOM Elements
  const researchResults = document.querySelector('#research-results');
  const queryInput = document.querySelector('input[name="q"]');
  const statusSelect = document.querySelector('select[name="status"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton = document.querySelector('#filter-btn');
  let timer = null; // Debounce timer
  
  // Toggle visibility of the filter dropdown
  function toggleFilters(e) {
      if (e) e.preventDefault();
      e.stopPropagation(); 
      if (filterDropdown.style.display === "none" || filterDropdown.style.display === "") {
        filterDropdown.style.display = "block";
      } else {
        filterDropdown.style.display = "none";
      }
  }
  // Clear filters and reset search
  function clearFilters(e) {
      if (e) e.preventDefault();
      queryInput.value = '';
      statusSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      
      fetchResults(1); // Refresh results

      filterDropdown.style.display = "none"; // Close dropdown
  }
  // Close dropdown when clicking outside
  document.addEventListener('click', function(e) {
    if (!filterDropdown.contains(e.target) && e.target !== filterButton) {
      filterDropdown.style.display = "none";
    }
  });
  // Fetch results based on current filters and page
  function fetchResults(page) {
    const q = queryInput.value;
    const status = statusSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    const url = `api/search_research.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&page=${page}`;
    
    researchResults.innerHTML = "<div class='loading'>Loading...</div>";
    fetch(url)
      .then(res => res.text())
      .then(html => {
        researchResults.innerHTML = html;
        // Reattach pagination events
        attachPaginationEvents();
      })
      .catch (err => {
        researchResults.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error: ", err);
      })
  }
  
  // Debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300); 
  }

  // Event Listeners
  queryInput.addEventListener('input', handleLiveInput);
  
  // Instant update on specific filter changes
  statusSelect.addEventListener('change', () => fetchResults(1)); 
  fromInput.addEventListener('change', () => fetchResults(1));
  toInput.addEventListener('change', () => fetchResults(1));
  
  // Attach pagination link events
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

  fetchResults(1);
  attachPaginationEvents();
</script>

<?php 
// Footer
require_once __DIR__ . '/../partials/site_footer.php'; 
?>