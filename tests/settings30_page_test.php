<?php
declare(strict_types=1);
session_start();$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/device-manager.php';$_GET=['company'=>'2'];
ob_start();require dirname(__DIR__).'/public/device-manager.php';$html=ob_get_clean();restore_exception_handler();
if(!str_contains($html,'data-settings-tabs')||str_contains($html,'No se pudo completar la operación'))throw new RuntimeException('Config page failed');
file_put_contents(ROOT.'/public/_ui-settings30.html',str_replace('http://localhost/','http://127.0.0.1:8080/',$html));
echo "SETTINGS30_PAGE_PASS\n";
