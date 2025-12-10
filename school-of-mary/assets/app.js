// Core utilities and UI logic
(function () {
  "use strict";

  /* ------------------------------
      Helpers
  ------------------------------ */
  // Short alias for document.querySelector
  const qs  = (sel, ctx = document) => ctx.querySelector(sel);
  // Short alias for document.querySelectorAll (converts NodeList to Array)
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  // Wrapper for addEventListener
  function on(el, evt, fn, opts) {
    el.addEventListener(evt, fn, opts || false);
  }

  // Event Delegation: Attaches a listener to a parent (root) but fires only if the target matches 'sel'
  // Efficient for dynamic elements like lists or table rows
  function delegate(root, evt, sel, fn) {
    on(root, evt, (e) => {
      const t = e.target.closest(sel);
      if (t && root.contains(t)) fn(e, t);
    });
  }

  // Converts form fields into a simple JSON object
  function serializeForm(form) {
    const data = new FormData(form);
    const obj = {};
    for (const [k, v] of data.entries()) {
      obj[k] = v;
    }
    return obj;
  }

  /* ------------------------------
      Sticky Topbar Logic
  ------------------------------ */
  const topbar = qs("#topbar");
  
  // Adds a shadow/style class to the topbar when the user scrolls down
  const addScrolledClass = () => {
    if (!topbar) return;
    if (window.scrollY > 4) {
      topbar.style.transform = "translateY(-1px)";
      topbar.classList.add("topbar--scrolled");
    } else {
      topbar.style.transform = "translateY(0)";
      topbar.classList.remove("topbar--scrolled");
    }
  };
  
  // Run on load and on scroll
  addScrolledClass();
  on(window, "scroll", addScrolledClass, { passive: true });

  /* ------------------------------
      Dropdown Keyboard Support
  ------------------------------ */
  // Makes dropdown menus accessible via Enter/Space keys and closes them on Escape
  qsa(".dropdown").forEach((dd) => {
    const btn = qs("button, .btn", dd);
    const menu = qs(".dropdown__menu", dd);
    if (!btn || !menu) return;

    // Toggle logic for keyboard users
    on(btn, "keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const open = menu.style.display === "block";
        menu.style.display = open ? "none" : "block";
        // If opening, focus the first link inside
        if (!open) {
          const first = menu.querySelector("a,button");
          if (first) first.focus();
        }
      }
      // Close on Escape
      if (e.key === "Escape") {
        menu.style.display = "none";
        btn.focus();
      }
    });

    // Close if clicking anywhere outside the dropdown
    on(document, "click", (e) => {
      if (!dd.contains(e.target)) {
        menu.style.display = "none";
      }
    });
  });
  
  /* ------------------------------
      Toast Notification System
  ------------------------------ */
  // Creates and displays a popup message
  function toast(msg = "Saved", timeout = 2200) {
    let t = qs("#toast");
    
    // Create the element if it doesn't exist yet
    if (!t) {
      t = document.createElement("div");
      t.id = "toast";
      // Apply inline styles for fixed positioning
      Object.assign(t.style, {
        position: "fixed",
        bottom: "14px",
        left: "50%",
        transform: "translateX(-50%)",
        background: "rgba(0,0,0,.84)",
        color: "#fff",
        padding: "8px 12px",
        borderRadius: "8px",
        fontSize: "14px",
        zIndex: 3000,
        boxShadow: "0 10px 24px rgba(0,0,0,.25)",
        opacity: "0",
        transition: "opacity .18s ease"
      });
      document.body.appendChild(t);
    }
    
    // Show the message
    t.textContent = msg;
    t.style.opacity = "1";
    
    // Fade out after timeout
    setTimeout(() => { t.style.opacity = "0"; }, timeout);
  }

  /* ------------------------------
      Modal Logic
  ------------------------------ */
  const modal      = qs("#modal");
  const modalForm  = qs("#modal-form", modal || document);
  const modalTitle = qs("#modal-title", modal || document);

  // Function to show the modal
  function openModal(title = "Edit") {
    if (!modal) return;
    modalTitle && (modalTitle.textContent = title);
    modal.removeAttribute("hidden"); // Shows the element via CSS

    // Auto-focus the first input for better UX
    const firstField = modalForm && modalForm.querySelector("input,select,textarea,button");
    if (firstField) firstField.focus();
  }

  // Function to close the modal and cleanup form fields
  function closeModal() {
    if (!modal) return;
    // Add class for fade-out animation
    modal.classList.add("modal--closing");
    
    setTimeout(() => {
      modal.classList.remove("modal--closing");
      modal.setAttribute("hidden", "");
      
      // Remove dynamically added inputs (elements with class .field)
      if (modalForm) {
        qsa(".field", modalForm).forEach((f) => f.remove());
        // Reset the 'action' hidden input
        const act = modalForm.querySelector('input[name="action"]');
        if (act) act.value = "";
      }
    }, 180); // Matches CSS transition duration
  }

  if (modal) {
    // Event Delegate: Open modal when clicking buttons with [data-modal]
    delegate(document, "click", "[data-modal]", (e, btn) => {
      const name = btn.getAttribute("data-modal");
      const title = btn.getAttribute("data-title") || "Edit";
      
      // Dynamic Content Insertion
      // If data-template is present, find that <template> ID and clone its content into the form
      const tplId = btn.getAttribute("data-template");
      if (tplId && modalForm) {
        const tpl = qs(tplId);
        if (tpl) {
          const clone = tpl.content ? tpl.content.cloneNode(true) : tpl.cloneNode(true);
          const actions = qs(".modal__actions", modalForm);
          modalForm.insertBefore(clone, actions);
        }
      }

      // Set 'action' hidden input if specified
      const action = btn.getAttribute("data-action");
      if (action && modalForm) {
        const actInput = modalForm.querySelector('input[name="action"]');
        if (actInput) actInput.value = action;
      }

      // Transfer hidden data attributes (data-hidden-*)
      // This allows passing IDs (like user_id) from the button to the form
      if (modalForm) {
        Array.from(btn.attributes).forEach((attr) => {
          if (attr.name.startsWith("data-hidden-")) {
            const name = attr.name.replace("data-hidden-", "");
            const value = attr.value;
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value;
            modalForm.insertBefore(input, qs(".modal__actions", modalForm));
          }
        });
      }

      openModal(title);
    });

    // Event Delegate: Close buttons
    delegate(modal, "click", "[data-close='modal']", () => closeModal());

    // Close when clicking the dark overlay background
    on(modal, "click", (e) => {
      const dialog = qs(".modal__dialog", modal);
      // Check if the click was *outside* the dialog box
      if (dialog && !dialog.contains(e.target)) {
        closeModal();
      }
    });

    // Close on Escape key
    on(document, "keydown", (e) => {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) {
        closeModal();
      }
    });

    // Prevent double submissions: Disable button on submit
    if (modalForm) {
      on(modalForm, "submit", () => {
        const btn = modalForm.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Saving…";
          // Re-enable after 2.5s (in case the server response is slow or fails silently)
          setTimeout(() => { btn.disabled = false; btn.textContent = "Save"; }, 2500);
        }
      });
    }
  }

  /* ------------------------------
      Smooth Scroll for Anchor Links
  ------------------------------ */
  delegate(document, "click", 'a[href^="#"]', (e, link) => {
    const id = link.getAttribute("href").slice(1);
    const target = id && qs(`#${CSS.escape(id)}`);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });

  /* ------------------------------
      Expose API to Window
  ------------------------------ */
  // Allows other scripts to use these internal tools
  window.SOM = {
    toast,
    modal: { open: openModal, close: closeModal },
    utils: { qs, qsa, serializeForm }
  };
})();

