/** Local progressive-enhancement for ordered schedules. No external libraries. */
(() => {
  'use strict';
  document.querySelectorAll('[data-schedule-editor]').forEach(editor => {
    const form = editor.querySelector('form');
    const company = editor.querySelector('[data-schedule-company]');
    const device = editor.querySelector('[data-schedule-device]');
    const addSelect = editor.querySelector('[data-schedule-add-select]');
    const add = editor.querySelector('[data-schedule-add]');
    const sequence = editor.querySelector('[data-schedule-sequence]');
    if (!form || !device || !addSelect || !add || !sequence) return;
    const tenantInput = form.querySelector('input[name="tenant_id"]');
    const tenant = () => company ? company.value : tenantInput?.value;
    const available = (option, targetTenant) => option.value !== '' && option.dataset.tenant === String(targetTenant);
    const refresh = () => {
      const target = tenant();
      [...device.options].forEach(option => { option.hidden = !available(option, target); option.disabled = option.hidden; });
      if (device.selectedOptions[0]?.disabled) device.selectedIndex = [...device.options].findIndex(o => !o.disabled);
      const orientation = device.selectedOptions[0]?.dataset.orientation;
      [...addSelect.options].forEach(option => {
        option.hidden = option.value !== '' && (!available(option, target) || (option.dataset.orientation !== 'any' && option.dataset.orientation !== orientation));
        option.disabled = option.hidden;
      });
      if (addSelect.selectedOptions[0]?.disabled) addSelect.value = '';
      add.disabled = ![...addSelect.options].some(o => o.value !== '' && !o.disabled);
      [...sequence.children].forEach(row => {
        const match = [...addSelect.options].find(o => o.value === row.dataset.listId && !o.disabled);
        row.classList.toggle('border-danger', !match);
        row.title = match ? '' : 'Lista incompatible con la pantalla o empresa elegida';
      });
    };
    function appendRow(id, name) {
      const row = document.createElement('li');
      row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
      row.dataset.listId = id;
      const hidden = document.createElement('input');hidden.type='hidden';hidden.name='playlists[]';hidden.value=id;
      const span = document.createElement('span');span.className='flex-grow-1';span.textContent=name;
      row.append(hidden,span);
      for(const [act,label,cls] of [['up','↑','btn-outline-secondary'],['down','↓','btn-outline-secondary'],['remove','×','btn-outline-danger']]) {
        const b=document.createElement('button');b.type='button';b.className='btn btn-sm '+cls;b.textContent=label;
        b.dataset[act==='remove'?'sequenceRemove':'sequenceMove']=act==='remove'?'':act;
        row.appendChild(b);
      }
      sequence.appendChild(row);
    }
    add.addEventListener('click', () => {
      const selected = addSelect.selectedOptions[0];
      if(!selected || !selected.value || selected.disabled) return;
      appendRow(selected.value,selected.textContent.trim());
    });
    sequence.addEventListener('click', event => {
      const button=event.target.closest('button');const row=button?.closest('li');if(!row)return;
      if(button.hasAttribute('data-sequence-remove'))row.remove();
      else if(button.dataset.sequenceMove==='up' && row.previousElementSibling)sequence.insertBefore(row,row.previousElementSibling);
      else if(button.dataset.sequenceMove==='down' && row.nextElementSibling)sequence.insertBefore(row.nextElementSibling,row);
    });
    company?.addEventListener('change',() => { sequence.replaceChildren(); refresh(); });
    device.addEventListener('change',refresh);
    form.addEventListener('submit',event => {
      if(!sequence.children.length){event.preventDefault();window.alert('Añade al menos una lista a la programación.');return;}
      if(sequence.querySelector('.border-danger')){event.preventDefault();window.alert('Hay listas incompatibles con la pantalla seleccionada.');}
    });
    refresh();
  });
})();
