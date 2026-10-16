/* 2Cam Digital Signage 0.4.15 — sequential media import wizard.
 * Selection and classification happen entirely in the browser. Nothing reaches PHP
 * until final confirmation. Each upload is one transaction (per-file rollback).
 */
(function(){
'use strict';
var screens={landscape:{name:'Pantalla horizontal',w:1920,h:1080},portrait:{name:'Pantalla vertical',w:1080,h:1920}};
var zones={fullscreen:{name:'Pantalla completa',w:1,h:1},bottom:{name:'Barra inferior',w:1,h:.20},side:{name:'Barra lateral derecha',w:.40,h:1},half_horizontal:{name:'Media pantalla horizontal',w:1,h:.5},half_vertical:{name:'Media pantalla vertical',w:.5,h:1}};
var allowed=['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','video/quicktime'];
var sizeLimit=40*1048576;
function target(o,z){var screen=screens[o]||screens.landscape,zone=zones[z]||zones.fullscreen;return {w:Math.round(screen.w*zone.w),h:Math.round(screen.h*zone.h),screen:screen,zone:zone};}
function ratioMatches(w,h,t){return w>0&&h>0&&Math.abs((w/h)/(t.w/t.h)-1)<=.04;}
function human(n){return (n/1048576).toFixed(2)+' MB';}
function by(root,k){return root.querySelector('[data-import-'+k+']');}
function el(tag,cls,text){var e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;}
function detectType(f){if(allowed.indexOf(f.type)!==-1)return f.type;var ext=f.name.split('.').pop().toLowerCase();return ({jpg:'image/jpeg',jpeg:'image/jpeg',png:'image/png',webp:'image/webp',gif:'image/gif',mp4:'video/mp4',webm:'video/webm',mov:'video/quicktime'})[ext]||'';}
function inspect(f){return new Promise(function(resolve){
 var kind=detectType(f),isImage=kind.indexOf('image/')===0,isVideo=kind.indexOf('video/')===0;
 if(!isImage&&!isVideo){resolve({w:0,h:0,error:'Formato no admitido'});return;}
 var url=URL.createObjectURL(f),object=isImage?new Image():document.createElement('video'),done=false;
 var timeout=setTimeout(function(){finish(0,0,'No se pudo leer la resolución del medio');},8500);
 function finish(w,h,error){if(done)return;done=true;clearTimeout(timeout);URL.revokeObjectURL(url);
  if(!isImage){object.removeAttribute('src');try{object.load();}catch(e){}}
  resolve({w:w||0,h:h||0,error:error||''});}
 object.onerror=function(){finish(0,0,'El navegador no pudo abrir este archivo');};
 if(isImage){object.onload=function(){finish(object.naturalWidth,object.naturalHeight,'');};object.src=url;}
 else{object.preload='metadata';object.muted=true;object.onloadedmetadata=function(){finish(object.videoWidth,object.videoHeight,'');};object.src=url;try{object.load();}catch(e){finish(0,0,e.message||'Video no compatible');}}
 });}
function guess(item){if(!item.w||!item.h)return;
 var winner=null,diff=99;
 Object.keys(screens).forEach(function(o){Object.keys(zones).forEach(function(z){var t=target(o,z),d=Math.abs((item.w/item.h)/(t.w/t.h)-1);if(d<diff){diff=d;winner={orientation:o,layout:z};}});});
 if(winner&&diff<=.04&&!item.dirty){item.orientation=winner.orientation;item.layout=winner.layout;item.suggested=true;}
}
function validate(item){
 if(item.skip||item.status==='uploaded')return {ok:true,message:item.skip?'Omitido':'Cargado'};
 if(!item.file.size||item.file.size>sizeLimit)return {ok:false,message:'El archivo supera 40 MB o está vacío. No se puede cargar.'};
 if(allowed.indexOf(item.mime)===-1)return {ok:false,message:'Tipo de archivo no admitido.'};
 if(!item.name.trim()||new TextEncoder().encode(item.name.trim()).length>190)return {ok:false,message:'Indica un nombre de hasta 190 bytes.'};
 if(!item.company)return {ok:false,message:'Selecciona la empresa de destino.'};
 if(!screens[item.orientation]||!zones[item.layout])return {ok:false,message:'Selecciona pantalla y presentación.'};
 if(!item.inspected)return {ok:false,message:'Leyendo dimensiones. Espera un momento.'};
 if(item.mime.indexOf('image/')===0 && (!item.w||!item.h))return {ok:false,message:'No se pudieron comprobar las dimensiones de esta imagen.'};
 if(item.w&&item.h){var t=target(item.orientation,item.layout);if(!ratioMatches(item.w,item.h,t))return {ok:false,message:'Esta imagen mide '+item.w+' × '+item.h+' px y NO coincide con '+t.w+' × '+t.h+' px (tolerancia de proporción ±4%). Cambia la presentación o corrige el arte.'};}
 if(item.mime.indexOf('video/')===0&&!item.w)return {ok:true,warning:true,message:'Video: no se pudieron comprobar sus dimensiones en este navegador. Se importará bajo tu responsabilidad.'};
 return {ok:true,message:'Proporción correcta para esta zona.'};
}
function init(root){
 var fileInput=by(root,'files'),companyInput=root.querySelector('[data-import-company] [name="tenant_id"]');
 if(!fileInput||!companyInput)return;
 var queue=[],index=0,busy=false,previewUrl=null,stage='select';
 // Limit parallel metadata inspection to protect phones and lower-memory browsers.
 var inspectActive=0,inspectionWaiting=[];
 function scheduleInspection(file){return new Promise(function(resolve){inspectionWaiting.push({file:file,resolve:resolve});pumpInspection();});}
 function pumpInspection(){while(inspectActive<3&&inspectionWaiting.length){
   var task=inspectionWaiting.shift();inspectActive++;
   inspect(task.file).then(task.resolve,function(err){task.resolve({w:0,h:0,error:err.message||'No se pudieron inspeccionar las dimensiones'});})
     .finally(function(){inspectActive--;pumpInspection();});
 }}
 var modal=root.closest('.modal');
 function display(stageName){stage=stageName;['select','work','review','progress'].forEach(function(k){by(root,k).hidden=(k!==stageName);});}
 function cleanupPreview(){if(previewUrl){URL.revokeObjectURL(previewUrl);previewUrl=null;}}
 function selected(){return queue[index];}
 function populatePreview(item){cleanupPreview();var holder=by(root,'preview');holder.replaceChildren();
  previewUrl=URL.createObjectURL(item.file);
  if(item.mime.indexOf('image/')===0){var img=el('img');img.alt='Vista previa: '+item.name;img.src=previewUrl;holder.appendChild(img);}
  else if(item.mime.indexOf('video/')===0){var vid=el('video');vid.src=previewUrl;vid.controls=true;vid.preload='metadata';vid.muted=true;vid.playsInline=true;holder.appendChild(vid);}
  else{holder.appendChild(el('p','','Archivo sin vista previa'))}
 }
 function syncCurrent(){if(!queue.length)return;var item=selected();
  by(root,'name').value=item.name;companyInput.value=item.company;
  by(root,'counter').textContent='Medio '+(index+1)+' de '+queue.length;
  var ready=queue.filter(function(a){return !a.skip&&a.status!=='uploaded'&&validate(a).ok;}).length;
  by(root,'summary').textContent=ready+' preparados · '+queue.filter(function(a){return a.skip;}).length+' omitidos';
  by(root,'filename').textContent=item.file.name;
  by(root,'file-details').textContent=(item.w&&item.h?item.w+' × '+item.h+' px · ':'Dimensiones por comprobar · ')+human(item.file.size);
  populatePreview(item);updateFields();
 }
 function updateFields(){if(!queue.length)return;var item=selected();
  root.querySelectorAll('[data-import-orientation]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.importOrientation===item.orientation));});
  root.querySelectorAll('[data-import-layout]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.importLayout===item.layout));var d=b.querySelector('.ds-preview-mini-screen');if(d)d.setAttribute('data-orientation',item.orientation);});
  var g=by(root,'diagram');g.dataset.orientation=item.orientation;g.dataset.layout=item.layout;
  var t=target(item.orientation,item.layout);
  by(root,'geometry-title').textContent=t.screen.name+' · '+t.zone.name;
  by(root,'geometry-size').textContent='Arte recomendado: '+t.w+' × '+t.h+' px';
  var r=validate(item),text=by(root,'validation');
  text.className='ds-import-validation '+(r.ok?(r.warning?'is-warn':'is-ok'):'is-error');
  text.textContent=item.skip?'Archivo omitido. Pulsa «Reincorporar» para incluirlo.':r.message;
  by(root,'skip').textContent=item.skip?'Reincorporar archivo':'Omitir este archivo';
  by(root,'prev').disabled=index===0;
  by(root,'next').textContent=index===queue.length-1?'Revisar lote →':'Siguiente →';
 }
 function setCurrent(n){if(n<0||n>=queue.length)return;index=n;display('work');syncCurrent();}
 function addFiles(files){if(busy)return;var added=0;
  Array.from(files).forEach(function(file){
   if(queue.some(function(a){return a.file.name===file.name&&a.file.size===file.size&&a.file.lastModified===file.lastModified;}))return;
   var company=companyInput.value;
   var item={file:file,mime:detectType(file),company:company,name:file.name.slice(0,190),orientation:'landscape',layout:'fullscreen',w:0,h:0,inspected:false,dirty:false,skip:false,status:'pending',error:''};
   queue.push(item);added++;
   item.promise=scheduleInspection(file).then(function(info){item.w=info.w;item.h=info.h;item.inspected=true;item.inspectError=info.error;guess(item);
    if(stage==='work'&&selected()===item){by(root,'file-details').textContent=(item.w&&item.h?item.w+' × '+item.h+' px · ':'Dimensiones no disponibles · ')+human(item.file.size);updateFields();}
   });
  });
  fileInput.value='';
  if(!queue.length)return;
  if(stage==='select')setCurrent(0);else if(added)by(root,'summary').textContent=queue.length+' medios en la cola';
 }
 fileInput.addEventListener('change',function(){addFiles(fileInput.files);});
 by(root,'more').addEventListener('click',function(){fileInput.click();});
 by(root,'name').addEventListener('input',function(e){if(!selected())return;selected().name=e.target.value;updateFields();});
 companyInput.addEventListener('change',function(){if(selected())selected().company=companyInput.value;updateFields();});
 root.querySelectorAll('[data-import-orientation]').forEach(function(b){b.addEventListener('click',function(){if(!selected())return;selected().orientation=b.dataset.importOrientation;selected().dirty=true;updateFields();});});
 root.querySelectorAll('[data-import-layout]').forEach(function(b){b.addEventListener('click',function(){if(!selected())return;selected().layout=b.dataset.importLayout;selected().dirty=true;updateFields();});});
 by(root,'prev').addEventListener('click',function(){setCurrent(index-1);});
 by(root,'next').addEventListener('click',function(){if(index===queue.length-1){review();}else setCurrent(index+1);});
 by(root,'skip').addEventListener('click',function(){selected().skip=!selected().skip;updateFields();});
 by(root,'apply-company').addEventListener('click',function(){var v=selected().company;var n=0;queue.forEach(function(item){if(item.status==='pending'&&!item.skip){item.company=v;n++;}});by(root,'summary').textContent='Empresa aplicada a '+n+' medios pendientes';});
 by(root,'apply-layout').addEventListener('click',function(){var current=selected(),n=0;queue.forEach(function(item){if(item.status==='pending'&&!item.skip){item.orientation=current.orientation;item.layout=current.layout;item.dirty=true;n++;}});by(root,'summary').textContent='Presentación aplicada a '+n+' medios. Se comprobará la proporción de cada archivo.';updateFields();});
 by(root,'reset').addEventListener('click',function(){
  var completed=queue.filter(function(item){return item.status==='uploaded';}).length;
  var message=completed>0
    ?'¿Vaciar la lista de trabajo? Los '+completed+' medios ya cargados permanecerán guardados en la biblioteca.'
    :'¿Vaciar todos los medios seleccionados? Todavía no se ha subido ninguno.';
  if(!confirm(message))return;
  queue=[];index=0;cleanupPreview();display('select');
 });
 function review(){display('review');var list=by(root,'review-list');list.replaceChildren();
  var errors=0,valid=0,skipped=0;
  queue.forEach(function(item,i){var result=validate(item),row=el('div','ds-import-review-row '+(item.skip?'skipped':!result.ok?'invalid':item.status==='uploaded'?'uploaded':'valid'));
   var left=el('div','ds-import-review-info'),title=el('strong','',String(i+1)+'. '+item.name);
   var d=target(item.orientation,item.layout),desc=el('small','',(item.w&&item.h?item.w+' × '+item.h+' px · ':'')+d.screen.name+' · '+d.zone.name+' · '+d.w+' × '+d.h+' px');
   var note=el('small','',item.skip?'Omitido':item.status==='uploaded'?'Cargado correctamente':item.status==='error'?'Último intento falló: '+item.error:result.message);
   left.append(title,desc,note);row.appendChild(left);
   var edit=el('button','btn btn-sm btn-outline-primary','Editar');edit.type='button';edit.addEventListener('click',function(){setCurrent(i);});row.appendChild(edit);list.appendChild(row);
   if(item.skip)skipped++;else if(!result.ok)errors++;else if(item.status!=='uploaded')valid++;
  });
  var btn=by(root,'submit');btn.disabled=errors>0||valid===0||busy;
  btn.textContent=errors?'Corregir '+errors+' archivo(s) antes de subir':'Subir '+valid+' medio(s) revisado(s)';
  var txt=el('p',errors?'text-danger fw-bold':'text-success fw-bold',errors+' por corregir · '+valid+' pendientes válidos · '+skipped+' omitidos');
  list.prepend(txt);
 }
 by(root,'edit').addEventListener('click',function(){setCurrent(Math.min(index,queue.length-1));});
 by(root,'submit').addEventListener('click',async function(){if(busy)return;await Promise.all(queue.map(function(x){return x.promise;}));
  var bad=queue.findIndex(function(x){return !validate(x).ok;});if(bad>=0){setCurrent(bad);alert('Corrige este medio antes de iniciar las cargas.');return;}
  busy=true;display('progress');var targets=queue.filter(function(x){return !x.skip&&x.status!=='uploaded';});var total=targets.length,completed=0,ok=0,failed=0;
  var msg=by(root,'upload-status');msg.replaceChildren();var statusTitle=el('p','fw-bold','Subiendo archivos uno por uno...');msg.appendChild(statusTitle);
  for(var i=0;i<targets.length;i++){
   var item=targets[i],line=el('div','ds-import-upload-row','Subiendo '+(i+1)+' de '+total+': '+item.name);msg.appendChild(line);
   var f=new FormData();f.set('action','upload_wizard');f.set('_csrf',root.dataset.csrf);f.set('tenant_id',item.company);
   f.set('name',item.name.trim());f.set('orientation',item.orientation);f.set('layout_type',item.layout);f.set('media',item.file,item.file.name);
   try{
    var response=await fetch(root.dataset.uploadUrl,{method:'POST',body:f,credentials:'same-origin',redirect:'follow',headers:{'Accept':'application/json'}});
    var type=response.headers.get('content-type')||'',result=type.indexOf('application/json')>=0?await response.json():null;
    if(!result)throw new Error('La sesión expiró o el servidor devolvió una respuesta inesperada ('+response.status+').');
    if(!response.ok||!result.ok)throw new Error(result.message||'Falló el almacenamiento del medio.');
    item.status='uploaded';item.error='';ok++;line.className+=' is-ok';line.textContent='✓ '+item.name+' · cargado';
   }catch(e){item.status='error';item.error=e.message||'Error de conexión';failed++;line.className+=' is-error';line.textContent='✕ '+item.name+' · '+item.error;}
   completed++;var percent=Math.round(completed*100/Math.max(1,total));var bar=by(root,'bar');bar.style.width=percent+'%';bar.textContent=percent+'%';
  }
  busy=false;statusTitle.textContent='Terminó el lote: '+ok+' cargados, '+failed+' fallidos.';
  if(failed)msg.appendChild(el('p','text-warning','Los medios ya confirmados permanecen guardados. Puedes volver a revisar y reintentar solo los fallidos.'));
  else msg.appendChild(el('p','text-success','Todos los medios seleccionados se importaron correctamente.'));
  var close=by(root,'close');close.disabled=false;close.textContent=failed?'Revisar fallidos':'Cerrar y actualizar biblioteca';
  close.onclick=function(){if(failed){review();}else{window.location.reload();}};
 });
 if(modal){modal.addEventListener('hide.bs.modal',function(e){if(busy){e.preventDefault();alert('Espera a que terminen las cargas.');}});}
 window.addEventListener('beforeunload',function(e){if(busy){e.preventDefault();e.returnValue='Hay archivos todavía subiendo';}});
 display('select');
}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-media-import]').forEach(init);});
})();