/* ------------------------------
      Hero Slider Module
  ------------------------------ */
(function () {
  const hero = document.querySelector('.hero');
  if (!hero) return;

  const slides = Array.from(hero.querySelectorAll('.hero__slide'));
  const dots = Array.from(hero.querySelectorAll('.hero__dots button'));
  let i = 0;
  let timer = null;

  // Switches to slide 'n'
  function show(n) {
    slides.forEach((s, idx) => s.classList.toggle('is-active', idx === n));
    dots.forEach((d, idx) => d.classList.toggle('is-active', idx === n));
    i = n;
  }

  // Cycles to next slide (wrapping around using modulo)
  function next() {
    show((i + 1) % slides.length);
  }

  // Event Listeners for Dots
  dots.forEach((dot, idx) => {
    dot.addEventListener('click', () => {
      show(idx);
      restart(); // Reset timer so it doesn't jump immediately after click
    });
  });

  // Timer logic
  function start() {
    timer = setInterval(next, 5000); // 5 seconds per slide
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }
  function restart() {
    stop(); start();
  }

  // Pause on hover
  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);

  // Initialize
  show(0);
  start();
})();

/* ------------------------------
      Read More / Details Overlay
  ------------------------------ */
// Handles fetching Faculty or Research details via AJAX and showing them in a side panel
(() => {
  const overlay = document.getElementById('overlay');
  const bodyEl  = document.getElementById('overlay-body');
  const titleEl = document.getElementById('overlay-title');

  if (!overlay || !bodyEl || !titleEl) return;

  // Show/Hide Helpers
  function openOverlay(title) {
    titleEl.textContent = title || 'Details';
    bodyEl.innerHTML = '<div class="overlay__loading">Loading…</div>';
    overlay.removeAttribute('hidden');
  }
  function closeOverlay() { overlay.setAttribute('hidden', ''); }

  // Close Events (Backdrop click or Escape key)
  overlay.addEventListener('click', (e) => {
    if (e.target.matches('[data-close="overlay"], .overlay__backdrop')) closeOverlay();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeOverlay();
  });

  // Main Event Listener: Clicks on "Read More" buttons
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-read-more]");
    if (!btn) return;

    // Get metadata from button attributes
    const id = btn.dataset.id;
    const type = btn.dataset.type; // 'faculty' or 'research'
    const title = btn.dataset.title || "Details";

    openOverlay(title);
    
    // Fetch Data from API
    try {
      // Assumes the API is located at this path
      const res = await fetch(`school-of-mary/public/api/get_${type}_details.php?id=${id}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      
      // Select the correct render function based on type
      if (type === "faculty") {
        bodyEl.innerHTML = renderFacultyDetail(data);
        titleEl.textContent = data.faculty.FULL_NAME || "Faculty Details";
      } else {
        bodyEl.innerHTML = renderResearchDetail(data);
        titleEl.textContent = data.RESEARCH_TITLE || "Research Details";
      }
    } catch (err) {
      bodyEl.innerHTML = `<p style="color:#b00020">Failed to load details. ${String(err)}</p>`;
    }
  });

  // Render Faculty HTML
  function renderFacultyDetail(d) {
    const f = d.faculty || {};
      const projs = d.projects || [];
      // Loop through projects and create HTML cards
      const projectsHTML = projs.length
        ? projs.map(p => `
          <div class="panel" style="margin-bottom:8px">
            <b>${escapeHtml(p.RESEARCH_TITLE)}</b><br>
            Status: ${escapeHtml(p.RESEARCH_STATUS)}<br>
            Start: ${escapeHtml(p.RESEARCH_STARTDATE)}
            ${p.RESEARCH_ENDDATE ? " · End: " + escapeHtml(p.RESEARCH_ENDDATE) : ""}
            ${p.AGENCY_NAME ? `<br>Agency: ${escapeHtml(p.AGENCY_NAME)} (₱${escapeHtml(p.FUNDING_AMOUNT || '—')})` : ""}
          </div>
        `).join('')
        : "<p>No assigned research projects.</p>";
       
       return `
        <div class="detail-grid">
          <div></div> <div>
            <h4>${escapeHtml(f.FULL_NAME || '')}</h4>
            <p class="kv"><b>Email:</b> ${escapeHtml(f.FACULTY_EMAIL || '')}</p>
            <p class="kv"><b>Rank:</b> ${escapeHtml(f.RANK_DESCRIPTION || '')}</p>
            <p class="kv"><b>Department:</b> ${escapeHtml(f.DEPARTMENT || '')} (${escapeHtml(f.DEPT_CLASSIFICATION || '')})</p>
            <div class="section-title">Research & Funding</div>
            ${projectsHTML}
          </div>
        </div>
      `;
    }

  // Render Research HTML
  function renderResearchDetail(d) {
    const funds = (d.funding || []).map(f => `
      <li>${escapeHtml(f.AGENCY_NAME)} — ₱${escapeHtml(f.FUNDING_AMOUNT || '0.00')} (${escapeHtml(f.DATE_FUNDED || '—')})</li>
    `).join('');
    
    const people = (d.people || []).map(p => `
      <span class="badge">${escapeHtml(p.FACULTY_LNAME)}, ${escapeHtml(p.FACULTY_FNAME)}${p.ROLE_ID ? ` · ${escapeHtml(p.ROLE_ID)}` : ''}</span>
    `).join('');

    return `
      <div class="detail-grid">
        <div></div>
        <div>
          <h4>${escapeHtml(d.RESEARCH_TITLE || '')}</h4>
          <p class="kv"><b>Status:</b> ${escapeHtml(d.RESEARCH_STATUS || '')}</p>
          <p class="kv"><b>Start:</b> ${escapeHtml(d.RESEARCH_STARTDATE || '')}</p>
          <p class="kv"><b>End:</b> ${escapeHtml(d.RESEARCH_ENDDATE || '—')}</p>
          <div class="section-title">Funding</div>
          <ul class="list">${funds || '<li>No funding recorded.</li>'}</ul>
          <p class="kv"><b>Total Funding:</b> ₱${escapeHtml(d.total_funding || '0.00')}</p>
        </div>
      </div>
    `;
  }

  // Security: XSS Protection
  // Replaces characters that could be interpreted as code
  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }
})();

/* ------------------------------
      Admin Calendar & Dummy Events
  ------------------------------ */
(function() {
    const calendarEl = document.getElementById('calendar-app');
    if (!calendarEl) return;

    const monthYearEl = document.getElementById('current-month-year');
    const daysGridEl = document.getElementById('calendar-days');
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');
    
    // Hardcoded events for demonstration 
    const DUMMY_EVENTS = {
        // --- November 2025 ---
        '2025-11-01': [{ title: 'All Saints Day (Holiday)', type: 'holiday' }],
        '2025-11-30': [{ title: 'Bonifacio Day (Holiday)', type: 'holiday' }],
        
        // --- December 2025 ---
        '2025-12-24': [{ title: 'Christmas Eve (Special Holiday)', type: 'holiday' }],
        '2025-12-25': [{ title: 'Christmas Day (Holiday)', type: 'holiday' }],
        '2025-12-30': [{ title: 'Rizal Day (Holiday)', type: 'holiday' }],
        '2025-12-31': [{ title: 'New Year\'s Eve (Special Holiday)', type: 'holiday' }],
        
        // --- January 2026 ---
        '2026-01-01': [{ title: 'New Year\'s Day (Holiday)', type: 'holiday' }],
        
        // --- February 2026 ---
        '2026-02-25': [{ title: 'EDSA Revolution Anniversary (Holiday)', type: 'holiday' }],
        
        // --- April 2026 ---
        '2026-04-09': [{ title: 'Araw ng Kagitingan (Holiday)', type: 'holiday' }],
        '2026-04-10': [{ title: 'Good Friday (Holiday)', type: 'holiday' }],
        
        // --- May 2026 ---
        '2026-05-01': [{ title: 'Labor Day (Holiday)', type: 'holiday' }],
        
        // --- June 2026 ---
        '2026-06-12': [{ title: 'Independence Day (Holiday)', type: 'holiday' }],
        
        // --- August 2026 ---
        '2026-08-21': [{ title: 'Ninoy Aquino Day (Holiday)', type: 'holiday' }],
        '2026-08-31': [{ title: 'National Heroes Day (Holiday)', type: 'holiday' }],
        
        // --- November 2026 ---
        '2026-11-01': [{ title: 'All Saints Day (Holiday)', type: 'holiday' }],
        '2026-11-30': [{ title: 'Bonifacio Day (Holiday)', type: 'holiday' }],
    };

    let currentDate = new Date();
    currentDate.setDate(1); // Always start calculations from the 1st

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Update header
        monthYearEl.textContent = `${monthNames[month]} ${year}`;
        daysGridEl.innerHTML = ''; // Clear previous days

        // Determine start day of the month (0=Sun, 6=Sat)
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        // Determine number of days in the month
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        // Determine days in the previous month
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        // Check current date (used for highlighting today)
        const today = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

        // Fill leading days from the previous month
        for (let i = 0; i < firstDayOfMonth; i++) {
            const dayNum = daysInPrevMonth - firstDayOfMonth + i + 1;
            const cell = document.createElement('div');
            cell.classList.add('calendar-day', 'calendar-day--outside');
            cell.innerHTML = `<span class="calendar-day-number">${dayNum}</span>`;
            daysGridEl.appendChild(cell);
        }

        // Fill current month days
        for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const cell = document.createElement('div');
            cell.classList.add('calendar-day');
            // Highlight today
            if (dateStr === todayStr) {
                cell.classList.add('calendar-day--today');
            }

            cell.innerHTML = `<span class="calendar-day-number">${i}</span>`;
            
            // Add events for this day
            const events = DUMMY_EVENTS[dateStr];
            if (events) {
                events.forEach(event => {
                    const eventEl = document.createElement('span');
                    eventEl.classList.add('calendar-event', `type-${event.type}`);
                    eventEl.title = event.title; // This enables the hover/tooltip effect
                    eventEl.textContent = event.title;
                    cell.appendChild(eventEl);
                });
            }

            daysGridEl.appendChild(cell);
        }

        // Fill trailing days from the next month
        const totalCells = firstDayOfMonth + daysInMonth;
        //  Check if we need a 6th row (42 cells total)
        const totalGridCells = totalCells > 35 ? 42 : 35;
        const remainingCells = totalGridCells - totalCells; 

        for (let i = 1; i <= remainingCells; i++) {
            const cell = document.createElement('div');
            cell.classList.add('calendar-day', 'calendar-day--outside');
            cell.innerHTML = `<span class="calendar-day-number">${i}</span>`;
            daysGridEl.appendChild(cell);
        }
    }

    function changeMonth(delta) {
        currentDate.setMonth(currentDate.getMonth() + delta);
        renderCalendar();
    }

    // Navigation Listeners
    prevBtn.addEventListener('click', () => changeMonth(-1));
    nextBtn.addEventListener('click', () => changeMonth(1));

    renderCalendar();
})();

/* ------------------------------
      Research Date Validation
  ------------------------------ */
// Ensures that the 'End Date' cannot be selected before the 'Start Date'
(function() {
    // Select inputs for the "Create" form (static on page)
    const createStartDateEl = document.getElementById('research_startdate');
    const createEndDateEl = document.getElementById('research_enddate');

    // Select inputs for the "Edit" form (dynamic/inside modal)
    const editStartDateEl = document.getElementById('m_start');
    const editEndDateEl = document.getElementById('m_end');

    // Logic: When Start Date changes, update the Min attribute of End Date
    function enforceDateConstraint(startEl, endEl) {
        if (!startEl || !endEl) return;

        function validateAndSetMin() {
            const startDateValue = startEl.value;
            
            // Set min limit
            endEl.min = startDateValue;
            
            // If current end date is now invalid (less than start), clear it
            if (endEl.value && startDateValue && endEl.value < startDateValue) {
                endEl.value = ''; 
            }
        }
        
        startEl.addEventListener('change', validateAndSetMin);
        // Also check when focusing the end date field
        endEl.addEventListener('focus', validateAndSetMin, { once: true });
        
        // Run immediately if values are pre-filled
        if (startEl === createStartDateEl) {
            validateAndSetMin();
        }
    }
    
    // Apply to both forms
    enforceDateConstraint(createStartDateEl, createEndDateEl);
    enforceDateConstraint(editStartDateEl, editEndDateEl);

    // Since the Edit Modal is hidden, we watch for when it becomes visible to re-apply the constraints correctly
    const modal = document.getElementById('researchModal');
    if (modal) {
        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'hidden' && !modal.hidden) {
                    enforceDateConstraint(editStartDateEl, editEndDateEl);
                }
            });
        }).observe(modal, { attributes: true });
    }
    
})();

/**
 * 6. SIDEBAR RESPONSIVE LOGIC
 * Handles the mobile toggle menu and sidebar state classes.
 */
/* ------------------------------
      Responsive Sidebar Logic
  ------------------------------ */
document.addEventListener('DOMContentLoaded', function() {
    // Desktop Collapse Button (inside the sidebar)
    const internalToggleButton = document.getElementById('sidebar-toggle-internal');
    // Mobile Overlay Button (external, visible only on small screens)
    const mobileToggleButton = document.getElementById('sidebar-toggle-mobile');
    const appWrapper = document.getElementById('app-wrapper');

    if (appWrapper) {
        // Initial State Check based on screen width
        if (window.innerWidth > 1024) {
             appWrapper.classList.remove('sidebar-closed'); 
        } else {
             appWrapper.classList.remove('sidebar-open');
        }

        // Internal Toggle (Desktop collapse)
        if (internalToggleButton) {
            internalToggleButton.addEventListener('click', function() {
                if (window.innerWidth > 1024) {
                    appWrapper.classList.toggle('sidebar-closed');
                }
            });
        }
        
        // Mobile Toggle (Hamburger menu)
        if (mobileToggleButton) {
            mobileToggleButton.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    const isNowOpen = appWrapper.classList.toggle('sidebar-open');
                    
                    // Update button text
                    if (isNowOpen) {
                        mobileToggleButton.innerHTML = '✕ Close';
                    } else {
                        mobileToggleButton.innerHTML = '☰ Menu';
                    }
                }
            });
        }
        
        // Close sidebar automatically when a link is clicked (Mobile UX)
        const sidebarLinks = appWrapper.querySelectorAll('.sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024 && appWrapper.classList.contains('sidebar-open')) {
                    appWrapper.classList.remove('sidebar-open');
                    if (mobileToggleButton) {
                        mobileToggleButton.innerHTML = '☰ Menu';
                    }
                }
            });
        });
    }
});