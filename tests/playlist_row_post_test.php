<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$c=require $root.'/config/config.php';
$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pid=(int)$pdo->query("SELECT id FROM playlists WHERE tenant_id=1 AND name='Batch fixture'")->fetchColumn();
$read=static function()use($pdo,$pid){$s=$pdo->prepare('SELECT * FROM playlist_items WHERE tenant_id=1 AND playlist_id=? ORDER BY position,id');$s->execute([$pid]);return $s->fetchAll();};
$before=$read();$ids=array_map('intval',array_column($before,'id'));$state=[];foreach($before as $i)$state[]=array_map(static fn($k)=>(string)($i[$k]??''),['id','position','kind','media_id','child_playlist_id','duration_seconds','repeat_count','transition_effect','transition_ms']);
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['active_tenant']=1;$_SESSION['csrf']='row-post-fixture';$_GET=[];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/playlists.php';
$case=$argv[1]??'duplicate_row';$selection=array_slice($ids,0,2);if($case==='forged')$selection[]=999999;
$_POST=['_csrf'=>'row-post-fixture','tenant_id'=>'1','playlist_id'=>(string)$pid,'action'=>$case==='separator'?'add':($case==='forged'?'bulk_delete':$case),'item_id'=>(string)$ids[0],'selection_json'=>json_encode($selection),'stamp'=>hash_hmac('sha256',json_encode($state,JSON_THROW_ON_ERROR),'row-post-fixture'),'choice'=>'separator:0','duration'=>'0.1','repeat'=>'1','transition_effect'=>'cut','transition_ms'=>'0'];
$finished=false;register_shutdown_function(function()use(&$finished){if(!$finished){fwrite(STDERR,'Row POST interrupted');exit(1);}});
ob_start();require $root.'/public/playlists.php';$html=ob_get_clean();restore_exception_handler();
$after=$read();$delta=['duplicate_row'=>1,'bulk_duplicate'=>2,'bulk_delete'=>-2,'separator'=>1,'forged'=>0][$case];
if(count($after)!==count($before)+$delta)throw new RuntimeException('Wrong count for '.$case);
if($case==='forged'){if($after!==$before||!str_contains($html,'alert alert-danger'))throw new RuntimeException('Forged row not rejected');}
elseif(str_contains($html,'alert alert-danger'))throw new RuntimeException('Row request failed: '.strip_tags($html));
if($case==='separator'&&(float)$after[count($after)-1]['duration_seconds']!==0.1)throw new RuntimeException('Separator rounded to integer');
$finished=true;echo 'PLAYLIST_ROW_POST_PASS: '.$case.PHP_EOL;
