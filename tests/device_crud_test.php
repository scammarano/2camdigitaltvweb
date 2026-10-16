<?php
declare(strict_types=1);
// Run after config_tenant_test.php against the isolated CI database.
session_start();
$root=dirname(__DIR__);$c=require $root.'/config/config.php';
$fixture=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$read=function(string $table,int $id)use($fixture):array{$s=$fixture->prepare('SELECT * FROM '.$table.' WHERE '.($table==='devices'?'id':'device_id').'=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:[];};
$case=$argv[1]??'';
if(!in_array($case,['forged','identity','foreign'],true))throw new RuntimeException('Unknown case');
$fixture->exec("INSERT IGNORE INTO users(id,tenant_id,name,email,password_hash,role) VALUES(99,1,'Fixture admin','fixture@example.invalid','unused','admin')");
$_SESSION['user']=['id'=>99,'tenant_id'=>1];$_SESSION['csrf']='fixture-csrf';
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/devices.php';
$id=$case==='foreign'?2:1;
$before=$read('devices',$id);
$desired=$read('device_config_desired',$id);
$_POST=['_csrf'=>'fixture-csrf','action'=>'edit','id'=>(string)$id,'tenant_id'=>'1',
 'name'=>'Renamed screen','orientation'=>'landscape','width_px'=>'1920','height_px'=>'1080','location_label'=>'Hall'];
if($case==='forged')$_POST['mode']='4';
$finished=false;
register_shutdown_function(function()use(&$finished):void{if(!$finished){fwrite(STDERR,"Device form regression did not complete\n");exit(1);}});
ob_start();require $root.'/public/devices.php';$html=ob_get_clean();restore_exception_handler();
$after=$read('devices',$id);
if((int)$after['mode']!==(int)$before['mode'])throw new RuntimeException('Identity form changed playback mode');
if($read('device_config_desired',$id)!==$desired)throw new RuntimeException('Identity form changed desired config');
if($case==='identity'){
 if($after['name']!=='Renamed screen'||$after['location_label']!=='Hall')throw new RuntimeException('Valid identity edit failed');
}elseif($after!==$before)throw new RuntimeException('Rejected request mutated screen');
if($case==='forged'&&!str_contains($html,'Recarga este formulario'))throw new RuntimeException('Missing actionable rejection');
if(str_contains($html,'name="mode"'))throw new RuntimeException('Divergent mode selector remains');
if(!str_contains($html,'Configurar modo y playlists'))throw new RuntimeException('Missing canonical configuration link');
$finished=true;
echo 'DEVICE_CRUD_PASS: '.$case.PHP_EOL;
