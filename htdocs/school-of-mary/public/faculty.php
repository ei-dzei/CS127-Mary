<?php
  $pageTitle = 'Faculty';
  require_once __DIR__ . '/../partials/site_header.php';

  /* --- Lookups for filters --- */
  $ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
  $depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();

  /* --- Detail view (if ?id=) --- */
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    width: 95.7%;
}
.clear-btn-container .btn-primary:hover {
    background-color: #0b5394;
}
</style>
<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Explore the faculty of School of Mary</p>

  <!-- Filter Bar -->
  <form method="get" class="filterbar" id="form" style="margin-bottom:14px;">
    <!-- Inputs row -->
    
      <div class="searchbox">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" style="width: 85%;"placeholder="Search by name or email…" />
        <!-- <input type="reset" value="X" alt="Clear the search form"> -->
        <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" onclick="toggleFilters(event)">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
          </svg>
        </button>
      </div>
      <div id ="filter-dropdown">
          <div id="filter-options">
            <!-- Rank -->
            <div class="field" style="width:200px;" display="inline-block">
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
            <div class="field" style="width:200px;" display="inline-block">
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
          <div class="clear-btn-container">
            <button class="btn-primary" type="button" onclick="clearFilters(event)">Clear Filters</button>
          </div>
        </div>
    
  </form>

  <div id="faculty-results" class="fade-in"></div>
</section>

<script>
  const facultyResults = document.querySelector('#faculty-results');
  const queryInput = document.querySelector('input[name="q"]');
  const rankSelect = document.querySelector('select[name="rank"]');
  const deptSelect = document.querySelector('select[name="dept"]'); 
  const filterDropdown = document.querySelector('#filter-dropdown')
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
      queryInput.value = '';
      rankSelect.value = '';
      deptSelect.value = '';
      
      // Fetch results to show the unfiltered list
      fetchResults(1);

      // Hide the filter panel
      filterDropdown.style.display = "none";
  }
  //fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const rank = rankSelect.value;
    const dept = deptSelect.value;
    const url =  `api/search_faculty.php?q=${q}&rank=${rank}&dept=${dept}&page=${page}`;
    
    
    facultyResults.innerHTML = "<div class='loading'>Loading...</div>";
    fetch(url)
      .then(res => res.text())
      .then(html => {
        facultyResults.innerHTML = html;
        attachPaginationEvents();
      })
      .catch (err => {
        facultyResults.innerHTML = "<div class='error'>Failed to load results. </div>";
        console.error("Error: ", err);
      })
  }
  
  //Debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);//300ms
  }
  
  queryInput.addEventListener('input', handleLiveInput);
  rankSelect.addEventListener('change', () => fetchResults(1));
  deptSelect.addEventListener('change', () => fetchResults(1));
  
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
