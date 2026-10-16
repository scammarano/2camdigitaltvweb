/* TV remote-friendly portal shell, ES5 for older Fire OS browsers. */
(function(){
 'use strict';
 var button=document.getElementById('ds-menu-toggle');
 var backdrop=document.getElementById('ds-backdrop');
 function closeNav(){
  document.body.classList.remove('ds-nav-open');
  if(button)button.setAttribute('aria-expanded','false');
  if(backdrop)backdrop.hidden=true;
 }
 if(button)button.addEventListener('click',function(){
  var open=document.body.classList.toggle('ds-nav-open');
  button.setAttribute('aria-expanded',String(open));
  if(backdrop)backdrop.hidden=!open;
 });
 if(backdrop)backdrop.addEventListener('click',closeNav);
 document.addEventListener('keydown',function(ev){if(ev.key==='Escape')closeNav();});
 var anchors=document.querySelectorAll('.ds-sidebar a');
 for(var i=0;i<anchors.length;i++)anchors[i].addEventListener('click',closeNav);
 var modals=document.querySelectorAll('.modal');
 for(var j=0;j<modals.length;j++){
  if(modals[j].parentElement!==document.body)document.body.appendChild(modals[j]);
 }
 var cells=document.querySelectorAll('.table td');
 for(var k=0;k<cells.length;k++)if(cells[k].querySelector('button, a.btn'))cells[k].classList.add('ds-row-actions');
 var cards=document.querySelectorAll('.card.table-responsive');
 for(var m=0;m<cards.length;m++)cards[m].classList.add('ds-table-card');
 var tables=document.querySelectorAll('.table');
 for(var n=0;n<tables.length;n++)tables[n].classList.add('ds-data-table');
 var checks=document.querySelectorAll('input[type="checkbox"]');
 for(var p=0;p<checks.length;p++)checks[p].classList.add('ds-checkbox');
 var list=document.getElementById('ds-sidebar-scroll');
 function scrollNav(amount){if(list)list.scrollTop+=amount;}
 var up=document.getElementById('ds-nav-up'),down=document.getElementById('ds-nav-down');
 if(up)up.addEventListener('click',function(){scrollNav(-280);});
 if(down)down.addEventListener('click',function(){scrollNav(280);});
 if(list){
  list.addEventListener('keydown',function(ev){
   if(ev.key==='PageDown'){ev.preventDefault();scrollNav(300);}
   if(ev.key==='PageUp'){ev.preventDefault();scrollNav(-300);}
  });
  var items=list.querySelectorAll('a');
  for(var a=0;a<items.length;a++){
   items[a].addEventListener('focus',function(){
    var position=this.offsetTop-list.offsetTop;
    if(position<list.scrollTop)list.scrollTop=Math.max(0,position-14);
    else if(position+this.offsetHeight>list.scrollTop+list.clientHeight)
      list.scrollTop=position+this.offsetHeight-list.clientHeight+16;
   });
  }
 }
})();
