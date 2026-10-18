(() => {
 'use strict';
 document.querySelectorAll('[data-settings-tabs]').forEach(root=>{
  const tabs=[...root.querySelectorAll('[data-tab-target]')],panes=[...root.querySelectorAll('[data-tab-panel]')];
  function show(tab){tabs.forEach(t=>{t.setAttribute('aria-selected',String(t===tab));t.classList.toggle('active',t===tab);});panes.forEach(p=>{p.hidden=p.dataset.tabPanel!==tab.dataset.tabTarget;});}
  tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>show(tab));tab.addEventListener('keydown',e=>{if(e.key!=='ArrowRight'&&e.key!=='ArrowLeft')return;e.preventDefault();const next=tabs[(index+(e.key==='ArrowRight'?1:tabs.length-1))%tabs.length];show(next);next.focus();});});
  if(tabs[0])show(tabs[0]);
  root.addEventListener('invalid',e=>{const pane=e.target.closest('[data-tab-panel]');const tab=pane&&tabs.find(t=>t.dataset.tabTarget===pane.dataset.tabPanel);if(tab)show(tab);},true);
  root.querySelectorAll('[data-playlist-preview]').forEach(link=>{const select=root.querySelector('[name="'+link.dataset.playlistPreview+'"]');function update(){link.href=link.dataset.previewBase+encodeURIComponent(select.value);link.hidden=Number(select.value)<=0;}select.addEventListener('change',update);update();});
 });
})();
