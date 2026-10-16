(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){
 var table=document.querySelector('.ds-media-grid'), form=document.getElementById('ds-bulk-form');
 if(!table||!form)return;
 var boxes=Array.prototype.slice.call(table.querySelectorAll('[data-bulk-id]'));
 var all=table.querySelector('[data-bulk-all]');
 var open=document.querySelector('[data-bulk-open]'),clear=document.querySelector('[data-bulk-clear]');
 var num=document.querySelector('[data-bulk-selected]'),modalNum=document.querySelector('[data-bulk-modal-selected]');
 function selected(){return boxes.filter(function(el){return el.checked;});}
 function update(){var count=selected().length;
  if(num)num.textContent=count;if(modalNum)modalNum.textContent=count;
  if(open)open.disabled=count===0;
  if(all){all.checked=boxes.length>0&&count===boxes.length;all.indeterminate=count>0&&count<boxes.length;}
  boxes.forEach(function(b){var row=b.closest('tr');if(row)row.classList.toggle('ds-selected-row',b.checked);});
 }
 boxes.forEach(function(b){b.addEventListener('change',update);});
 if(all)all.addEventListener('change',function(){boxes.forEach(function(b,i){b.checked=all.checked&&i<100;});if(all.checked&&boxes.length>100)alert('Hay más de 100 medios visibles. Se seleccionaron los primeros 100; utiliza filtros para trabajar los demás.');update();});
 if(clear)clear.addEventListener('click',function(){boxes.forEach(function(b){b.checked=false;});update();});
 var action=document.getElementById('ds-bulk-action');
 var destination=form.querySelector('[data-bulk-destination]'),classify=form.querySelector('[data-bulk-classify]');
 function show(){var target=action&&action.value==='bulk_classify';
  if(destination){destination.hidden=!!target;destination.querySelectorAll('select,input').forEach(function(el){el.disabled=!!target;});}
  if(classify){classify.hidden=!target;classify.querySelectorAll('select,input').forEach(function(el){el.disabled=!target;});}
 }
 if(action){action.addEventListener('change',show);show();}
 form.addEventListener('submit',function(e){var chosen=selected(),operation=action?action.value:'';
  if(chosen.length===0){e.preventDefault();alert('Selecciona al menos un medio.');return;}
  if(chosen.length>100){e.preventDefault();alert('Selecciona como máximo 100 medios por lote.');return;}
  var desc=operation==='bulk_move'?'MOVER (cambia propietario y archivo)':operation==='bulk_copy'?'DUPLICAR (preserva originales)':'RECLASIFICAR';
  if(!window.confirm('Confirmar '+desc+' para '+chosen.length+' medio(s). Los cambios se aplican a toda la selección.'))e.preventDefault();
 });update();
});
})();
