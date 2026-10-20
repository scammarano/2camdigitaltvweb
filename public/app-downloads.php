<?php
/** Canonical read-only APK catalogue for all three products; old URLs stay compatible. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/apk_releases.php';

$channels=ds_apk_channels();
$platforms=ds_apk_platforms();
$channel=(string)($_GET['channel']??'all');
if($channel!=='all' && !isset($channels[$channel]))$channel='all';
$tvView=basename((string)($_SERVER['SCRIPT_NAME']??''))==='tv.php';
$isLGWebOS=preg_match('/web0s|webos/i',(string)($_SERVER['HTTP_USER_AGENT']??''))===1;
$app=(string)($_GET['app']??($isLGWebOS?'lg-webos':'all'));
if($app!=='all' && $app!=='lg-webos' && !array_key_exists($app,$platforms))$app='all';
if($tvView)$app=$isLGWebOS?'lg-webos':'android-tv';

// Explicit public mode (e.g. tv.php) never requires a login or exposes tenant data.
$session=false;
try{$session=user()!==null;}catch(Throwable $ignored){$session=false;}
$standalone=((string)($_GET['standalone']??'')==='1')||!$session;
$filters=['app'=>$app,'channel'=>$channel];
if($standalone)$filters['standalone']='1';
$catalogUrl=static function(array $changes=[])use($filters):string {
    $p=array_replace($filters,$changes);
    foreach($p as $key=>$value)if($value==='all' || $value==='')unset($p[$key]);
    return url('app-downloads.php').($p?'?'.http_build_query($p,'','&',PHP_QUERY_RFC3986):'');
};

$releases=[];$unavailable=false;
try{
    $query="SELECT * FROM app_releases WHERE status='published' AND platform IN ('android-tv','turnos-control','sports-control')".
        ($channel==='all'?'':' AND channel=?').
        " ORDER BY version_code DESC, CASE channel WHEN 'stable' THEN 0 WHEN 'beta' THEN 1 ELSE 2 END, id DESC";
    foreach(dball($query,$channel==='all'?[]:[$channel]) as $row){
        $platform=(string)$row['platform'];
        // Keep history for each app, not just the newest APK, so the legacy page loses no functionality.
        if(count($releases[$platform]??[])<25)$releases[$platform][]=$row;
    }
}catch(Throwable $e){
    $unavailable=true;
    log_event('unified_apk_catalog_error',['type'=>'database']);
}

$descriptions=[
 'android-tv'=>['TV / Fire Stick','Reproduce campañas, playlists y avisos de Turnos y Sports en pantallas.'],
 'turnos-control'=>['Android móvil / tablet','Control de atención, emisión y llamados de turnos; integración con las pantallas.'],
 'sports-control'=>['Android móvil / tablet','Control de encuentros, pistas, marcador y puntuación deportiva.'],
];
$showPlatforms=$app==='all'?$platforms:($app==='lg-webos'?[]:[$app=>$platforms[$app]]);
$showLGWebOS=$app==='all'||$app==='lg-webos';
$webosReleaseUrl='https://github.com/scammarano/2camdigitaltvweb/releases/tag/webos-v0.1.0';
$webosIpkUrl='https://github.com/scammarano/2camdigitaltvweb/releases/download/webos-v0.1.0/com.twocam.signage_0.1.0_all.ipk';
ob_start();
?>
<div class="ds-apk-hub">
  <header class="ds-apps-intro">
    <div class="ds-apps-kicker">2CAM SERVICES · DISTRIBUCIÓN OFICIAL</div>
    <h1><?= $tvView?($isLGWebOS?'2Cam Digital Signage para LG webOS':'2Cam Digital Signage para Fire TV'):'Descargar aplicaciones' ?></h1>
    <p><?= $tvView?($isLGWebOS?'Reproduce campañas directamente en tu LG, sin Android ni Fire Stick.':'Selecciona una versión publicada y descarga la APK para tu televisor.'):'Descargas para LG webOS, Android/Fire TV, Turnos y Sports.' ?></p>
  </header>
  <?php if(!$tvView): ?>
  <nav class="ds-apps-filter" aria-label="Filtrar por aplicación">
    <a class="<?= $app==='all'?'active':'' ?>" href="<?=h($catalogUrl(['app'=>'all']))?>">Todas las aplicaciones</a>
    <?php foreach($platforms as $slug=>$name): ?>
      <a class="<?= $app===$slug?'active':'' ?>" href="<?=h($catalogUrl(['app'=>$slug]))?>"><?=h($name)?></a>
    <?php endforeach; ?>
    <a class="<?= $app==='lg-webos'?'active':'' ?>" href="<?=h($catalogUrl(['app'=>'lg-webos']))?>">LG webOS TV</a>
  </nav>
  <?php endif; ?>
  <?php if($app!=='lg-webos'): ?><nav class="ds-apps-filter ds-apps-channels" aria-label="Filtrar por canal de publicación">
    <?php foreach(['all'=>'Todos los canales','stable'=>'Estable','beta'=>'Beta','debug'=>'Pruebas'] as $key=>$label): ?>
      <a class="<?= $channel===$key?'active':'' ?>" href="<?=h($catalogUrl(['channel'=>$key]))?>"><?=h($label)?></a>
    <?php endforeach; ?>
  </nav><?php endif; ?>
  <?php if($unavailable && $app!=='lg-webos'): ?>
    <p class="ds-apk-hub-error" role="alert">No se pudo consultar el catálogo. Intenta de nuevo en unos minutos.</p>
  <?php endif; ?>
  <div class="ds-apps-grid<?=count($showPlatforms)+($showLGWebOS?1:0)===1?' ds-apps-grid-single':''?>">
    <?php if($showLGWebOS): ?>
    <article class="ds-apps-card ds-apps-lg" data-platform="lg-webos">
      <span class="ds-apps-family">TV LG · webOS</span>
      <h2>2Cam Digital Signage para LG</h2>
      <p>Reproductor para webOS TV conectado al mismo portal, con listas, programación y emparejamiento de seis dígitos.</p>
      <div class="ds-lg-compatible">LG webOS TV · probado inicialmente para el modelo 75UR7800PSB (webOS 25)</div>
      <p class="ds-apps-revision"><strong>v0.1.0 · Versión de pruebas</strong><br>
      Aplicación LG <code>.ipk</code>, diferente de Android <code>.apk</code>.</p>
      <a class="ds-apps-download" href="<?=h(url('player.php?client=webos'))?>">Abrir reproductor web ahora</a>
      <a class="ds-apps-download ds-apps-download-secondary" href="<?=h($webosIpkUrl)?>">Descargar aplicación LG (.ipk) para PC</a>
      <p class="ds-apps-technical">La instalación .ipk se realiza desde una computadora mediante Developer Mode; no se instala pulsando el archivo en el navegador del LG.</p>
      <p class="ds-apps-technical"><a href="<?=h(url('webos-install.php'))?>">Guía paso a paso para instalar en LG</a>
      · <a href="<?=h($webosReleaseUrl)?>">Consultar publicación en GitHub</a></p>
      <div class="ds-apps-tv-help"><strong>Sin Fire Stick ni caja Android</strong>
      <p>Para una prueba inmediata abre el reproductor web. Esta primera versión no ofrece inicio automático al encender, overlay sobre otras aplicaciones ni caché offline persistente.</p></div>
    </article>
    <?php endif; ?>
    <?php foreach($showPlatforms as $slug=>$name):
        $meta=$descriptions[$slug];$history=$releases[$slug]??[];$latest=$history[0]??null;
    ?>
      <article class="ds-apps-card">
        <span class="ds-apps-family"><?=h($meta[0])?></span>
        <h2><?=h($name)?></h2>
        <p><?=h($meta[1])?></p>
        <?php if($latest): ?>
          <p class="ds-apps-revision"><strong>Versión <?=h((string)$latest['version_name'])?></strong>
          · <?=h($channels[(string)$latest['channel']]??(string)$latest['channel'])?><br>
          Android API <?= (int)$latest['min_sdk'] ?>+ · <?=h(ds_apk_human_size((int)$latest['file_size']))?></p>
          <?php if((string)$latest['channel']==='debug'): ?>
            <p class="ds-apps-warning">APK de pruebas: Android puede exigir reinstalar si el certificado de firma ha cambiado.</p>
          <?php endif; ?>
          <a class="ds-apps-download" href="<?=h(ds_apk_public_url($latest))?>" type="application/vnd.android.package-archive">Descargar APK v<?=h((string)$latest['version_name'])?></a>
          <p class="ds-apps-technical"><a href="<?=h(ds_apk_public_url($latest))?>">Enlace directo de descarga</a></p>
          <?php if(count($history)>1): ?>
            <details class="ds-apps-history">
              <summary>Ver otras versiones (<?=count($history)-1?>)</summary>
              <div class="ds-apps-releases">
                <?php foreach(array_slice($history,1) as $release): ?>
                  <div class="ds-apps-release">
                    <div><strong>v<?=h((string)$release['version_name'])?></strong> · <?=h($channels[(string)$release['channel']]??(string)$release['channel'])?><br>
                      <span>API <?= (int)$release['min_sdk']?>+ · <?=h(ds_apk_human_size((int)$release['file_size']))?></span>
                    </div>
                    <a href="<?=h(ds_apk_public_url($release))?>" type="application/vnd.android.package-archive" aria-label="Descargar versión <?=h((string)$release['version_name'])?>">Descargar</a>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>
          <details class="ds-apps-history ds-apps-integrity">
            <summary>Detalles técnicos e integridad</summary>
            <p>Versión interna: <?= (int)$latest['version_code'] ?> · API mínima: <?= (int)$latest['min_sdk'] ?></p>
            <p>SHA-256: <code><?=h((string)$latest['sha256'])?></code></p>
            <?php if((string)($latest['release_notes']??'')!==''): ?><p><?=nl2br(h((string)$latest['release_notes']))?></p><?php endif; ?>
          </details>
        <?php elseif(!$unavailable): ?>
          <div class="ds-apps-pending">Todavía no hay APK publicada<?= $channel==='all'?'':' en este canal' ?>.</div>
          <p class="ds-apps-technical">La descarga aparecerá cuando esté disponible una versión aprobada.</p>
        <?php endif; ?>
        <?php if($slug==='android-tv'): ?>
          <div class="ds-apps-tv-help">
            <strong>Instalación en Fire Stick</strong>
            <p>Abre esta página desde Downloader, elige una versión publicada y confirma la instalación. Las actualizaciones pueden requerir autorización de Fire OS y conservar la misma firma Android.</p>
            <?php if($latest): ?><p>Descarga directa corta: <a href="<?=h(url('tv.apk'))?>"><code><?=h(url('tv.apk'))?></code></a></p><?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="ds-apps-bottom">LG webOS utiliza aplicaciones .ipk; Android y Fire TV utilizan .apk. Los .ipk se instalan desde una computadora, no directamente desde el navegador del televisor.</p>
</div>
<?php
$content=(string)ob_get_clean();
$style='<link rel="stylesheet" href="'.h(url('assets/css/app-downloads.css')).'?v=webos010">';
if(!$standalone){
    require_once ROOT.'/app/layout.php';
    render('Descargar aplicaciones',static function()use($content,$style):void{echo $style.$content;});
    exit;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>2Cam Services · Descargar aplicaciones</title>
<link rel="stylesheet" href="<?=h(url('assets/vendor/bootstrap.min.css'))?>"><?=$style?></head>
<body style="margin:0;background:#f1f5fa;"><main class="ds-apps-public"><?=$content?></main></body></html>
