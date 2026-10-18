(function(){
 'use strict';
 var dirtyForms=[];
 function dirty(){return dirtyForms.some(function(f){return f.dataset.dirty==='1';});}
 document.querySelectorAll('[data-playlist-grid]').forEach(function(form){
  dirtyForms.push(form);
  var fields=document.querySelectorAll('[form="'+form.id+'"][data-grid-field]');
  var edit=form.querySelector('[data-grid-edit]'),save=form.querySelector('[data-grid-save]'),cancel=form.querySelector('[data-grid-cancel]');
  function mode(on){fields.forEach(function(f){f.disabled=!on;});edit.hidden=on;save.hidden=!on;cancel.hidden=!on;form.closest('details').classList.toggle('is-editing',on);}
  edit.addEventListener('click',function(){mode(true);if(fields.length)fields[0].focus();});
  fields.forEach(function(f){f.addEventListener('input',function(){form.dataset.dirty='1';});f.addEventListener('change',function(){form.dataset.dirty='1';});});
  cancel.addEventListener('click',function(){if(form.dataset.dirty==='1'&&!confirm('¿Descartar los cambios sin guardar de esta lista?'))return;form.reset();form.dataset.dirty='0';mode(false);});
  form.addEventListener('submit',function(){
   // One JSON input avoids PHP max_input_vars truncating a large grid.
   var rows={};fields.forEach(function(f){var m=/^rows\[(\d+)\]\[(\w+)\]$/.exec(f.name);if(m){rows[m[1]]=rows[m[1]]||{};rows[m[1]][m[2]]=f.value;}});
   var input=document.createElement('input');input.type='hidden';input.name='rows_json';input.value=JSON.stringify(rows);form.appendChild(input);
   fields.forEach(function(f){f.disabled=true;});dirtyForms.forEach(function(f){f.dataset.dirty='0';});
  });
 });
 document.addEventListener('submit',function(event){
  if(event.target.matches('[data-playlist-grid]'))return;
  if(dirty()&&!confirm('Hay cambios sin guardar en las listas. ¿Descartarlos y continuar?'))event.preventDefault();
  else dirtyForms.forEach(function(f){f.dataset.dirty='0';});
 });
 window.addEventListener('beforeunload',function(e){if(dirty()){e.preventDefault();e.returnValue='Hay cambios sin guardar';}});
 function openFragment(){var id=window.location.hash.slice(1);if(/^playlist-[1-9][0-9]*$/.test(id)){var list=document.getElementById(id);if(list)list.open=true;}}
 openFragment();window.addEventListener('hashchange',openFragment);
})();
