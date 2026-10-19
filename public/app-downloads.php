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
$app=(string)($_GET['app']??'all');
if($app!=='all' && !array_key_exists($app,$platforms))$app='all';
if($tvView)$app='android-tv';

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
$showPlatforms=$app==='all'?$platforms:[$app=>$platforms[$app]];
ob_start();
?>
<div class="ds-apk-hub">
  <header class="ds-apps-intro">
    <div class="ds-apps-kicker">2CAM SERVICES · DISTRIBUCIÓN OFICIAL</div>
    <h1><?= $tvView?'2Cam Digital Signage para Fire TV':'Descargar aplicaciones' ?></h1>
    <p><?= $tvView?'Selecciona una versión publicada y descarga la APK para tu televisor.':'Un único catálogo para 2Cam Digital Signage, 2Cam Turnos y 2Cam Sports. Descarga únicamente las versiones publicadas.' ?></p>
  </header>
  <?php if(!$tvView): ?>
  <nav class="ds-apps-filter" aria-label="Filtrar por aplicación">
    <a class="<?= $app==='all'?'active':'' ?>" href="<?=h($catalogUrl(['app'=>'all']))?>">Todas las aplicaciones</a>
    <?php foreach($platforms as $slug=>$name): ?>
      <a class="<?= $app===$slug?'active':'' ?>" href="<?=h($catalogUrl(['app'=>$slug]))?>"><?=h($name)?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <nav class="ds-apps-filter ds-apps-channels" aria-label="Filtrar por canal de publicación">
    <?php foreach(['all'=>'Todos los canales','stable'=>'Estable','beta'=>'Beta','debug'=>'Pruebas'] as $key=>$label): ?>
      <a class="<?= $channel===$key?'active':'' ?>" href="<?=h($catalogUrl(['channel'=>$key]))?>"><?=h($label)?></a>
    <?php endforeach; ?>
  </nav>
  <?php if($unavailable): ?>
    <p class="ds-apk-hub-error" role="alert">No se pudo consultar el catálogo. Intenta de nuevo en unos minutos.</p>
  <?php endif; ?>
  <div class="ds-apps-grid<?=count($showPlatforms)===1?' ds-apps-grid-single':''?>">
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
  <p class="ds-apps-bottom">Los archivos APK son para Android. iPhone, iPad y Apple Watch requieren aplicaciones o interfaces compatibles con sus sistemas cuando estén disponibles.</p>
</div>
<?php
$content=(string)ob_get_clean();
$style='<link rel="stylesheet" href="'.h(url('assets/css/app-downloads.css')).'?v=0417">';
if(!$standalone){
    require_once ROOT.'/app/layout.php';
    render('Descargar aplicaciones',static function()use($content,$style):void{echo $style.$content;});
    exit;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>2Cam Services · Descargar aplicaciones</title>
<link rel="stylesheet" href="<?=h(url('assets/vendor/bootstrap.min.css'))?>"><?=$style?></head>
<body style="margin:0;background:#f1f5fa;"><main class="ds-apps-public"><?=$content?></main></body></html>
