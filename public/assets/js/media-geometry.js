/* Browser-side preview and preflight. Server re-checks image proportions securely. */
(function(){
'use strict';
var screens={landscape:{w:1920,h:1080,name:'Pantalla horizontal'},portrait:{w:1080,h:1920,name:'Pantalla vertical'}};
var zones={fullscreen:{name:'Pantalla completa',w:1,h:1},bottom:{name:'Barra inferior',w:1,h:.20},side:{name:'Barra lateral derecha',w:.40,h:1},half_horizontal:{name:'Media pantalla horizontal',w:1,h:.5},half_vertical:{name:'Media pantalla vertical',w:.5,h:1}};
function target(ori,zone){var o=screens[ori]||screens.landscape,z=zones[zone]||zones.fullscreen;return {w:Math.round(o.w*z.w),h:Math.round(o.h*z.h),o:o,z:z};}
function match(w,h,target){return w>0&&h>0&&Math.abs((w/h)/(target.w/target.h)-1)<=.04;}
function checked(form,attr){return form.querySelector('input[name="'+attr+'"]:checked');}
function updateCards(form){
 var ori=checked(form,'orientation'),zone=checked(form,'layout_type'); if(!ori||!zone)return;
 var t=target(ori.value,zone.value), out=form.querySelector('[data-media-target]');
 if(out){out.querySelector('[data-target-description]').textContent=t.o.name+' + '+t.z.name;
 out.querySelector('[data-target-dims]').textContent=t.w+' × '+t.h+' px';
 out.querySelector('[data-target-proportion]').textContent='Proporción '+(t.w/t.h).toFixed(3)+':1 · proporciones equivalentes admitidas (±4%)';
 var screen=out.querySelector('.ds-preview-mini-screen');screen.setAttribute('data-orientation',ori.value);screen.setAttribute('data-layout',zone.value);}
 form.querySelectorAll('[data-orientation-pick]').forEach(function(b){b.setAttribute('aria-pressed',b.getAttribute('data-orientation-pick')===ori.value?'true':'false');});
 form.querySelectorAll('[data-layout-pick]').forEach(function(b){b.setAttribute('aria-pressed',b.getAttribute('data-layout-pick')===zone.value?'true':'false');var screen=b.querySelector('.ds-preview-mini-screen');if(screen)screen.setAttribute('data-orientation',ori.value);});
 var list=form.querySelector('[data-file-result]');if(list){list.textContent='Selecciona archivos para comprobar sus dimensiones.';list.className='ds-media-file-check';}
 var input=form.querySelector('input[type=file][name="files[]"]');if(input&&input.files&&input.files.length)verify(form);
}
function verify(form){var input=form.querySelector('input[type=file][name="files[]"]');if(!input||!input.files.length)return;
 var box=form.querySelector('[data-file-result]');if(!box)return;var ori=checked(form,'orientation'),zone=checked(form,'layout_type');var t=target(ori.value,zone.value);
 var files=Array.from(input.files);box.className='ds-media-file-check';box.textContent='Comprobando '+files.length+' archivo(s)...';
 var tasks=files.map(function(file){return new Promise(function(resolve){
  var media='';if(file.type.indexOf('image/')===0)media='image';else if(file.type.indexOf('video/')===0)media='video';
  if(!media){resolve({name:file.name,error:'Tipo de archivo no admitido'});return;}
  var url=URL.createObjectURL(file);
  var obj=media==='image'?new Image():document.createElement('video');var settled=false;
  function done(w,h,reason){if(settled)return;settled=true;URL.revokeObjectURL(url);
   if(!w||!h){resolve({name:file.name,warning:reason||'No se pudieron obtener las dimensiones; el servidor verificará lo disponible'});return;}
   resolve({name:file.name,w:w,h:h,error:!match(w,h,t)?'No coincide con '+t.w+' × '+t.h+' px (misma proporción requerida)':null});}
  obj.onerror=function(){done(0,0,'No se pueden leer las dimensiones de '+file.name);};
  if(media==='image'){obj.onload=function(){done(obj.naturalWidth,obj.naturalHeight);};obj.src=url;}
  else{obj.preload='metadata';obj.onloadedmetadata=function(){done(obj.videoWidth,obj.videoHeight);};obj.src=url;obj.load();}
  setTimeout(function(){done(0,0,'No se pudieron obtener metadatos dentro del tiempo de espera');},7000);
 });});
 Promise.all(tasks).then(function(results){
  var bad=results.some(function(r){return !!r.error;});box.className='ds-media-file-check '+(bad?'is-error':'is-ok');
  box.textContent=results.map(function(r){return (r.error?'✕ ':r.warning?'⚠ ':'✓ ')+r.name+(r.w?' · '+r.w+' × '+r.h+' px':'')+(r.error?' · '+r.error:r.warning?' · '+r.warning:' · Proporción correcta');}).join('\n');
  form.setAttribute('data-geometry-valid',bad?'false':'true');
 });
}
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('[data-media-upload-form]').forEach(function(form){
  form.querySelectorAll('[data-orientation-pick]').forEach(function(b){b.addEventListener('click',function(){var r=form.querySelector('input[name="orientation"][value="'+b.dataset.orientationPick+'"]');r.checked=true;updateCards(form);});});
  form.querySelectorAll('[data-layout-pick]').forEach(function(b){b.addEventListener('click',function(){var r=form.querySelector('input[name="layout_type"][value="'+b.dataset.layoutPick+'"]');r.checked=true;updateCards(form);});});
  var input=form.querySelector('input[type=file][name="files[]"]');if(input)input.addEventListener('change',function(){verify(form);});
  form.addEventListener('submit',function(e){if(form.getAttribute('data-geometry-valid')==='false'){e.preventDefault();alert('Algún archivo tiene proporciones incorrectas para la zona seleccionada. Corrígelo antes de cargar el lote.');}});
  updateCards(form);
 });
});
})();
