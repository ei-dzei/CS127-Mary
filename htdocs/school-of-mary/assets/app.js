(function () {
  "use strict";

  /* ------------------------------
     Helpers
  ------------------------------ */
  const qs  = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  function on(el, evt, fn, opts) {
    el.addEventListener(evt, fn, opts || false);
  }

  function delegate(root, evt, sel, fn) {
    on(root, evt, (e) => {
      const t = e.target.closest(sel);
      if (t && root.contains(t)) fn(e, t);
    });
  }

  function serializeForm(form) {
    const data = new FormData(form);
    const obj = {};
    for (const [k, v] of data.entries()) {
      obj[k] = v;
    }
    return obj;
  }

  /* ------------------------------
     Sticky topbar
  ------------------------------ */
  const topbar = qs("#topbar");
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
  addScrolledClass();
  on(window, "scroll", addScrolledClass, { passive: true });

  /* ------------------------------
     Dropdown keyboard support
  ------------------------------ */
  qsa(".dropdown").forEach((dd) => {
    const btn = qs("button, .btn", dd);
    const menu = qs(".dropdown__menu", dd);
    if (!btn || !menu) return;

    // Toggle on Enter/Space
    on(btn, "keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const open = menu.style.display === "block";
        menu.style.display = open ? "none" : "block";
        if (!open) {
          const first = menu.querySelector("a,button");
          if (first) first.focus();
        }
      }
      if (e.key === "Escape") {
        menu.style.display = "none";
        btn.focus();
      }
    });

    // Close if clicking outside
    on(document, "click", (e) => {
      if (!dd.contains(e.target)) {
        menu.style.display = "none";
      }
    });
  });
  
  /* ------------------------------
     Toast (small inline feedback)
  ------------------------------ */
  function toast(msg = "Saved", timeout = 2200) {
    let t = qs("#toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "toast";
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
    t.textContent = msg;
    t.style.opacity = "1";
    setTimeout(() => { t.style.opacity = "0"; }, timeout);
  }

  const modal      = qs("#modal");
  const modalForm  = qs("#modal-form", modal || document);
  const modalTitle = qs("#modal-title", modal || document);

  function openModal(title = "Edit") {
    if (!modal) return;
    modalTitle && (modalTitle.textContent = title);
    modal.removeAttribute("hidden");

    // Focus the first input inside the form
    const firstField = modalForm && modalForm.querySelector("input,select,textarea,button");
    if (firstField) firstField.focus();
  }

  function closeModal() {
    if (!modal) return;
    // Close animation
    modal.classList.add("modal--closing");
    setTimeout(() => {
      modal.classList.remove("modal--closing");
      modal.setAttribute("hidden", "");
      // Clear dynamic fields (keep csrf/action + actions)
      if (modalForm) {
        qsa(".field", modalForm).forEach((f) => f.remove());
        // Reset action to blank
        const act = modalForm.querySelector('input[name="action"]');
        if (act) act.value = "";
      }
    }, 180);
  }

  if (modal) {
    // Open: any element with data-modal and optional data-title
    delegate(document, "click", "[data-modal]", (e, btn) => {
      const name = btn.getAttribute("data-modal");
      const title = btn.getAttribute("data-title") || "Edit";
      
      const tplId = btn.getAttribute("data-template");
      if (tplId && modalForm) {
        const tpl = qs(tplId);
        if (tpl) {
          // Clone children
          const clone = tpl.content ? tpl.content.cloneNode(true) : tpl.cloneNode(true);
          // Insert before .modal__actions (keep actions last)
          const actions = qs(".modal__actions", modalForm);
          modalForm.insertBefore(clone, actions);
        }
      }
      // Fill action if provided
      const action = btn.getAttribute("data-action");
      if (action && modalForm) {
        const actInput = modalForm.querySelector('input[name="action"]');
        if (actInput) actInput.value = action;
      }
      // Optional hidden inputs transfer (data-hidden-*)
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

    // Close buttons
    delegate(modal, "click", "[data-close='modal']", () => closeModal());

    // Close by clicking overlay (outside dialog)
    on(modal, "click", (e) => {
      const dialog = qs(".modal__dialog", modal);
      if (dialog && !dialog.contains(e.target)) {
        closeModal();
      }
    });

    // Escape closes
    on(document, "keydown", (e) => {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) {
        closeModal();
      }
    });

    // Prevent double submit; keep default form POST (PHP handles)
    if (modalForm) {
      on(modalForm, "submit", () => {
        const btn = modalForm.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Saving…";
          setTimeout(() => { btn.disabled = false; btn.textContent = "Save"; }, 2500);
        }
      });
    }
  }

  /* ------------------------------
     Link: smooth scroll
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
     Expose a small API
  ------------------------------ */
  window.SOM = {
    toast,
    modal: { open: openModal, close: closeModal },
    utils: { qs, qsa, serializeForm }
  };
})();
/* -------------------------
    HERO SLIDER 
------------------------- */
(function () {
  const hero = document.querySelector('.hero');
  if (!hero) return;

  const slides = Array.from(hero.querySelectorAll('.hero__slide'));
  const dots = Array.from(hero.querySelectorAll('.hero__dots button'));
  let i = 0;
  let timer = null;

  function show(n) {
    slides.forEach((s, idx) => s.classList.toggle('is-active', idx === n));
    dots.forEach((d, idx) => d.classList.toggle('is-active', idx === n));
    i = n;
  }

  function next() {
    show((i + 1) % slides.length);
  }

  dots.forEach((dot, idx) => {
    dot.addEventListener('click', () => {
      show(idx);
      restart();
    });
  });

  function start() {
    timer = setInterval(next, 7000);
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }
  function restart() {
    stop(); start();
  }

  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);

  // initialize
  show(0);
  start();
})();

