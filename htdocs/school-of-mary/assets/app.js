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

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-read-more]');
    if (!btn) return;

    const type = btn.getAttribute('data-type'); 
    const id   = btn.getAttribute('data-id');
    const title= btn.getAttribute('data-title') || 'Details';

    if (!type || !id) return;

    openOverlay(title);

    try {
      const endpoint =
        type === 'faculty'
          ? '/public/api/faculty_show.php?id=' + encodeURIComponent(id)
          : '/public/api/research_show.php?id=' + encodeURIComponent(id);


      const res = await fetch(endpoint, { headers: { 'Accept': 'application/json' }});
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      if (type === 'faculty') {
        bodyEl.innerHTML = renderFacultyDetail(data);
      } else {
        bodyEl.innerHTML = renderResearchDetail(data);
      }
    } catch (err) {
      bodyEl.innerHTML = `<p style="color:#b00020">Failed to load details. ${String(err)}</p>`;
    }
  });

  function renderFacultyDetail(d) {
    const dept = d.dept_name
      ? `<p class="kv"><b>Department:</b> ${escapeHtml(d.dept_name)}${d.dept_classification ? ` <span style="opacity:.65">(${escapeHtml(d.dept_classification)})</span>` : ''}</p>`
      : '';
    const email = d.email ? `<p class="kv"><b>Email:</b> <a href="mailto:${escapeHtml(d.email)}">${escapeHtml(d.email)}</a></p>` : '';

    const items = (d.assignments || []).map(p => {
      const funders = (p.funding || [])
        .map(f => `${escapeHtml(f.AGENCY_NAME)} (₱${escapeHtml(f.FUNDING_AMOUNT || '0.00')})`)
        .join(', ');
      return `
        <li>
          <a href="/public/research.php?id=${escapeHtml(p.RESEARCH_ID)}">${escapeHtml(p.RESEARCH_TITLE)}</a>
          <span style="opacity:.75"> — ${escapeHtml(p.RESEARCH_STATUS)}</span>
          ${funders ? `<br><span style="font-size:13px;opacity:.8">Funded by: ${funders}</span>` : ''}
        </li>
      `;
    }).join('');

    return `
      <div class="detail-grid">
        <div></div>
        <div>
          <h4>${escapeHtml(d.full_name || '')}</h4>
          ${dept}${email}
          ${items ? `<div class="section-title">Research & Funding</div><ul class="list">${items}</ul>` : '<p class="kv">No research assignments yet.</p>'}
        </div>
      </div>
    `;
  }


  function renderResearchDetail(d) {
    const badge = d.status ? `<span class="badge">${escapeHtml(d.status)}</span>` : '';

    const people = (d.people || []).map(p => `
      <span class="badge">
        ${escapeHtml(p.name)}${p.role_name ? ` · ${escapeHtml(p.role_name)}` : ''}${p.rank_name ? ` · ${escapeHtml(p.rank_name)}` : ''}
      </span>
    `).join('');

    const funds = (d.funding || []).map(f => `
      <li>
        ${escapeHtml(f.AGENCY_NAME)}${f.AGENCY_TYPE ? ` <span style="opacity:.7">(${escapeHtml(f.AGENCY_TYPE)})</span>` : ''}
        — ₱${escapeHtml(f.FUNDING_AMOUNT || '0.00')}
        ${f.DATE_FUNDED ? ` <span style="opacity:.65">(${escapeHtml(f.DATE_FUNDED)})</span>` : ''}
      </li>
    `).join('');

    return `
      <div class="detail-grid">
        <div></div>
        <div>
          <h4>${escapeHtml(d.title || '')}</h4>
          ${badge ? `<p class="kv"><b>Status:</b> ${badge}</p>` : ''}
          ${d.start_date ? `<p class="kv"><b>Start:</b> ${escapeHtml(d.start_date)}</p>` : ''}
          ${d.end_date ? `<p class="kv"><b>End:</b> ${escapeHtml(d.end_date)}</p>` : ''}

          ${people ? `<div class="section-title">Assigned Faculty</div><div class="badges">${people}</div>` : ''}
          ${funds ? `<div class="section-title">Funding</div><ul class="list">${funds}</ul>` : ''}
          <p class="kv"><b>Total Funding:</b> ₱${escapeHtml(d.total_funding || '0.00')}</p>
        </div>
      </div>
    `;
  }

  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
})();

