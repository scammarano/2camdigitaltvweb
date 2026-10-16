<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/schema.php';require_permission('updates.manage');
$checks=[];
$checks['Versión']=trim((string)file_get_contents(ROOT.'/version.txt'));
$checks['PHP']=PHP_VERSION;
$checks['PDO MySQL']=extension_loaded('pdo_mysql')?'Disponible':'FALTA';
$checks['ZIP']=class_exists('ZipArchive')?'Disponible':'FALTA';
$checks['Sodium']=function_exists('sodium_crypto_sign_verify_detached')?'Disponible':'FALTA';
$checks['OpenSSL']=function_exists('openssl_verify')?'Disponible':'FALTA';
$checks['Clave Ed25519 heredada']=is_file(ROOT.'/config/update-pubkey.txt')?'Presente':'FALTA';
$rsaPem=ROOT.'/config/update-rsa-pubkey.pem';
$rsaKey=is_file($rsaPem)&&function_exists('openssl_pkey_get_public')?@openssl_pkey_get_public((string)file_get_contents($rsaPem)):false;
$rsaDetails=$rsaKey?openssl_pkey_get_details($rsaKey):false;
$checks['Clave pública RSA-SHA256']=($rsaDetails&&($rsaDetails['type']??-1)===OPENSSL_KEYTYPE_RSA&&($rsaDetails['bits']??0)>=3072)?'Disponible ('.$rsaDetails['bits'].' bits)':'FALTA O INVÁLIDA';
$checks['Motor actualización segura']=class_exists('ZipArchive')&&function_exists('openssl_verify')&&str_starts_with($checks['Clave pública RSA-SHA256'],'Disponible')?'Listo RSA-SHA256':'Requiere intervención';
$checks['Storage escribible']=is_writable(ROOT.'/storage')?'Sí':'NO';
$checks['Config escribible']=is_writable(ROOT.'/config')?'Sí (restringir cuando sea posible)':'No';
$checks['Ruta pública']=url('index.php');
$checks['Límite subida']=ini_get('upload_max_filesize').' (post_max_size '.ini_get('post_max_size').')';
$checks['Bloqueo mantenimiento']=is_file(ROOT.'/storage/maintenance.lock')?'ACTIVO':'No';
try{$missing=schema_check();$checks['Esquema MySQL']=$missing?'INCOMPLETO: '.implode(', ',$missing):'Completo 0.3.0';}catch(Throwable $e){$checks['Esquema MySQL']='ERROR. Incidencia '.fault($e,'diagnostics_schema');}
$events=[];foreach(array_reverse(glob(ROOT.'/storage/logs/events-*.jsonl')?:[]) as $file){$lines=file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];foreach(array_reverse($lines) as $line){$r=json_decode($line,true);if(!is_array($r)||!in_array($r['kind']??'',['error','update_failed','update_rejected'],true))continue;$events[]=['date'=>$r['at']??'','kind'=>$r['kind'],'incident'=>$r['data']['incidence']??'','module'=>$r['data']['module']??'','message'=>$r['data']['message']??$r['data']['error']??''];if(count($events)>=30)break 2;}}
if(isset($_GET['export'])){header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="signage-diagnostics.json"');echo json_encode(['checks'=>$checks,'events'=>$events],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;}
render('Diagnóstico del sistema',function()use($checks,$events){
 echo '<p><a class="btn btn-outline-primary" href="?export=1">Exportar diagnóstico JSON</a> <a class="btn btn-outline-secondary" href="'.h(url('upgrade.php')).'">Revisar esquema</a></p>';
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Comprobación</th><th>Resultado</th></tr></thead><tbody>';foreach($checks as $k=>$v)echo '<tr><td>'.h($k).'</td><td>'.h((string)$v).'</td></tr>';echo '</tbody></table></div>';
 echo '<h4>Últimas incidencias registradas</h4><div class="card table-responsive"><table class="table"><thead><tr><th>UTC</th><th>Tipo</th><th>ID</th><th>Módulo</th><th>Detalle técnico (solo SUPERADMIN)</th></tr></thead><tbody>';
 foreach($events as $e)echo '<tr><td>'.h($e['date']).'</td><td>'.h($e['kind']).'</td><td>'.h($e['incident']).'</td><td>'.h($e['module']).'</td><td>'.h($e['message']).'</td></tr>';echo '</tbody></table></div>';
});