/* -------------------------
    READ MORE OVERLAY
------------------------- */

(() => {
  const overlay = document.getElementById('overlay');
  const bodyEl  = document.getElementById('overlay-body');
  const titleEl = document.getElementById('overlay-title');

  if (!overlay || !bodyEl || !titleEl) return;

  function openOverlay(title) {
    titleEl.textContent = title || 'Details';
    bodyEl.innerHTML = '<div class="overlay__loading">Loading…</div>';
    overlay.removeAttribute('hidden');
  }
  function closeOverlay() { overlay.setAttribute('hidden', ''); }

  overlay.addEventListener('click', (e) => {
    if (e.target.matches('[data-close="overlay"], .overlay__backdrop')) closeOverlay();
});

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeOverlay();
  });

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-read-more]");
    if (!btn) return;

    const id = btn.dataset.id;
    const type = btn.dataset.type;
    
    const title = btn.dataset.title || "Details";

    openOverlay(title);
    
    try {
      const res = await fetch(`school-of-mary/public/api/get_${type}_details.php?id=${id}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      
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

  function renderFacultyDetail(d) {
    const f = d.faculty || {};
      const projs = d.projects || [];
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
          <div></div>
          <div>
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

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }
})();


/* -------------------------
    ADMIN CALENDAR WIDGET
------------------------- */
(function() {
    const calendarEl = document.getElementById('calendar-app');
    if (!calendarEl) return;

    const monthYearEl = document.getElementById('current-month-year');
    const daysGridEl = document.getElementById('calendar-days');
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');
    
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
    currentDate.setDate(1); // Set to the 1st of the current month

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


        // 1. Fill leading days from the previous month
        for (let i = 0; i < firstDayOfMonth; i++) {
            const dayNum = daysInPrevMonth - firstDayOfMonth + i + 1;
            const cell = document.createElement('div');
            cell.classList.add('calendar-day', 'calendar-day--outside');
            cell.innerHTML = `<span class="calendar-day-number">${dayNum}</span>`;
            daysGridEl.appendChild(cell);
        }

        // 2. Fill current month days
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

        // 3. Fill trailing days from the next month
        const totalCells = firstDayOfMonth + daysInMonth;
        // Check if we need a 6th row (42 cells total)
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

    // Attach event listeners
    prevBtn.addEventListener('click', () => changeMonth(-1));
    nextBtn.addEventListener('click', () => changeMonth(1));

    // Initial render
    renderCalendar();
})();

(function () {
  "use strict";

  /* ------------------------------
     Helpers
  ------------------------------ */
  const qs  = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  function on(el, evt, fn, opts) {
    el.addEventListener(evt, fn, opts || false);
  }

  function delegate(root, evt, sel, fn) {
    on(root, evt, (e) => {
      const t = e.target.closest(sel);
      if (t && root.contains(t)) fn(e, t);
    });
  }

  function serializeForm(form) {
    const data = new FormData(form);
    const obj = {};
    for (const [k, v] of data.entries()) {
      obj[k] = v;
    }
    return obj;
  }

  /* ------------------------------
     Sticky topbar
  ------------------------------ */
  const topbar = qs("#topbar");
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
  addScrolledClass();
  on(window, "scroll", addScrolledClass, { passive: true });

  /* ------------------------------
     Dropdown keyboard support
  ------------------------------ */
  qsa(".dropdown").forEach((dd) => {
    const btn = qs("button, .btn", dd);
    const menu = qs(".dropdown__menu", dd);
    if (!btn || !menu) return;

    // Toggle on Enter/Space
    on(btn, "keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const open = menu.style.display === "block";
        menu.style.display = open ? "none" : "block";
        if (!open) {
          const first = menu.querySelector("a,button");
          if (first) first.focus();
        }
      }
      if (e.key === "Escape") {
        menu.style.display = "none";
        btn.focus();
      }
    });

    // Close if clicking outside
    on(document, "click", (e) => {
      if (!dd.contains(e.target)) {
        menu.style.display = "none";
      }
    });
  });
  
  /* ------------------------------
     Toast (small inline feedback)
  ------------------------------ */
  function toast(msg = "Saved", timeout = 2200) {
    let t = qs("#toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "toast";
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
    t.textContent = msg;
    t.style.opacity = "1";
    setTimeout(() => { t.style.opacity = "0"; }, timeout);
  }

  /* ------------------------------
     Modal logic
     - Open buttons: [data-modal="edit"] or [data-modal="open"]
     - Close buttons: [data-close="modal"] or overlay click
     - Pages inject field blocks into #modal-form
  ------------------------------ */
  const modal      = qs("#modal");
  const modalForm  = qs("#modal-form", modal || document);
  const modalTitle = qs("#modal-title", modal || document);

  function openModal(title = "Edit") {
    if (!modal) return;
    modalTitle && (modalTitle.textContent = title);
    modal.removeAttribute("hidden");

    // Focus the first input inside the form
    const firstField = modalForm && modalForm.querySelector("input,select,textarea,button");
    if (firstField) firstField.focus();
  }

  function closeModal() {
    if (!modal) return;
    // Close animation
    modal.classList.add("modal--closing");
    setTimeout(() => {
      modal.classList.remove("modal--closing");
      modal.setAttribute("hidden", "");
      // Clear dynamic fields (keep csrf/action + actions)
      if (modalForm) {
        qsa(".field", modalForm).forEach((f) => f.remove());
        // Reset action to blank
        const act = modalForm.querySelector('input[name="action"]');
        if (act) act.value = "";
      }
    }, 180);
  }

  if (modal) {
    // Open: any element with data-modal and optional data-title
    delegate(document, "click", "[data-modal]", (e, btn) => {
      const name = btn.getAttribute("data-modal");
      const title = btn.getAttribute("data-title") || "Edit";
      
      const tplId = btn.getAttribute("data-template");
      if (tplId && modalForm) {
        const tpl = qs(tplId);
        if (tpl) {
          // Clone children
          const clone = tpl.content ? tpl.content.cloneNode(true) : tpl.cloneNode(true);
          // Insert before .modal__actions (keep actions last)
          const actions = qs(".modal__actions", modalForm);
          modalForm.insertBefore(clone, actions);
        }
      }
      // Fill action if provided
      const action = btn.getAttribute("data-action");
      if (action && modalForm) {
        const actInput = modalForm.querySelector('input[name="action"]');
        if (actInput) actInput.value = action;
      }
      // Optional hidden inputs transfer (data-hidden-*)
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

    // Close buttons
    delegate(modal, "click", "[data-close='modal']", () => closeModal());

    // Close by clicking overlay (outside dialog)
    on(modal, "click", (e) => {
      const dialog = qs(".modal__dialog", modal);
      if (dialog && !dialog.contains(e.target)) {
        closeModal();
      }
    });

    // Escape closes
    on(document, "keydown", (e) => {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) {
        closeModal();
      }
    });

    // Prevent double submit; keep default form POST (PHP handles)
    if (modalForm) {
      on(modalForm, "submit", () => {
        const btn = modalForm.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Saving…";
          setTimeout(() => { btn.disabled = false; btn.textContent = "Save"; }, 2500);
        }
      });
    }
  }

  /* ------------------------------
     Link: smooth scroll
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
     Expose a small API
  ------------------------------ */
  window.SOM = {
    toast,
    modal: { open: openModal, close: closeModal },
    utils: { qs, qsa, serializeForm }
  };
})();

/* -------------------------
    HERO SLIDER 
------------------------- */
(function () {
  const hero = document.querySelector('.hero');
  if (!hero) return;

  const slides = Array.from(hero.querySelectorAll('.hero__slide'));
  const dots = Array.from(hero.querySelectorAll('.hero__dots button'));
  let i = 0;
  let timer = null;

  function show(n) {
    slides.forEach((s, idx) => s.classList.toggle('is-active', idx === n));
    dots.forEach((d, idx) => d.classList.toggle('is-active', idx === n));
    i = n;
  }

  function next() {
    show((i + 1) % slides.length);
  }

  dots.forEach((dot, idx) => {
    dot.addEventListener('click', () => {
      show(idx);
      restart();
    });
  });

  function start() {
    timer = setInterval(next, 7000);
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }
  function restart() {
    stop(); start();
  }

  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);

  // initialize
  show(0);
  start();
})();

/* -------------------------
    READ MORE OVERLAY
------------------------- */

(() => {
  const overlay = document.getElementById('overlay');
  const bodyEl  = document.getElementById('overlay-body');
  const titleEl = document.getElementById('overlay-title');

  if (!overlay || !bodyEl || !titleEl) return;

  function openOverlay(title) {
    titleEl.textContent = title || 'Details';
    bodyEl.innerHTML = '<div class="overlay__loading">Loading…</div>';
    overlay.removeAttribute('hidden');
  }
  function closeOverlay() { overlay.setAttribute('hidden', ''); }

  overlay.addEventListener('click', (e) => {
    if (e.target.matches('[data-close="overlay"], .overlay__backdrop')) closeOverlay();
});

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeOverlay();
  });

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-read-more]");
    if (!btn) return;

    const id = btn.dataset.id;
    const type = btn.dataset.type;
    
    const title = btn.dataset.title || "Details";

    openOverlay(title);
    
    try {
      const res = await fetch(`./api/get_${type}_details.php?id=${id}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      
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

  function renderFacultyDetail(d) {
    const f = d.faculty || {};
      const projs = d.projects || [];
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
          <div></div>
          <div>
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

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }
})();


