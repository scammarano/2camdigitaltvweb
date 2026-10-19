(function(){
 'use strict';
 var dirtyForms=[];
 function dirty(){return dirtyForms.some(function(f){return f.dataset.dirty==='1';});}
 document.querySelectorAll('[data-playlist-grid]').forEach(function(form){
  dirtyForms.push(form);
  var list=form.closest('details'),fields=list.querySelectorAll('[data-grid-field]'),checks=list.querySelectorAll('[data-row-select]');
  var edit=form.querySelector('[data-grid-edit]'),save=form.querySelector('[data-grid-save]'),cancel=form.querySelector('[data-grid-cancel]');
  var bulk=list.querySelector('[data-playlist-bulk]'),panel=list.querySelector('[data-rows-values]'),all=list.querySelector('[data-rows-all]');
  var selectedOnly=false,editing=false;
  function selected(){return Array.prototype.filter.call(checks,function(c){return c.checked;}).map(function(c){return c.value;});}
  function mode(on,subset){editing=on;selectedOnly=!!subset;var ids=selected();fields.forEach(function(f){f.disabled=!on||(selectedOnly&&ids.indexOf(f.closest('tr').dataset.playlistRow)<0);});edit.hidden=on;save.hidden=!on;cancel.hidden=!on;panel.hidden=!on||!selectedOnly;list.classList.toggle('is-editing',on);}
  function selection(){var ids=selected();bulk.querySelector('[data-row-count]').textContent=ids.length;bulk.querySelector('[name="selection_json"]').value=JSON.stringify(ids);bulk.querySelectorAll('[data-rows-action],[data-rows-edit]').forEach(function(b){b.disabled=!ids.length;});if(all){all.checked=checks.length>0&&ids.length===checks.length;all.indeterminate=ids.length>0&&ids.length<checks.length;}if(editing&&selectedOnly)mode(true,true);}
  checks.forEach(function(c){c.addEventListener('change',selection);});if(all)all.addEventListener('change',function(){checks.forEach(function(c){c.checked=all.checked;});selection();});
  edit.addEventListener('click',function(){mode(true,false);if(fields.length)fields[0].focus();});
  bulk.querySelector('[data-rows-edit]').addEventListener('click',function(){mode(true,true);var first=Array.prototype.find.call(fields,function(f){return !f.disabled;});if(first)first.focus();});
  var msMode=panel.querySelector('[data-bulk-ms-mode]'),msValue=panel.querySelector('[data-bulk-value="transition_ms"]');
  msMode.addEventListener('change',function(){msValue.disabled=msMode.value!=='set';});
  panel.querySelector('[data-rows-apply]').addEventListener('click',function(){
   var ids=selected(),values={};if(!ids.length)return;
   var inputs=panel.querySelectorAll('[data-bulk-value]');
   for(var i=0;i<inputs.length;i++){var input=inputs[i],key=input.dataset.bulkValue;if(key==='transition_ms')continue;if(!input.reportValidity())return;if(input.value!==''&&input.value!=='keep')values[key]=input.value;}
   if(msMode.value==='inherit')values.transition_ms='';
   if(msMode.value==='set'){if(msValue.value===''||!msValue.reportValidity()){msValue.focus();return;}values.transition_ms=msValue.value;}
   fields.forEach(function(f){var id=f.closest('tr').dataset.playlistRow;if(ids.indexOf(id)<0)return;var m=/\[([a-z_]+)\]$/.exec(f.name);if(m&&Object.prototype.hasOwnProperty.call(values,m[1])){f.value=values[m[1]];form.dataset.dirty='1';}});
  });
  fields.forEach(function(f){f.addEventListener('input',function(){form.dataset.dirty='1';});f.addEventListener('change',function(){form.dataset.dirty='1';});});
  cancel.addEventListener('click',function(){if(form.dataset.dirty==='1'&&!confirm('¿Descartar los cambios sin guardar de esta lista?'))return;form.reset();form.dataset.dirty='0';mode(false,false);});
  form.addEventListener('submit',function(event){
   if(dirtyForms.some(function(f){return f!==form&&f.dataset.dirty==='1';})&&!confirm('Hay cambios sin guardar en otras listas. ¿Descartarlos y guardar solamente esta lista?')){event.preventDefault();return;}
   var rows={};fields.forEach(function(f){var m=/^rows\[(\d+)\]\[(\w+)\]$/.exec(f.name);if(m){rows[m[1]]=rows[m[1]]||{};rows[m[1]][m[2]]=f.value;}});
   var input=document.createElement('input');input.type='hidden';input.name='rows_json';input.value=JSON.stringify(rows);form.appendChild(input);
   fields.forEach(function(f){f.disabled=true;});dirtyForms.forEach(function(f){f.dataset.dirty='0';});
  });
  var bulkAction='';
  bulk.querySelectorAll('[data-rows-action]').forEach(function(b){b.addEventListener('click',function(){bulkAction=b.value;});});
  bulk.addEventListener('submit',function(e){selection();if(!selected().length){e.preventDefault();return;}if(bulkAction==='bulk_delete'&&!confirm('¿Eliminar los '+selected().length+' renglones seleccionados? Los medios originales se conservan.'))e.preventDefault();});
  var choice=list.querySelector('[data-playlist-add] [name="choice"]');
  if(choice)choice.addEventListener('change',function(){if(choice.value==='separator:0'){var add=choice.form;add.querySelector('[name="duration"]').value='0.1';add.querySelector('[name="transition_effect"]').value='cut';add.querySelector('[name="transition_ms"]').value='0';}});
  function videos(){if(list.open)list.querySelectorAll('[data-row-video]').forEach(function(v){if(!v.getAttribute('src')){v.src=v.dataset.rowVideo;v.preload='metadata';v.load();}});}
  list.addEventListener('toggle',videos);videos();selection();
 });
 document.querySelectorAll('details.ds-playlist').forEach(function(list){
  if(list.querySelector('[data-playlist-grid]'))return;
  function videos(){if(list.open)list.querySelectorAll('[data-row-video]').forEach(function(v){if(!v.getAttribute('src')){v.src=v.dataset.rowVideo;v.preload='metadata';v.load();}});}
  list.addEventListener('toggle',videos);videos();
 });
 document.addEventListener('submit',function(event){
  if(event.defaultPrevented||event.target.matches('[data-playlist-grid]'))return;
  if(dirty()&&!confirm('Hay cambios sin guardar en las listas. ¿Descartarlos y continuar?'))event.preventDefault();
  else dirtyForms.forEach(function(f){f.dataset.dirty='0';});
 });
 window.addEventListener('beforeunload',function(e){if(dirty()){e.preventDefault();e.returnValue='Hay cambios sin guardar';}});
 function openFragment(){var id=window.location.hash.slice(1);if(/^playlist-[1-9][0-9]*$/.test(id)){var list=document.getElementById(id);if(list)list.open=true;}}
 openFragment();window.addEventListener('hashchange',openFragment);
})();
