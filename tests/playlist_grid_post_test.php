<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$c=require $root.'/config/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pid=(int)$pdo->query("SELECT id FROM playlists WHERE tenant_id=1 AND name='Editor fixture'")->fetchColumn();
$read=static function()use($pdo,$pid){$s=$pdo->prepare('SELECT * FROM playlist_items WHERE tenant_id=1 AND playlist_id=? ORDER BY position,id');$s->execute([$pid]);return $s->fetchAll();};
$before=$read();if(count($before)!==2)throw new RuntimeException('Grid fixture missing');
$state=[];$rows=[];foreach($before as $i){$state[]=array_map(static fn($k)=>(string)($i[$k]??''),['id','position','kind','media_id','child_playlist_id','duration_seconds','repeat_count','transition_effect','transition_ms']);$rows[$i['id']]=['duration'=>'21','repeat'=>'4','transition_effect'=>'cut','transition_ms'=>'0'];}
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['active_tenant']=1;$_SESSION['csrf']='grid-post-fixture';
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/playlists.php';$_GET=[];
$_POST=['_csrf'=>'grid-post-fixture','tenant_id'=>'1','playlist_id'=>(string)$pid,'action'=>'save_grid','rows_json'=>json_encode($rows),'stamp'=>hash_hmac('sha256',json_encode($state,JSON_THROW_ON_ERROR),'grid-post-fixture')];
$finished=false;register_shutdown_function(function()use(&$finished){if(!$finished){fwrite(STDERR,'Grid POST interrupted');exit(1);}});
ob_start();require $root.'/public/playlists.php';$html=ob_get_clean();restore_exception_handler();
foreach($read() as $r)if((int)$r['duration_seconds']!==21||(int)$r['repeat_count']!==4||$r['transition_effect']!=='cut'||(int)$r['transition_ms']!==0)throw new RuntimeException('Actual POST did not save all columns');
if(!str_contains($html,'Lista actualizada')||str_contains($html,'alert alert-danger'))throw new RuntimeException('POST response did not confirm save');
$expanded=expanded(1,$pid);if(count($expanded)!==8||$expanded[0]['duration']!==21||$expanded[0]['transition']!=='cut'||$expanded[0]['transition_ms']!==0)throw new RuntimeException('Playback manifest lost edited fields');
$finished=true;echo "PLAYLIST_GRID_POST_PASS: actual PHP form JSON -> persisted rows -> expanded playback manifest\n";
