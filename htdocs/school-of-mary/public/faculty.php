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
                  <span style="margin-left:6px;">
                    <i class="bi bi-person-badge-fill"></i> Role: <?= htmlspecialchars($p['ROLE_ID']); ?>
                  </span>
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
.filterbar {
    display: flex;
    flex-direction: column;
}
.filter-inputs {
    display: flex;
    flex-wrap: wrap;
    align-items: center; 
    gap: 10px;
}
.searchbox {
    position: relative; 
    display: flex; 
    align-items: center;
    gap: 8px; 
    padding: 8px 12px; 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    flex: 1 1 360px;
}
.searchbox svg:first-child, .searchbox i:first-child { 
    color: #6b7280;
}
.searchbox input[type="search"] {
    flex-grow: 1;
    border: none;
    padding: 0;
    height: 1.5em; 
}
.searchbox .filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--color-accent);
}

#filter-dropdown {
    position: absolute;
    top: 100%; 
    right: 0;
    margin-top: 8px;
    width: min(100%, 500px); 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 12px;
    display: none; 
}
#filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end; 
}
#filter-options .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
#filter-options .field:first-child,
#filter-options .field:nth-child(2) {
    flex: 1 1 180px; 
}
#filter-options .clear-btn {
    min-width: 100px;
    height: 40px; 
    padding: 8px 12px;
}
.field .input { 
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}
.searchbox .filter-btn i { 
    color: var(--color-accent);
}
</style>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Explore the faculty of School of Mary</p>

  <form method="get" class="filterbar" id="form" style="margin-bottom:14px;">
    <div class="filter-inputs">
      <div class="searchbox">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name or email…" />
        
        <button class="filter-btn" id="filter-btn" type="button" onclick="showHide()">
          <i class="bi bi-filter"></i>
        </button>
        
        <div id ="filter-dropdown">
            <div id="filter-options">
            <div class="field">
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
            <div class="field">
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
            <button class="btn clear-btn" type="button" onclick="clearFilter()" id="clear-btn">Clear Filters</button>
          </div>
        </div>
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
  let timer = null;
  

  function showHide() {
    var f = document.getElementById("filter-dropdown");
    // Toggle the display property
    if (f.style.display === "none" || f.style.display === "") {
      f.style.display = "block";
    } else {
      f.style.display = "none";
    }
  }
  function clearFilter() {
    if((queryInput.value == '') && (rankSelect.value == '') && (deptSelect.value == '')) {
      return;
    } else {
      rankSelect.value = '';
      deptSelect.value = '';
      fetchResults(1);
    }
    document.getElementById("filter-dropdown").style.display = "none";
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
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>