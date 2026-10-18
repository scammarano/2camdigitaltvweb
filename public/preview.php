<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_permission('playlists.view');
$id=(int)($_GET['id']??0);
$p=is_super()?dbone('SELECT * FROM playlists WHERE id=?',[$id]):ds_content_record('playlists',$id,tenant());
if(!$p){http_response_code(404);exit('Lista no encontrada');}
try{$entries=expanded((int)$p['tenant_id'],$id);}catch(Throwable $e){http_response_code(422);exit(h(fault($e,'preview')));}
foreach($entries as &$item)$item['url']=url('media-file.php?id='.$item['media_id']);unset($item);
$orientation=$p['orientation']==='portrait'?'portrait':'landscape';
$layout=in_array($p['layout_type'],['fullscreen','bottom','side','half_horizontal','half_vertical'],true)?$p['layout_type']:'fullscreen';
$label=layouts()[$layout]??'Pantalla completa';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vista previa · <?=h($p['name'])?></title><link rel="stylesheet" href="<?=h(url('assets/vendor/bootstrap.min.css'))?>"><link rel="stylesheet" href="<?=h(url('assets/css/signage.css'))?>?v=0428"></head>
<body class="ds-preview-body"><div class="ds-preview-shell"><header class="ds-preview-header"><a class="ds-preview-back" href="<?=h(url('playlists.php'))?>" aria-label="Volver a listas">←</a><div class="ds-preview-heading"><strong>Vista previa</strong><small><?=h($p['name'])?></small></div><div class="ds-preview-tags"><span><?=h(orientations()[$p['orientation']]??'Ambas')?></span><span><?=h($label)?></span></div></header>
<main class="ds-preview-main"><div class="ds-preview-sim"><div class="ds-preview-frame <?=h($orientation)?> ds-layout-<?=h($layout)?>" id="simulation"><div class="ds-preview-backdrop"><span>SIMULACIÓN DE PANTALLA</span></div><div class="ds-preview-content" id="stage"><div class="ds-preview-placeholder">Agrega imágenes o videos para iniciar esta vista previa.</div></div></div></div><div class="ds-preview-controls"><div class="ds-preview-meta"><div id="counter">0 / <?=count($entries)?></div><strong id="currentName">Sin contenido</strong></div><div class="ds-preview-buttons"><button type="button" id="pause" class="btn btn-outline-light">Pausar</button><button type="button" id="next" class="btn btn-primary">Siguiente →</button></div></div><p class="ds-preview-note">Simulación de orientación y área de visualización. La vista previa no modifica la programación ni los archivos originales.</p></main></div>
<script>window.DS_PREVIEW_ITEMS=<?=json_encode($entries,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;</script>
<script src="<?=h(url('assets/js/media-format-runtime.js'))?>"></script><script src="<?=h(url('media-formats-data.php'))?>"></script><script src="<?=h(url('assets/js/transition-engine.js'))?>?v=0428"></script>
<script src="<?=h(url('assets/js/preview-transition.js'))?>?v=0428"></script></body></html>
