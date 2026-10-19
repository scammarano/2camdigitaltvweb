<?php
declare(strict_types=1);
session_start();$_SESSION['user']=['id'=>100,'tenant_id'=>1];
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/devices.php';$_GET=['company'=>'2'];
ob_start();require dirname(__DIR__).'/public/devices.php';$html=ob_get_clean();restore_exception_handler();
if(!str_contains($html,'ds-devices-scroll')||!str_contains($html,'data-devices-step="1"')||!str_contains($html,'Generar código'))throw new RuntimeException('Accessible devices controls missing');
file_put_contents(ROOT.'/public/_ui-devices31.html',str_replace('http://localhost/','http://127.0.0.1:8080/',$html));
echo "DEVICES31_PAGE_PASS\n";