/* -------------------------
    ADMIN CALENDAR WIDGET
------------------------- */
(function() {
    const calendarEl = document.getElementById('calendar-app');
    if (!calendarEl) return;

    const monthYearEl = document.getElementById('current-month-year');
    const daysGridEl = document.getElementById('calendar-days');
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');
    
    // Comprehensive list of dummy events (Holidays, Meetings, Deadlines)
    const DUMMY_EVENTS = {
        // --- November 2025 ---
        '2025-11-01': [{ title: 'All Saints Day (Holiday)', type: 'holiday' }],
        '2025-11-20': [{ title: 'Funding Deadline A', type: 'deadline' }],
        '2025-11-25': [{ title: 'Faculty Meeting', type: 'meeting' }],
        '2025-11-30': [{ title: 'Bonifacio Day (Holiday)', type: 'holiday' }],
        
        // --- December 2025 ---
        '2025-12-10': [{ title: 'Project Review', type: 'meeting' }],
        '2025-12-24': [{ title: 'Christmas Eve (Special Holiday)', type: 'holiday' }],
        '2025-12-25': [{ title: 'Christmas Day (Holiday)', type: 'holiday' }],
        '2025-12-30': [{ title: 'Rizal Day (Holiday)', type: 'holiday' }],
        '2025-12-31': [{ title: 'New Year\'s Eve (Special Holiday)', type: 'holiday' }],
        
        // --- January 2026 ---
        '2026-01-01': [{ title: 'New Year\'s Day (Holiday)', type: 'holiday' }],
        '2026-01-20': [{ title: 'Q1 Budget Deadline', type: 'deadline' }],
        
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
    currentDate.setDate(1); // Set to the 1st of the current month

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


        for (let i = 0; i < firstDayOfMonth; i++) {
            const dayNum = daysInPrevMonth - firstDayOfMonth + i + 1;
            const cell = document.createElement('div');
            cell.classList.add('calendar-day', 'calendar-day--outside');
            cell.innerHTML = `<span class="calendar-day-number">${dayNum}</span>`;
            daysGridEl.appendChild(cell);
        }

        for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const cell = document.createElement('div');
            cell.classList.add('calendar-day');
            
            if (dateStr === todayStr) {
                cell.classList.add('calendar-day--today');
            }

            cell.innerHTML = `<span class="calendar-day-number">${i}</span>`;
            
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

        const totalCells = firstDayOfMonth + daysInMonth;
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

    // Attach event listeners
    prevBtn.addEventListener('click', () => changeMonth(-1));
    nextBtn.addEventListener('click', () => changeMonth(1));

    // Initial render
    renderCalendar();
})();

/* -------------------------
    RESEARCH DATE VALIDATION WIDGET
------------------------- */
(function() {
    // --- CREATE FORM (Static Inputs) ---
    const createStartDateEl = document.getElementById('research_startdate');
    const createEndDateEl = document.getElementById('research_enddate');

    // --- EDIT MODAL (Dynamic Inputs) ---
    const editStartDateEl = document.getElementById('m_start');
    const editEndDateEl = document.getElementById('m_end');

    // ----------------------------------------------------
    // Generic validation logic function
    // ----------------------------------------------------
    function enforceDateConstraint(startEl, endEl) {
        if (!startEl || !endEl) return;

        function validateAndSetMin() {
            const startDateValue = startEl.value;
            
            endEl.min = startDateValue;
            if (endEl.value && startDateValue && endEl.value < startDateValue) {
                // Clear value to force re-selection and prevent submission of invalid data
                endEl.value = ''; 
            }
        }
        
        startEl.addEventListener('change', validateAndSetMin);
        endEl.addEventListener('focus', validateAndSetMin, { once: true });
                if (startEl === createStartDateEl) {
            validateAndSetMin();
        }
    }
    
    // ----------------------------------------------------
    // Apply logic to both sets of inputs
    // ----------------------------------------------------
    enforceDateConstraint(createStartDateEl, createEndDateEl);
    enforceDateConstraint(editStartDateEl, editEndDateEl);

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

document.addEventListener('DOMContentLoaded', function() {
    // Desktop Collapse Button (inside the sidebar)
    const internalToggleButton = document.getElementById('sidebar-toggle-internal');
    // Mobile Overlay Button (external, visible only on small screens)
    const mobileToggleButton = document.getElementById('sidebar-toggle-mobile');
    
    const appWrapper = document.getElementById('app-wrapper');

    if (appWrapper) {
        
        // --- Initialization ---
        // On desktop load, ensure the sidebar is open by default
        if (window.innerWidth > 1024) {
             appWrapper.classList.remove('sidebar-closed'); 
        } else {
             appWrapper.classList.remove('sidebar-open');
        }


        // --- Desktop Collapse Logic ---
        if (internalToggleButton) {
            internalToggleButton.addEventListener('click', function() {
                // Only run desktop logic on desktop screen sizes
                if (window.innerWidth > 1024) {
                    appWrapper.classList.toggle('sidebar-closed');
                }
            });
        }
        
        // --- Mobile Overlay Logic ---
        if (mobileToggleButton) {
            mobileToggleButton.addEventListener('click', function() {
                // Only run mobile logic on mobile screen sizes
                if (window.innerWidth <= 1024) {
                    const isNowOpen = appWrapper.classList.toggle('sidebar-open');
                    
                    // Update button text/icon for mobile
                    if (isNowOpen) {
                        mobileToggleButton.innerHTML = '✕ Close';
                    } else {
                        mobileToggleButton.innerHTML = '☰ Menu';
                    }
                }
            });
        }
        
        // --- Close Sidebar on Mobile Link Click ---
        const sidebarLinks = appWrapper.querySelectorAll('.sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                // If the sidebar is open and we are on mobile, close it after clicking a link
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