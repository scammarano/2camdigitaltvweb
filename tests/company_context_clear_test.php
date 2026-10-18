<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$case=$argv[1]??'valid';
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['active_tenant']=1;$_SESSION['csrf']='clear-fixture';
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/tenants.php';
$_POST=['clear_company'=>'1','_csrf'=>$case==='valid'?'clear-fixture':'forged'];
register_shutdown_function(function()use($case){
 $cleared=!isset($_SESSION['active_tenant']);if($cleared!==($case==='valid')){fwrite(STDERR,'Context clear/CSRF failed');exit(1);}
 echo 'COMPANY_CONTEXT_CLEAR_PASS: '.$case.PHP_EOL;
});
require $root.'/public/tenants.php';
