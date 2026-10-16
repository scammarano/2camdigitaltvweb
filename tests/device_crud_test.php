<?php
declare(strict_types=1);
// Run after config_tenant_test.php against the isolated CI database.
require dirname(__DIR__).'/app/device_config.php';
$case=$argv[1]??'';
if(!in_array($case,['forged','identity','foreign'],true))throw new RuntimeException('Unknown case');
execsql("INSERT IGNORE INTO users(id,tenant_id,name,email,password_hash,role) VALUES(99,1,'Fixture admin','fixture@example.invalid','unused','admin')");
$_SESSION['user']=['id'=>99,'tenant_id'=>1];$_SESSION['csrf']='fixture-csrf';
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/devices.php';
$id=$case==='foreign'?2:1;
$before=dbone('SELECT * FROM devices WHERE id=?',[$id]);
$desired=ds_config_for_device($id,(int)$before['tenant_id']);
$_POST=['_csrf'=>'fixture-csrf','action'=>'edit','id'=>(string)$id,'tenant_id'=>'1',
 'name'=>'Renamed screen','orientation'=>'landscape','width_px'=>'1920','height_px'=>'1080','location_label'=>'Hall'];
if($case==='forged')$_POST['mode']='4';
ob_start();require ROOT.'/public/devices.php';$html=ob_get_clean();
$after=dbone('SELECT * FROM devices WHERE id=?',[$id]);
if((int)$after['mode']!==(int)$before['mode'])throw new RuntimeException('Identity form changed playback mode');
if(ds_config_for_device($id,(int)$before['tenant_id'])!==$desired)throw new RuntimeException('Identity form changed desired config');
if($case==='identity'){
 if($after['name']!=='Renamed screen'||$after['location_label']!=='Hall')throw new RuntimeException('Valid identity edit failed');
}elseif($after!==$before)throw new RuntimeException('Rejected request mutated screen');
if($case==='forged'&&!str_contains($html,'Recarga este formulario'))throw new RuntimeException('Missing actionable rejection');
if(str_contains($html,'name="mode"'))throw new RuntimeException('Divergent mode selector remains');
if(!str_contains($html,'Configurar modo y playlists'))throw new RuntimeException('Missing canonical configuration link');
echo 'DEVICE_CRUD_PASS: '.$case.PHP_EOL;
