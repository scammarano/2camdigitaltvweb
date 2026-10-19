(function(){
 'use strict';
 window.DSApplyMediaFormats=function(f){
  var b=Number(f.bottom_height_pct),s=Number(f.side_width_pct);
  if(!isFinite(b)||!isFinite(s)||b<5||b>80||s<5||s>80)throw new Error('Formato central inválido');
  var css=document.getElementById('ds-central-format-style');
  if(!css){css=document.createElement('style');css.id='ds-central-format-style';document.head.appendChild(css);}
  css.textContent='.ds-preview-mini-screen[data-layout="bottom"] .ds-mini-selected{top:'+(100-b)+'%;height:'+b+'%}'+
   '.ds-preview-mini-screen[data-layout="side"] .ds-mini-selected{width:'+s+'%}'+
   '.ds-player-stage .ds-display-frame.ds-layout-bottom .ds-display-area{top:'+(100-b)+'%}'+
   '.ds-player-stage .ds-display-frame.ds-layout-side .ds-display-area{left:'+(100-s)+'%}';
 };
})();
