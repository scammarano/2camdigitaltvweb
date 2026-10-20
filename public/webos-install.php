<?php
/** Read-only public guide for LG home TV webOS installation. Never exposes credentials. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
?><!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalar 2Cam Digital Signage en LG webOS TV</title>
<link rel="stylesheet" href="<?=h(url('assets/vendor/bootstrap.min.css'))?>">
<style>body{background:#f3f6fb;color:#1b314b;font:18px Arial,sans-serif}.guide{max-width:1050px;margin:auto;padding:28px}h1{font-size:clamp(27px,5vw,40px)}h2{font-size:25px;color:#174589}.panel{background:white;border-radius:16px;padding:24px;margin:20px 0;border:1px solid #d5e3f1}li{padding:10px 0;line-height:1.55}code,pre{background:#edf2f9;color:#134477;padding:8px;white-space:pre-wrap;overflow-wrap:anywhere}a{color:#174589}a:focus-visible{outline:3px solid #edaa22}.attention{border-left:6px solid #dca827;padding:15px;background:#fff9e8}.primary{display:inline-block;padding:15px 20px;background:#174589;border-radius:10px;color:white!important;text-decoration:none}small{color:#536a7f}</style>
</head><body><main class="guide">
<p><a href="<?=h(url('app-downloads.php?app=lg-webos&standalone=1'))?>">← Volver a descargas</a></p>
<h1>2Cam Digital Signage · LG webOS TV</h1>
<p><strong>Para LG 75UR7800PSB con webOS 25</strong>. Sin Fire Stick; la aplicación LG usa un paquete .ipk, no una APK Android.</p>
<div class="panel"><h2>Opción inmediata: navegador LG</h2>
<p>Si todavía no tienes una computadora disponible, puedes abrir el reproductor del portal desde el navegador del LG. Necesita conexión a internet y código de vinculación del portal.</p>
<p><a class="primary" href="<?=h(url('player.php?client=webos'))?>">Abrir reproductor web</a></p></div>
<div class="panel"><h2>Instalar la aplicación LG (.ipk) desde una PC</h2>
<ol>
<li>Conecta la PC y el LG a la misma red local. En la tienda LG del televisor instala la aplicación <strong>Developer Mode</strong>.</li>
<li>Accede en la aplicación con una cuenta LG Developer, activa <strong>Dev Mode Status</strong> y sigue la indicación de reinicio.</li>
<li>Abre Developer Mode, activa <strong>Key Server</strong> y anota en privado la IP local del LG y la frase de autorización. No envíes esa frase por correo o chat.</li>
<li>En la PC instala Node.js y la CLI oficial de LG: <code>npm install -g @webos-tools/cli</code>.</li>
<li>Registra el LG en tu PC, sustituyendo <code>IP_DEL_TV</code> por su IP:<pre>ares-setup-device --add lg-tv -i "host=IP_DEL_TV" -i "port=9922" -i "username=prisoner"
ares-novacom --device lg-tv --getkey</pre>Introduce la frase del Key Server <strong>solo en la PC</strong>.</li>
<li>Descarga el archivo <code>com.twocam.signage_0.1.0_all.ipk</code> desde <a href="https://github.com/scammarano/2camdigitaltvweb/releases/tag/webos-v0.1.0">GitHub Releases webOS</a> y ejecuta en la carpeta donde lo guardaste:<pre>ares-install --device lg-tv com.twocam.signage_0.1.0_all.ipk
ares-launch --device lg-tv com.twocam.signage</pre></li>
<li>Desde el portal de 2Cam, registra la pantalla LG, asigna una lista adecuada y genera un código temporal de seis dígitos. Introdúcelo en el reproductor y comienza a mostrar contenido.</li>
</ol>
<p><small>Consulta oficial: <a href="https://webostv.developer.lge.com/develop/getting-started/developer-mode-app">LG Developer Mode</a> y <a href="https://webostv.developer.lge.com/develop/tools/cli-dev-guide">LG webOS CLI</a>.</small></p></div>
<div class="attention"><strong>Condiciones de esta versión de pruebas:</strong> Developer Mode requiere renovar su sesión periódicamente; no equivale a instalación desde LG Store. En webOS doméstico no garantizamos inicio automático al encender, overlay sobre otras aplicaciones ni reproducción sin internet.</div>
</main></body></html>
