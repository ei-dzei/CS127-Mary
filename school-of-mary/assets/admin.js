(function(){
  const $$ = (sel,root=document)=>root.querySelector(sel);

  // ---------- Spinner ----------
  const spinner = $$('#spinner-overlay');
  function showSpinner(){ spinner.style.display='flex'; }
  function hideSpinner(){ spinner.style.display='none'; }

  // ---------- Toasts ----------
  const toastRoot = $$('#toast-root');
  function toast({title='Notice', msg='', type='success', timeout=2800}={}){
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `
      <div>
        <div class="title">${escapeHtml(title)}</div>
        <div class="msg">${escapeHtml(msg)}</div>
      </div>
      <button class="x" aria-label="Dismiss">×</button>
    `;
    toastRoot.appendChild(el);
    const kill = ()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(), 180); };
    el.querySelector('.x').addEventListener('click', kill);
    setTimeout(kill, timeout);
  }
  function escapeHtml(s){ return (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  // Expose for manual calls
  window.AdminNotify = { toast, showSpinner, hideSpinner };

  // ---------- Field error helpers ----------
  function clearFieldErrors(form){
    form.querySelectorAll('.input.invalid').forEach(el=>{
      el.classList.remove('invalid');
      el.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('.error-text').forEach(el=>el.remove());
  }
  function showFieldError(form, fieldName, message){
    const el = form.elements.namedItem(fieldName);
    if (!el) return false;
    el.classList.add('invalid');
    el.setAttribute('aria-invalid','true');
    // Find its nearest .field container 
    let wrap = el.closest('.field') || el.parentElement;
    const msg = document.createElement('div');
    msg.className = 'error-text';
    msg.textContent = message || 'Invalid value';
    wrap.appendChild(msg);
    // Focus it for accessibility
    try{ el.focus(); }catch{}
    return true;
  }

  // ---------- Modal open/close ----------
  const overlay = $$('#modal-overlay');
  const modalTitle = $$('#modal-title');
  const modalForm  = $$('#modal-form');
  const modalClose = $$('#modal-x');

  function openModal(title){
    modalTitle.textContent = title || 'Edit';
    document.body.classList.add('modal-open');
    overlay.style.display = 'flex';
  }
  function closeModal(){
    document.body.classList.remove('modal-open');
    overlay.style.display = 'none';
    clearFieldErrors(modalForm);
    modalForm?.reset();
  }
  modalClose?.addEventListener('click', closeModal);
  overlay?.addEventListener('click', (e)=>{ if(e.target===overlay) closeModal(); });

  // Fill modal from data-*
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-modal="edit"]');
    if (!btn) return;
    clearFieldErrors(modalForm);
    for (const {name} of modalForm.elements){
      if (!name) continue;
      const key = name.toLowerCase();
      if (btn.dataset.hasOwnProperty(key)) {
        const el = modalForm.elements.namedItem(name);
        if (el) el.value = btn.dataset[key];
      }
    }
    openModal(btn.dataset.title || 'Edit');
  });

  // Unified POST helper (adds field error handling)
  async function postForm(form){
    clearFieldErrors(form);
    showSpinner();
    try{
      const fd = new FormData(form);
      // Hint server we want JSON errors (so guardFail returns JSON)
      const res = await fetch(form.action || location.href, {
        method:'POST', body: fd, credentials:'same-origin',
        headers:{ 'X-Requested-With':'fetch' }
      });

      // If not OK, try to parse JSON for {field,message}
      if (!res.ok){
        let text = await res.text();
        try {
          const data = JSON.parse(text);
          if (data?.field){
            const placed = showFieldError(form, data.field, data.message || 'Invalid value');
            if (!placed && form!==modalForm) showFieldError(modalForm, data.field, data.message);
            toast({title:'Validation error', msg:data.message||'Please check your inputs.', type:'error', timeout:5000});
            throw new Error(data.message||'Validation error');
          } else {
            toast({title:'Save failed', msg:data?.message || text.slice(0,200), type:'error', timeout:5000});
            throw new Error(data?.message || text);
          }
        } catch{
          // Not JSON
          toast({title:'Save failed', msg:text.slice(0,200), type:'error', timeout:5000});
          throw new Error(text);
        }
      }

      toast({title:'Saved', msg:'Changes applied successfully.'});
      setTimeout(()=>location.reload(), 350);
    }catch(err){
      console.error(err);
      throw err;
    }finally{
      hideSpinner();
    }
  }

  // Inline save via fetch
  document.addEventListener('submit', (e)=>{
    const form = e.target;
    if (form.classList.contains('inline-save')) {
      e.preventDefault();
      postForm(form).catch(()=>{ /* toast already shown */ });
    }
  });

  // Modal submit via fetch
  modalForm?.addEventListener('submit', (e)=>{
    e.preventDefault();
    postForm(modalForm).catch(()=>{});
  });
})();
