(function(){
  // Modal open/close
  const overlay = document.getElementById('modal-overlay');
  const modalTitle = document.getElementById('modal-title');
  const modalForm  = document.getElementById('modal-form');
  const modalClose = document.getElementById('modal-x');

  function openModal(title){
    modalTitle.textContent = title || 'Edit';
    document.body.classList.add('modal-open');
    overlay.style.display = 'flex';
  }
  function closeModal(){
    document.body.classList.remove('modal-open');
    overlay.style.display = 'none';
    modalForm.reset();
  }
  if (modalClose){ modalClose.addEventListener('click', closeModal); }
  overlay?.addEventListener('click', (e)=>{ if(e.target===overlay) closeModal(); });

  // Bind all [data-modal="edit"] buttons
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-modal="edit"]');
    if (!btn) return;
    // Fill modal fields from data attrs (name must match input name)
    for (const {name} of modalForm.elements){
      if (!name) continue;
      const val = btn.dataset[name?.toLowerCase()];
      if (val !== undefined) {
        const el = modalForm.elements.namedItem(name);
        if (el) el.value = val;
      }
    }
    openModal(btn.dataset.title || 'Edit');
  });

  // Inline edit: any .inline-save form posts via fetch then reloads
  async function postForm(form){
    const fd = new FormData(form);
    const res = await fetch(form.action || location.href, { method:'POST', body: fd, credentials:'same-origin' });
    if (!res.ok) throw new Error('Request failed');
    location.reload();
  }
  document.addEventListener('submit', (e)=>{
    const form = e.target;
    if (form.classList.contains('inline-save')) {
      e.preventDefault();
      postForm(form).catch(()=>alert('Save failed. Please check inputs.'));
    }
  });

  // Modal submit via fetch
  modalForm?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try { await postForm(modalForm); } catch { alert('Save failed.'); }
  });
})();
