<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$config=require $root.'/config/config.php';
$pdo=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$page=$argv[1]??'playlists';$case=$argv[2]??'selected';
if(!in_array($page,['playlists','media','devices','schedules','mode-schedules','index'],true)||!in_array($case,['selected','all','tenant','screen'],true))throw new RuntimeException('Invalid test');
$_SESSION['user']=['id'=>$case==='tenant'?99:100,'tenant_id'=>1];$_SESSION['csrf']='scope-fixture';if($case!=='all')$_SESSION['active_tenant']=1;
$_GET=[];if($case==='screen')$_GET['device']='1';$_SERVER['SCRIPT_NAME']='/'.$page.'.php';$_SERVER['REQUEST_METHOD']='GET';
$finished=false;register_shutdown_function(function()use(&$finished){if(!$finished){fwrite(STDERR,'Scope page interrupted');exit(1);}});
ob_start();require $root.'/public/'.$page.'.php';$html=ob_get_clean();restore_exception_handler();
if(!str_contains($html,'<!doctype html>')||str_contains($html,'No se pudo completar la operación'))throw new RuntimeException('Page did not render: '.$page);
$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
if($page!=='index'){
 $selector=$xp->query('//*[@id="ds-company-filter"]/option[@selected]');
 if($case==='tenant'){if($selector->length)throw new RuntimeException('Tenant sees global scope selector');}
 else if($selector->length!==1||$selector->item(0)->getAttribute('value')!==($case==='all'?'all':'1'))throw new RuntimeException('Default company filter mismatch: '.$page);
}
if($page==='playlists'){
 $details=$xp->query('//details[contains(@class,"ds-playlist")]');if(!$details->length)throw new RuntimeException('Lists missing');
 foreach($details as $list){if($list->hasAttribute('open'))throw new RuntimeException('List expanded by default');$badge=$xp->query('.//summary/span[contains(@class,"badge")]',$list)->item(0);if($case!=='all'&&$badge->textContent!=='Company A')throw new RuntimeException('Other company list visible');}
 if($case==='all'&&!str_contains($html,'Other company copy'))throw new RuntimeException('Global list missing');
 foreach(['Duración (s)','Repeticiones','Efecto','Duración efecto (ms)','Modificar contenido','Ya incluido','Listas compatibles','Separador'] as $label)if(stripos($html,$label)===false)throw new RuntimeException('Missing editor UI: '.$label);
 if($case==='selected')$fixtureFile='_ui-playlists.html';
}
if($page==='devices'&&$case!=='all'&&str_contains($html,'Screen B'))throw new RuntimeException('Other company screen visible');
if(in_array($page,['media','devices','schedules','mode-schedules'],true)){
 $column=$page==='media'?3:1;$cells=$xp->query('//table/tbody/tr/td['.$column.']');$companies=[];
 foreach($cells as $cell)$companies[]=trim($cell->textContent);
 if(!$companies)throw new RuntimeException('Missing scoped fixtures: '.$page);
 if($case!=='all'&&count(array_filter($companies,static fn($name)=>$name!=='Company A')))throw new RuntimeException('Other company data visible: '.$page);
 if($case==='all'&&!in_array('Company B',$companies,true))throw new RuntimeException('Global scope lost company B: '.$page);
}
if($page==='media'&&$case==='selected')$fixtureFile='_ui-media.html';
if(isset($fixtureFile)){
 file_put_contents($root.'/.browser-session.json',json_encode(['session'=>session_id()]));chmod($root.'/.browser-session.json',0600);
 $zones=ds_media_zones();foreach($zones as &$z){$z['w']/=100;$z['h']/=100;}unset($z);
 $data='window.DSMediaFormats='.json_encode(['settings'=>ds_formats_current(),'zones'=>$zones],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.DSApplyMediaFormats(window.DSMediaFormats.settings);';
 $html=str_replace('<script src="'.url('media-formats-data.php').'"></script>','<script>'.$data.'</script>',$html);
 file_put_contents($root.'/public/'.$fixtureFile,str_replace('http://localhost/','http://127.0.0.1:8080/',$html));
}
$finished=true;echo 'PORTAL_SCOPE_PAGE_PASS: '.$page.' '.$case.PHP_EOL;
