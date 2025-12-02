<?php
// File: admin/audit_print.php
$pageTitle = 'Audit Log';

require_once __DIR__ . '/../partials/init.php';

// Security Check
if (!is_admin()) { redirect_to('/admin/login.php'); }

// Lookups for the Filter Dropdown
$tables  = ['FACULTY','RESEARCH','AGENCY','FUNDING','ASSIGNMENT'];
$actions = ['CREATE','UPDATE','DELETE','IMPORT'];

require_once __DIR__ . '/../partials/site_header.php';
?>

<style>
/* --- STYLES (Adapted from your Faculty.php) --- */
.field { display: flex; flex-direction: column; gap: 4px; }
.field label { font-weight: 500; color: #4b5563; }
.field .input { 
  padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; height: 38px; box-sizing: border-box;
}

/* SEARCH BAR CONTAINER */
.searchbox {
  font-family: 'Newsreader', serif;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 15px;
  background: #fff;
  border: 1px solid #c7d2e4;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  flex: 1; 
  box-sizing: border-box;
  width: auto;
  height: 57px;
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
    font-family: 'Newsreader', serif;
}

/* BUTTONS */
.filter-toggle-btn, .sort-toggle-btn {
  display: flex; align-items: center; justify-content: center;
  padding: 0; background: transparent; border: none; cursor: pointer; color: #4b5563; 
}
.filter-toggle-btn:hover, .sort-toggle-btn:hover { color: #1f2937; }

.sort-toggle-btn {
  border: 1px solid #c7d2e4; border-radius: 8px; width: 40px; height: 57px; flex-shrink: 0;
}
.sort-toggle-btn:hover { background: rgba(0, 0, 0, 0.05); border-radius: 6px; }

/* DROPDOWNS */
#filter-dropdown, #sort-dropdown {
  display: none; position: absolute; top: 100%; right: 0; margin-top: 8px;
  background: #fff; border: 1px solid #c7d2e4; border-radius: 8px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1); z-index: 1000; padding: 15px;
}
#filter-dropdown { width: min(100%, 550px); }
#sort-dropdown { width: min(100%, 200px); }

#filter-options { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px; }

/* SORT RADIO BUTTONS */
#sort-dropdown fieldset { border: none; padding: 0; margin: 0; }
#sort-dropdown fieldset legend { font-weight: 600; margin-bottom: 10px; color: #4b5563; }
#sort-dropdown fieldset > div { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
#sort-dropdown input[type="radio"] { transform: scale(1.5); cursor: pointer; margin: 0; }
#sort-dropdown label { cursor: pointer; margin: 0; }

/* CLEAR BUTTON */
.clear-btn-container .btn-primary {
  background-color: #0b5394; color: white; padding: 10px 15px; border: none;
  border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%;
}

/* PRINT BUTTON STYLE */
.btn-action {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:130px; height:40px; padding:0 16px;
  border-radius:8px; border:1px solid #0b5394;
  font-weight:600; text-decoration:none; cursor:pointer;
  background:#0b5394; color:#fff;
  font-family: 'Newsreader', serif;
}
.btn-action:hover { filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }

/* HIDE ELEMENTS WHEN PRINTING */
@media print {
  .crud-header-card, .filterbar, .pagination, .btn-action { display: none !important; }
  body, .container { margin: 0 !important; padding: 0 !important; }
  .panel { box-shadow: none; border: none; }
}

/* TABLE STYLING */
.table-scroll table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.table-scroll th, .table-scroll td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.table-scroll th:nth-child(1), .table-scroll td:nth-child(1) { width: 60px; } /* ID */
.table-scroll th:nth-child(2), .table-scroll td:nth-child(2) { width: 180px; } /* Date */
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <button class="btn-action" onclick="window.print()" style="min-width:160px; font-family: 'Newsreader', serif;">
      <span style="font-size:1.2em; margin-right:8px; ">&#x1F5B6;&#xFE0F;</span> Print Audit Log
    </button>
  </div>

  <h1 style="margin: 0;">Audit Log</h1>
  <p class="muted" style="margin-bottom:10px;">Track and review history of all system changes.</p>
  
  <form method="get" class="filterbar" onsubmit="return false;">
    <div class="searchbox" style="font-family: 'Newsreader', serif;">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" type="search" name="actor" placeholder="Search actor" autocomplete="off">
      
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button>
    </div>
    
    <div id="filter-dropdown">
      <div id="filter-options">
        <div class="field">
          <label>Action</label>
          <select class="input" name="action">
            <option value="">All</option>
            <?php foreach ($actions as $a): ?>
              <option value="<?= $a; ?>"><?= $a; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Table</label>
          <select class="input" name="table">
            <option value="">All</option>
            <?php foreach ($tables as $t): ?>
              <option value="<?= $t; ?>"><?= $t; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>From</label>
          <input class="input" type="date" name="from">
        </div>
        <div class="field">
          <label>To</label>
          <input class="input" type="date" name="to">
        </div>
      </div>
      <div class="clear-btn-container">
        <button class="btn-primary" id="clear-btn" type="button" style="font-family: 'Newsreader', serif;">Clear Filters</button>
      </div>
    </div>

    <button class="sort-toggle-btn" id="sort-btn" title="Sort" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l4 -4l4 4m-4 -4v14" /><path d="M21 15l-4 4l-4 -4m4 4v-14" /></svg>
    </button>
    
    <div class="field" id="sort-dropdown">
        <fieldset>
          <legend>Sort by:</legend>
          <div>
            <input type="radio" name="sort" value="newest" checked> <label>Newest First</label>
          </div>
          <div>
            <input type="radio" name="sort" value="oldest"> <label>Oldest First</label>
          </div>
          <div>
            <input type="radio" name="sort" value="actor_asc"> <label>Actor (A–Z)</label>
          </div>
          <div>
            <input type="radio" name="sort" value="actor_desc"> <label>Actor (Z–A)</label>
          </div>
        </fieldset>
    </div>
  </form>
</section>

<section class="panel" id="panel" style="background:#fff; min-height:200px;"></section>

<script>
  // --- DOM Elements ---
  const panel        = document.querySelector('#panel');
  const actorInput   = document.querySelector('input[name="actor"]');
  const actionSelect = document.querySelector('select[name="action"]');
  const tableSelect  = document.querySelector('select[name="table"]');
  const fromInput    = document.querySelector('input[name="from"]');
  const toInput      = document.querySelector('input[name="to"]');
  
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton   = document.querySelector('#filter-btn');
  const sortDropdown   = document.querySelector('#sort-dropdown');
  const sortButton     = document.querySelector('#sort-btn');
  const clearBtn       = document.querySelector('#clear-btn');
  const sortRadios     = document.querySelectorAll('input[name="sort"]');

  let timer = null;

  // --- 1. Fetch Logic ---
  
  // Helper to get currently selected radio value
  function getSelectedSort() {
    const checked = document.querySelector('input[name="sort"]:checked');
    return checked ? checked.value : 'newest';
  }

  // Main function to call API
  function fetchResults(page) {
    const params = new URLSearchParams({
      actor:  actorInput.value,
      action: actionSelect.value,
      table:  tableSelect.value,
      from:   fromInput.value,
      to:     toInput.value,
      sort:   getSelectedSort(),
      page:   page
    });

    // Path to the API file we created
    const url = `api/search_audit.php?${params.toString()}`;

    // Show loading state
    panel.innerHTML = "<div style='padding:20px; text-align:center; color:#666;'>Loading...</div>";

    fetch(url)
      .then(res => res.text())
      .then(html => {
        panel.innerHTML = html;
        attachPaginationEvents(); // Re-attach listeners to new links
      })
      .catch(err => {
        panel.innerHTML = "<div style='padding:20px; color:red; text-align:center;'>Error loading data.</div>";
        console.error(err);
      });
  }

  // --- 2. Event Listeners ---
  
  // Live Search (Debounce 300ms)
  actorInput.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  });

  // Filters (Immediate fetch)
  [actionSelect, tableSelect, fromInput, toInput].forEach(el => {
    el.addEventListener('change', () => fetchResults(1));
  });

  // Sorting
  sortRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      fetchResults(1);
      sortDropdown.style.display = 'none'; // Close dropdown after selection
    });
  });

  // Pagination Link Handling (Prevent full page reload)
  function attachPaginationEvents() {
    const links = panel.querySelectorAll('.page-btn');
    links.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        // Parse the 'page' param from the link's href
        const urlObj = new URL(this.href);
        const page = urlObj.searchParams.get('page') || 1;
        fetchResults(page);
      });
    });
  }

  // --- 3. UI Toggle Logic (Dropdowns) ---
  
  // Toggle Filter
  filterButton.addEventListener('click', (e) => {
    e.stopPropagation();
    sortDropdown.style.display = 'none';
    filterDropdown.style.display = (filterDropdown.style.display === 'block') ? 'none' : 'block';
  });

  // Toggle Sort
  sortButton.addEventListener('click', (e) => {
    e.stopPropagation();
    filterDropdown.style.display = 'none';
    sortDropdown.style.display = (sortDropdown.style.display === 'block') ? 'none' : 'block';
  });

  // Close dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    if (!filterDropdown.contains(e.target) && e.target !== filterButton) {
      filterDropdown.style.display = 'none';
    }
    if (!sortDropdown.contains(e.target) && e.target !== sortButton) {
      sortDropdown.style.display = 'none';
    }
  });

  // Clear Filters Button
  clearBtn.addEventListener('click', () => {
    actorInput.value = '';
    actionSelect.value = '';
    tableSelect.value = '';
    fromInput.value = '';
    toInput.value = '';
    filterDropdown.style.display = 'none';
    fetchResults(1);
  });

  // --- 4. Initial Load ---
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>