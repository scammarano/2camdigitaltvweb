/* Digital Signage browser player v0.3.7 — smooth transitions, prefetch and bounded memory cache. */
(function(){
 'use strict';
 const setup=document.getElementById('setup'),stage=document.getElementById('stage'),area=document.getElementById('displayArea'),display=document.getElementById('display'),status=document.getElementById('status'),state=document.getElementById('playerState');
 const deck=new DSSlides.SlideDeck(area);
 let token='',items=[],index=0,timer=null,refreshing=false,displaySerial=0,sessionGen=0,orientation='landscape',source='';
 let serviceSuspended=false;
 function serviceApply(state){serviceSuspended=!!state?.suspended;if(serviceSuspended){stopClock();++displaySerial;deck.clear();stateLabel();}}
 function stateLabel(){state.textContent='Servicio suspendido · Contacta al administrador';}
 const cache=new Map();let cachedBytes=0;const CACHE_MAX=96*1024*1024,ENTRY_MAX=35*1024*1024,COUNT_MAX=30;
 const inFlight=new Map();
 const keyFor=item=>String(item.sha256||'')+':'+String(item.media_id||'')+':'+String(item.url||'');
 const capCache=()=>{while((cachedBytes>CACHE_MAX||cache.size>COUNT_MAX)&&cache.size){const first=cache.keys().next().value;cachedBytes-=cache.get(first).size;cache.delete(first);}};
 function clearCache(){cache.clear();inFlight.clear();cachedBytes=0;}
 function stopClock(){if(timer!==null){clearTimeout(timer);timer=null;}}
 function put(item,blob){if(blob.size>ENTRY_MAX)return;const k=keyFor(item);if(cache.has(k))cachedBytes-=cache.get(k).size;cache.delete(k);cache.set(k,blob);cachedBytes+=blob.size;capCache();}
 function getCached(item){const k=keyFor(item);if(!cache.has(k))return null;const blob=cache.get(k);cache.delete(k);cache.set(k,blob);return blob;}
 async function mediaBlob(item){
   const hit=getCached(item);if(hit)return hit;
   const k=keyFor(item);if(inFlight.has(k))return inFlight.get(k);
   const generation=sessionGen;
   const task=(async()=>{
     const r=await fetch(item.url,{headers:{Authorization:'Bearer '+token},cache:'no-store'});
     if(!r.ok)throw Error('Descarga HTTP '+r.status);
     const b=await r.blob();if(generation!==sessionGen)throw Error('Sesión cambiada durante la descarga');if(!b.size)throw Error('Archivo vacío');
     // The server manifest includes hashes for change detection. Hash verification is an Android download feature still pending.
     put(item,b);return b;
   })();inFlight.set(k,task);
   try{return await task;}finally{if(inFlight.get(k)===task)inFlight.delete(k);}
 }
 function prefetchNext(){if(items.length<2||!token)return;const next=items[(index+1)%items.length];if(!next||!(next.mime||'').startsWith('image/'))return;if(next.size && Number(next.size)>ENTRY_MAX)return;mediaBlob(next).catch(()=>{});}
 function waitVideo(el){return new Promise((resolve,reject)=>{let done=false;const close=(err)=>{if(done)return;done=true;clearTimeout(t);el.removeEventListener('loadeddata',yes);el.removeEventListener('error',no);err?reject(err):resolve();};const yes=()=>close(),no=()=>close(new Error('Video no compatible'));el.addEventListener('loadeddata',yes);el.addEventListener('error',no);const t=setTimeout(()=>close(new Error('Carga del video agotada')),12000);el.load();});}
 async function api(op,method='GET',body){const headers={...(token?{Authorization:'Bearer '+token}:{}),...(body?{'Content-Type':'application/json'}:{})};const r=await fetch('api-device.php?op='+encodeURIComponent(op),{method,headers,body:body?JSON.stringify(body):undefined,cache:'no-store'});let d;try{d=await r.json();}catch(e){throw Error('Respuesta inválida del servidor; revisa los logs.');}if(!r.ok)throw Error(d.error||'Error de conexión');return d;}
 function region(item){const allowed=['fullscreen','bottom','side','half_horizontal','half_vertical'];const v=allowed.includes(item.layout)?item.layout:'fullscreen';display.className='ds-display-frame ds-layout-'+v+(orientation==='portrait'?' ds-orientation-portrait':'');}
 async function show(){
   stopClock();if(serviceSuspended){stateLabel();return;}const serial=++displaySerial;
   if(!token)return;
   if(items.length===0){deck.clear();state.textContent='Pantalla sin medios. Asigna una lista en el portal.';timer=setTimeout(refresh,30000);return;}
   const item=items[index%items.length];if(!item||!item.url||!item.mime){state.textContent='Elemento inválido en la lista';timer=setTimeout(next,2500);return;}
   const video=item.mime.startsWith('video/');let objectUrl=null;
   try{
     const blob=await mediaBlob(item);if(serial!==displaySerial)return;
     objectUrl=URL.createObjectURL(blob);
     const el=document.createElement(video?'video':'img');el.className='ds-player-media';el.alt=item.name||'Medio';
     if(video){el.muted=true;el.playsInline=true;el.preload='auto';el.src=objectUrl;await waitVideo(el);}
     else{el.src=objectUrl;if(typeof el.decode==='function')await el.decode();}
     if(serial!==displaySerial){URL.revokeObjectURL(objectUrl);return;}
     region(item);
     const previousUrl=deck.current?.dataset?.objectUrl||null;
     const shownAt=performance.now(),exposure=DSSlides.mediaMs(item);
     await deck.present(el,item.transition||'fade',Math.min(Number(item.transition_ms??600),video===true?3000:exposure/2));
     if(serial!==displaySerial){URL.revokeObjectURL(objectUrl);return;}
     // Keep this object URL while on screen; prior nodes release their URL after transition.
     if(previousUrl)URL.revokeObjectURL(previousUrl);
     el.dataset.objectUrl=objectUrl;
     state.textContent=source+' · '+(index%items.length+1)+'/'+items.length+' · '+(item.name||'')+' · precarga activa';
     if(video){el.onended=next;el.play().catch(()=>{state.textContent='Autoplay bloqueado. Abre el reproductor con interacción del usuario.';});}
     else timer=setTimeout(next,Math.max(0,exposure-(performance.now()-shownAt)));
     prefetchNext();
   }catch(e){if(objectUrl)URL.revokeObjectURL(objectUrl);if(serial!==displaySerial)return;state.textContent='No se pudo mostrar '+(item.name||'medio')+': '+e.message;timer=setTimeout(next,3000);}
 }
 function next(){index++;if(index>=items.length)refresh();else show();}
 async function refresh(){if(refreshing||!token)return;refreshing=true;try{
   const manifest=await api('manifest');serviceApply(manifest.service);if(serviceSuspended)return;window.DSApplyMediaFormats(manifest.media_formats||{bottom_height_pct:20,side_width_pct:40});const playback=manifest.playback||{source:'none',items:[]};items=playback.items||[];index=0;
   source=playback.source==='schedule'?'Programación horaria':playback.source==='default'?'Lista predeterminada':'Sin lista asignada';
   orientation=manifest.orientation||'landscape';state.textContent=(manifest.device_name||'Pantalla')+' · '+source+' · '+items.length+' medios';
   await show();
  }catch(e){state.textContent='Error al sincronizar: '+e.message;timer=setTimeout(refresh,15000);}finally{refreshing=false;}}
 document.getElementById('pair').addEventListener('click',async()=>{
   const code=document.getElementById('code').value.trim();if(!/^[0-9]{6}$/.test(code)){status.textContent='Introduce un código de 6 dígitos.';return;}
   const btn=document.getElementById('pair');btn.disabled=true;
   try{status.textContent='Emparejando…';const d=await api('pair','POST',{code});token=d.token;++sessionGen;clearCache();setup.hidden=true;stage.hidden=false;await refresh();}
   catch(e){status.textContent=e.message;}finally{btn.disabled=false;}
 });
 document.getElementById('disconnect').addEventListener('click',()=>{
   token='';++sessionGen;++displaySerial;stopClock();for(const node of area.querySelectorAll('[data-object-url]'))URL.revokeObjectURL(node.dataset.objectUrl);
   deck.clear();clearCache();stage.hidden=true;setup.hidden=false;status.textContent='Dispositivo desconectado de este navegador.';
 });
 document.getElementById('code').addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('pair').click();});
 setInterval(async()=>{if(!token)return;try{const result=await api('service');const previous=serviceSuspended;serviceApply(result.service);await api('service','POST',{revision:result.service.revision,suspended:serviceSuspended});if(previous&&!serviceSuspended)refresh();}catch(e){}},15000);
 setInterval(()=>{if(token)api('heartbeat','POST').catch(()=>{});},60000);
})();
