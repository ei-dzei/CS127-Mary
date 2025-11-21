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
/* --- CSS for Filter Bar Alignment --- */
/* ------------------------------------------- */
:root {
    /* Define color-accent if not already defined in site_header */
    --color-accent: #007bff; 
}

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
    flex: 1 1 360px; /* Allows search box to grow and maintain minimum width */
}
.searchbox i:first-child { 
    color: #6b7280;
}
.searchbox input[type="search"] {
    flex-grow: 1;
    border: none;
    padding: 0;
    height: 1.5em; 
    /* Remove default focus ring if preferred */
    outline: none; 
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

/* Dropdown Container */
#filter-dropdown {
    position: absolute;
    top: 100%; /* Position right below the searchbox */
    right: 0;
    margin-top: 8px;
    width: min(100vw, 700px); /* Max width, but responsive */
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 12px;
    
    /* Initially hidden */
    display: none; 
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

/* Class to show the dropdown */
#filter-dropdown.is-active {
    display: block; 
    opacity: 1;
    transform: translateY(0);
}

#filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end; /* Align inputs/selects to the bottom */
}

/* Field styling for better structure */
#filter-options .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.field label {
    font-size: 0.9em;
    color: #4b5563;
    font-weight: 500;
}
.field .input { 
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    height: 40px; /* Standard height */
}

/* Ensure fields have a consistent width (adjust based on design needs) */
#filter-options .field:nth-child(1) {
    flex: 1 1 150px; /* Status */
}
#filter-options .field:nth-child(2), 
#filter-options .field:nth-child(3) {
    flex: 1 1 120px; /* Dates */
}

#filter-options .clear-btn {
    /* Use 'btn' class styling if available, otherwise just use these styles */
    min-width: 100px;
    height: 40px; 
    padding: 8px 12px;
    background-color: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    margin-left: auto; /* Push clear button to the right on larger screens */
}

/* Adjust layout for smaller screens */
@media (max-width: 500px) {
    #filter-options {
        flex-direction: column; /* Stack fields vertically */
        align-items: stretch;
    }
    #filter-options .field {
        flex: 1 1 100%; 
    }
    #filter-options .clear-btn {
        margin-left: 0;
    }
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
        
        <button class="filter-btn" id="filter-toggle-btn" type="button" aria-expanded="false" aria-controls="filter-dropdown">
          <i class="bi bi-filter"></i>
        </button>
        
        <div id ="filter-dropdown">
            <div id="filter-options">
            
            <div class="field">
              <label for="status-select">Status</label>
              <select class="input" name="status" id="status-select">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= htmlspecialchars($s) ?>"<?= $status===$s ? ' selected' : '' ?>>
                    <?= htmlspecialchars($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="field">
                <label for="from-input">Start Date (From)</label>
                <input class="input" type="date" name="from" id="from-input" value="<?= htmlspecialchars($from) ?>" />
            </div>

            <div class="field">
                <label for="to-input">End Date (To)</label>
                <input class="input" type="date" name="to" id="to-input" value="<?= htmlspecialchars($to) ?>" />
            </div>
            
            <button class="btn clear-btn" type="button" id="clear-btn">Clear Filters</button>
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
  
  // New elements for dropdown
  const filterDropdown = document.getElementById("filter-dropdown");
  const filterToggleButton = document.getElementById("filter-toggle-btn");
  const clearButton = document.getElementById("clear-btn");
  
  let timer = null;
  
  // 1. Dropdown Toggle Function
  function toggleFilterDropdown() {
    const isActive = filterDropdown.classList.toggle("is-active");
    // Update ARIA attribute for accessibility
    filterToggleButton.setAttribute('aria-expanded', isActive);
  }
  
  // 2. Clear Filter Function
  function clearFilter() {
    // Check if any filter is active before proceeding
    if(queryInput.value === '' && statusSelect.value === '' && fromInput.value === '' && toInput.value === '') {
      return;
    }
    
    queryInput.value = ''; 
    statusSelect.value = '';
    fromInput.value = '';
    toInput.value = '';
    
    // Trigger a new search with clear values
    fetchResults(1);
    
    // Close the dropdown after clearing
    if (filterDropdown.classList.contains("is-active")) {
        toggleFilterDropdown();
    }
  }
  
  // 3. Main Fetch Function (Unchanged)
  function fetchResults(page) {
    const q = queryInput.value;
    const status = statusSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    
    // Properly encode query parameters
    const params = new URLSearchParams({
        q: q,
        status: status,
        from: from,
        to: to,
        page: page
    }).toString();
    
    const url =  `api/search_research.php?${params}`;
    
    
    researchResults.innerHTML = "<div class='loading'>Loading...</div>";
    fetch(url)
      .then(res => {
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.text();
      })
      .then(html => {
        researchResults.innerHTML = html;
        attachPaginationEvents();
      })
      .catch (err => {
        researchResults.innerHTML = "<div class='error'>Failed to load results. </div>";
        console.error("Error: ", err);
      })
  }
  
  // 4. Debounced Input Handler (Unchanged)
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);//300ms
  }
  
  // 5. Attach Event Listeners
  filterToggleButton.addEventListener('click', toggleFilterDropdown);
  clearButton.addEventListener('click', clearFilter);
  
  queryInput.addEventListener('input', handleLiveInput);
  statusSelect.addEventListener('change', () => fetchResults(1));
  fromInput.addEventListener('change', () => fetchResults(1));
  toInput.addEventListener('change', () => fetchResults(1));
  
  // 6. Pagination Event Listener (Unchanged)
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