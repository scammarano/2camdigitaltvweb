/* Digital Signage v0.3.7: two-layer transitions. Does not download or retain media. */
(function(global){
 'use strict';
 const allowed=new Set(['fade','slide-left','slide-right','zoom','cut']);
 const clamp=n=>Number.isFinite(Number(n))?Math.max(0,Math.min(3000,Math.round(Number(n)))):600;
 class SlideDeck {
  constructor(container){this.container=container;this.current=null;this.seq=0;}
  clear(){++this.seq;this.container.querySelectorAll('video').forEach(v=>{v.pause();v.removeAttribute('src');v.load();});this.container.replaceChildren();this.current=null;}
  async present(node,effect='fade',ms=600){
   const serial=++this.seq, old=this.current;
   effect=allowed.has(effect)?effect:'fade';ms=effect==='cut'?0:clamp(ms);
   const reduced=typeof global.matchMedia==='function' && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
   if(reduced)ms=0;
   node.classList.add('ds-slide-layer');node.style.opacity='1';
   this.container.appendChild(node);
   if(!old || ms===0 || typeof node.animate!=='function'){
    if(old){if(old.tagName==='VIDEO')old.pause();old.remove();}
    this.current=node;return;
   }
   let incoming,outgoing;
   switch(effect){
    case 'slide-left':incoming=[{transform:'translateX(100%)',opacity:1},{transform:'translateX(0)',opacity:1}];outgoing=[{transform:'translateX(0)',opacity:1},{transform:'translateX(-25%)',opacity:0}];break;
    case 'slide-right':incoming=[{transform:'translateX(-100%)',opacity:1},{transform:'translateX(0)',opacity:1}];outgoing=[{transform:'translateX(0)',opacity:1},{transform:'translateX(25%)',opacity:0}];break;
    case 'zoom':incoming=[{transform:'scale(1.06)',opacity:0},{transform:'scale(1)',opacity:1}];outgoing=[{transform:'scale(1)',opacity:1},{transform:'scale(.98)',opacity:0}];break;
    default:incoming=[{opacity:0},{opacity:1}];outgoing=[{opacity:1},{opacity:0}];
   }
   const opts={duration:ms,easing:'ease-in-out',fill:'forwards'};
   const first=node.animate(incoming,opts),second=old.animate(outgoing,opts);
   try {await Promise.all([first.finished,second.finished]);}catch(e) { /* animation cancelled on navigation */ }
   if(this.seq!==serial){if(node!==this.current)node.remove();return;}
   if(old.tagName==='VIDEO')old.pause();old.remove();this.current=node;
  }
 }
 const mediaMs=item=>{const n=Number(item.duration??10);return Math.round(Math.max(0.1,Math.min(86400,Number.isFinite(n)?n:10))*1000);};
 global.DSSlides={SlideDeck,mediaMs};
})(window);
