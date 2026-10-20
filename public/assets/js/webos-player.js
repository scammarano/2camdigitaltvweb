/* LG webOS TV: focus/navigation only. Never bypasses system back/home controls. */
(function () {
  'use strict';
  if (!(/web0s|webos/i.test(navigator.userAgent) ||
        new URLSearchParams(location.search).get('client') === 'webos')) return;

  document.body.classList.add('ds-webos-tv');
  var setup = document.getElementById('setup');
  var stage = document.getElementById('stage');
  var code = document.getElementById('code');
  var disconnect = document.getElementById('disconnect');
  var dismiss = document.createElement('button');
  var hideTimer = null;
  dismiss.type = 'button';
  dismiss.id = 'lg-menu-close';
  dismiss.textContent = 'Continuar reproducción';
  dismiss.className = 'lg-menu-close';
  stage.appendChild(dismiss);
  disconnect.textContent = 'Desvincular LG';
  disconnect.setAttribute('aria-label', 'Desvincular esta pantalla de LG');
  function hideMenu() {
    document.body.classList.remove('ds-lg-menu-open');
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = null;
    if (!stage.hidden) stage.focus();
  }
  function showMenu() {
    if (stage.hidden) return;
    document.body.classList.add('ds-lg-menu-open');
    dismiss.focus();
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(hideMenu, 30000);
  }
  stage.setAttribute('tabindex', '-1');
  dismiss.addEventListener('click', hideMenu);
  disconnect.addEventListener('click', hideMenu);
  document.addEventListener('keydown', function (e) {
    var back = (e.keyCode === 461 || e.key === 'Escape' || e.key === 'BrowserBack');
    if (back && !stage.hidden) {
      e.preventDefault();
      e.stopPropagation();
      if (document.body.classList.contains('ds-lg-menu-open')) hideMenu();
      else showMenu();
      return;
    }
    // The numeric remote can enter pairing digits directly while setup is open.
    if (!setup.hidden && e.key && /^[0-9]$/.test(e.key) &&
        document.activeElement !== code) {
      if (code.value.length >= 6) code.value = '';
      code.value += e.key;
      code.focus();
      e.preventDefault();
    }
    if (!setup.hidden && e.key === 'Enter' && document.activeElement === code) {
      document.getElementById('pair').click();
      e.preventDefault();
    }
  }, true);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) hideMenu();
  });
  window.addEventListener('webOSRelaunch', function () {
    if (!stage.hidden) hideMenu();
  });
  if (!stage.hidden) stage.focus(); else code.focus();
}());
