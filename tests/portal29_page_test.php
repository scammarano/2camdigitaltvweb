<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['csrf']='scope-fixture';$_SERVER['REQUEST_METHOD']='GET';$page=$argv[1]??'';if(!in_array($page,['users','roles','applications','playlist-generator'],true))throw new RuntimeException('Invalid page');$_SERVER['SCRIPT_NAME']='/'.$page.'.php';$_GET=['app'=>'turnos','company'=>'1','orientation'=>'landscape','layout_type'=>'bottom'];
$finished=false;register_shutdown_function(function()use(&$finished){if(!$finished){fwrite(STDERR,'Portal29 page interrupted');exit(1);}});
ob_start();require $root.'/public/'.$page.'.php';$html=ob_get_clean();restore_exception_handler();if(!str_contains($html,'<!doctype html>')||str_contains($html,'No se pudo completar la operación'))throw new RuntimeException('Page failed: '.$page);
if($page==='users'&&!str_contains($html,'data-company="2"'))throw new RuntimeException('Other company roles missing for SUPERADMIN');
if($page==='applications'&&(!str_contains($html,'Sala 29')||str_contains($html,'Sala ajena 29')))throw new RuntimeException('Module scope failed');
file_put_contents(ROOT.'/public/_ui-'.$page.'29.html',str_replace('http://localhost/','http://127.0.0.1:8080/',$html));$finished=true;echo 'PORTAL29_PAGE_PASS: '.$page.PHP_EOL;
