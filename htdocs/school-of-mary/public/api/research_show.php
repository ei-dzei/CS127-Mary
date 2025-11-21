<?php
  $pageTitle = 'Research Projects';
  require_once __DIR__ . '/../partials/site_header.php';

  /* --- Lookups for filters (example status) --- */
  // NOTE: Replace this array with a database query if your statuses are dynamic
  $statuses = ['Ongoing', 'Completed', 'Proposed', 'On Hold']; 

  /* --- List view filters (from query parameters) --- */
    $q      = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');
?>

<style>
/* ------------------------------------------- */
/* --- CSS for Filter Bar Alignment (Aligned to Faculty Page) --- */
/* ------------------------------------------- */
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
.searchbox i:first-child { /* Targets the search icon (bi-search) */
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
    width: min(100%, 700px); /* Adjust width for date fields */
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
/* Ensure fields have a consistent width */
#filter-options .field:nth-child(1) {
    flex: 1 1 150px; /* Status */
}
#filter-options .field:nth-child(2), 
#filter-options .field:nth-child(3) {
    flex: 1 1 120px; /* Dates */
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

/* Ensure the card icon shows up if needed (for list view cards, if present) */
.card__icon i.bi {
    font-size: 2rem;
    color: var(--color-accent, #007bff);
}
</style>

<section class="container fade-in" style="margin-top:6px;">
  <h1 style="margin-bottom:6px;">Research Projects</h1>
  <p class="muted" style="margin-bottom:10px;">View and search current and past research projects.</p>

  <form method="get" class="filterbar" id="form" style="margin-bottom:14px;">
    <div class="filter-inputs">
      <div class="searchbox">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by project title…" />
        
        <button class="filter-btn" id="filter-btn" type="button" onclick="showHide()">
          <i class="bi bi-filter"></i>
        </button>
        
        <div id ="filter-dropdown">
            <div id="filter-options">
            
            <div class="field">
              <label>Status</label>
              <select class="input" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= htmlspecialchars($s) ?>"<?= $status===$s ? ' selected' : '' ?>>
                    <?= htmlspecialchars($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="field">
                <label>Start Date (From)</label>
                <input class="input" type="date" name="from" value="<?= htmlspecialchars($from) ?>" />
            </div>

            <div class="field">
                <label>End Date (To)</label>
                <input class="input" type="date" name="to" value="<?= htmlspecialchars($to) ?>" />
            </div>
            
            <button class="btn clear-btn" type="button" onclick="clearFilter()" id="clear-btn">Clear Filters</button>
          </div>
        </div>
      </div>
    </div>
  </form>
  <div id="research-results" class="fade-in"></div>
</section>

<script>
  const researchResults = document.querySelector('#research-results');
  const queryInput = document.querySelector('input[name="q"]');
  const statusSelect = document.querySelector('select[name="status"]');
  const fromInput = document.querySelector('input[name="from"]'); 
  const toInput = document.querySelector('input[name="to"]'); 
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
    // Check all inputs
    if((queryInput.value == '') && (statusSelect.value == '') && (fromInput.value == '') && (toInput.value == '')) {
      return;
    } else {
      queryInput.value = ''; 
      statusSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      fetchResults(1);
    }
    document.getElementById("filter-dropdown").style.display = "none";
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
  fromInput.addEventListener('change', () => fetchResults(1));
  toInput.addEventListener('change', () => fetchResults(1));
  
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
  // Load initial results
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>