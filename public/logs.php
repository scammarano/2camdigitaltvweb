<?php
require __DIR__.'/_init.php';require_permission('logs.view');
$dir=ROOT.'/storage/logs';$files=array_map('basename',glob($dir.'/events-*.jsonl')?:[]);
if(isset($_GET['download'])){
 $base=basename((string)$_GET['download']);if(!preg_match('/^events-\d{4}-\d{2}-\d{2}\.jsonl$/D',$base)||!in_array($base,$files,true)){http_response_code(404);exit;}
 header('Content-Type: application/x-ndjson; charset=utf-8');header('Content-Disposition: attachment; filename="'.$base.'"');header('X-Content-Type-Options: nosniff');
 $fh=fopen($dir.'/'.$base,'rb');$t=tenant();while(($line=fgets($fh))!==false){$event=json_decode($line,true);if(is_super()||((int)($event['tenant']??-1)===$t))echo $line;}fclose($fh);exit;
}
render('Logs de diagnóstico',function()use($files){echo '<div class="card card-body"><p>Descarga JSONL por día. Los usuarios de empresa solo ven eventos asociados a su empresa. Los errores incluyen un ID de incidencia.</p>';foreach(array_reverse($files) as $file)echo '<p><a href="?download='.rawurlencode($file).'">'.h($file).'</a></p>';echo '</div>';});
