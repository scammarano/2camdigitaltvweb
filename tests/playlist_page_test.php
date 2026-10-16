<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$c=require $root.'/config/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$case=$argv[1]??'';if(!in_array($case,['view','copy','forbidden'],true))throw new RuntimeException('Unknown page test');
$tid=$case==='forbidden'?2:1;$s=$pdo->prepare('SELECT id FROM playlists WHERE tenant_id=? AND name=?');$s->execute([$tid,$case==='forbidden'?'Other company copy':'Copy source']);$id=(int)$s->fetchColumn();if(!$id)throw new RuntimeException('Fixture missing');
$_SESSION['user']=['id'=>$case==='forbidden'?99:100,'tenant_id'=>1];$_SESSION['csrf']='fixture-csrf';
$_GET=['company'=>'2'];$_SERVER['SCRIPT_NAME']='/playlists.php';$_SERVER['REQUEST_METHOD']=$case==='view'?'GET':'POST';
if($case!=='view')$_POST=['_csrf'=>'fixture-csrf','action'=>'duplicate','playlist_id'=>(string)$id,'tenant_id'=>'2','copy_name'=>'UI destination copy'];
$before=(int)$pdo->query('SELECT COUNT(*) FROM playlists')->fetchColumn();$finished=false;
register_shutdown_function(function()use(&$finished):void{if(!$finished){fwrite(STDERR,"Playlist page test interrupted\n");exit(1);}});
ob_start();require $root.'/public/playlists.php';$html=ob_get_clean();restore_exception_handler();
$after=(int)$pdo->query('SELECT COUNT(*) FROM playlists')->fetchColumn();
if(!str_contains($html,'Duplicar / copiar a empresa'))throw new RuntimeException('Copy action not visible');
if($case==='view'&&!str_contains($html,'Other company copy'))throw new RuntimeException('Superadmin company filter failed');
if($case==='copy'&&($after!==$before+2||!str_contains($html,'Ver copia en la empresa de destino')))throw new RuntimeException('POST copy failed');
if($case==='forbidden'&&($after!==$before||!str_contains($html,'alert alert-danger')))throw new RuntimeException('Forged cross-company POST accepted');
$finished=true;echo 'PLAYLIST_PAGE_PASS: '.$case.PHP_EOL;
