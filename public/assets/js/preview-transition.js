/* v0.3.7 preview playback of mixed nested playlists */
(function(){
 'use strict';
 const stage=document.getElementById('stage');if(!stage||!window.DSSlides)return;
 const deck=new DSSlides.SlideDeck(stage);
 const count=document.getElementById('counter'),label=document.getElementById('currentName'),pauseBtn=document.getElementById('pause');
 const items=Array.isArray(window.DS_PREVIEW_ITEMS)?window.DS_PREVIEW_ITEMS:[];
 let index=0,timer=null,paused=false,video=null,serial=0;
 function clearClock(){if(timer!==null){clearTimeout(timer);timer=null;}}
 function waitVideo(el){return new Promise((resolve,reject)=>{let settled=false;const done=(error)=>{if(settled)return;settled=true;clearTimeout(timeout);el.removeEventListener('loadeddata',good);el.removeEventListener('error',bad);error?reject(error):resolve();};const good=()=>done(),bad=()=>done(new Error('No se pudo cargar video'));el.addEventListener('loadeddata',good);el.addEventListener('error',bad);const timeout=setTimeout(()=>done(new Error('Tiempo de carga agotado')),12000);el.load();});}
 async function show(){
   clearClock();const my=++serial;
   if(!items.length){stage.textContent='Esta lista todavía no tiene medios para mostrar.';return;}
   const item=items[index%items.length];if(!item||!item.url){label.textContent='Elemento inválido';timer=setTimeout(next,2500);return;}
   const isVideo=(item.mime||'').startsWith('video/');const el=document.createElement(isVideo?'video':'img');
   el.className='ds-preview-media';el.alt=item.name||'Medio';
   if(isVideo){el.muted=true;el.playsInline=true;el.preload='auto';el.src=item.url;}
   else{el.src=item.url;}
   try{
     if(isVideo)await waitVideo(el);else if(typeof el.decode==='function')await el.decode();
     if(my!==serial)return;
     await deck.present(el,item.transition||'fade',Number(item.transition_ms??600));
     if(my!==serial)return;
     video=isVideo?el:null;count.textContent=(index%items.length+1)+' / '+items.length;label.textContent=item.name||'Medio';
     if(isVideo){el.onended=next;if(!paused)el.play().catch(()=>{label.textContent='Presiona Reanudar para iniciar el video';});}
     else if(!paused)timer=setTimeout(next,Math.max(1,Number(item.duration)||10)*1000);
     prefetchNext();
   }catch(e){if(my!==serial)return;label.textContent='Error de medio: '+(item.name||'Sin nombre');timer=setTimeout(next,2800);}
 }
 function prefetchNext(){if(items.length<=1)return;const item=items[(index+1)%items.length];if(!item||!(item.mime||'').startsWith('image/'))return;const img=new Image();img.src=item.url;if(typeof img.decode==='function')img.decode().catch(()=>{});}
 function next(){index++;show();}
 pauseBtn.addEventListener('click',()=>{paused=!paused;pauseBtn.textContent=paused?'Reanudar':'Pausar';if(video){if(paused)video.pause();else video.play().catch(()=>{});}else{clearClock();if(!paused)timer=setTimeout(next,Math.max(1,Number(items[index%items.length]?.duration)||10)*1000);}});
 document.getElementById('next').addEventListener('click',next);
 show();
})();
