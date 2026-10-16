/** Keep playlist choices in sync with company and orientation for create/edit screens. */
(() => {
  'use strict';
  const allowed=(option,tenant,orientation)=>!option.value || (option.dataset.tenant===String(tenant) && (option.dataset.orientation==='any'||option.dataset.orientation===orientation));
  document.querySelectorAll('#createModal, [id^="dev"]').forEach(modal=>{
    const form=modal.querySelector('form');if(!form)return;
    const company=form.querySelector('select[name="tenant_id"]')||form.querySelector('input[name="tenant_id"]');
    const orientation=form.querySelector('select[name="orientation"]');
    const playlist=form.querySelector('select[name="active_playlist_id"]');
    if(!company||!orientation||!playlist)return;
    const refresh=()=>{
      for(const option of playlist.options){option.hidden=!!option.value&&!allowed(option,company.value,orientation.value);option.disabled=option.hidden;}
      if(playlist.selectedOptions[0]?.disabled)playlist.value='';
    };
    company.addEventListener('change',refresh);
    orientation.addEventListener('change',refresh);
    refresh();
  });
})();
