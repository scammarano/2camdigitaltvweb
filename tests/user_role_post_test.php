<?php
declare(strict_types=1);
session_start();$root=dirname(__DIR__);$c=require $root.'/config/config.php';$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$case=$argv[1]??'create';$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['csrf']='access29';$_SERVER['REQUEST_METHOD']='POST';$_POST=['_csrf'=>'access29','tenant_id'=>'2'];$_GET=[];$page='users';
if($case==='create'){$pdo->exec("INSERT INTO roles(tenant_id,name,permissions_json) VALUES(2,'Delete test29','[\"turnos.view\"]')");$rid=(int)$pdo->lastInsertId();$_POST+=['action'=>'create','name'=>'User29','email'=>'user29@example.invalid','password'=>'isolated-password-29','role_id'=>(string)$rid,'is_active'=>'1'];}
elseif($case==='delete'){$id=(int)$pdo->query("SELECT id FROM users WHERE email='user29@example.invalid'")->fetchColumn();if(!$id)throw new RuntimeException('User not created');$_POST+=['action'=>'delete','id'=>(string)$id];}
elseif($case==='self'){$_POST['tenant_id']='1';$_POST+=['action'=>'delete','id'=>'100'];}
elseif($case==='role'){$page='roles';$id=(int)$pdo->query("SELECT id FROM roles WHERE tenant_id=2 AND name='Delete test29'")->fetchColumn();$_POST+=['action'=>'delete','id'=>(string)$id];}
else throw new RuntimeException('Invalid case');
$_SERVER['SCRIPT_NAME']='/'.$page.'.php';$finished=false;register_shutdown_function(function()use(&$finished){if(!$finished){fwrite(STDERR,'User/role POST interrupted');exit(1);}});
ob_start();require $root.'/public/'.$page.'.php';$html=ob_get_clean();restore_exception_handler();
if($case==='create'){if((int)$pdo->query("SELECT tenant_id FROM users WHERE email='user29@example.invalid'")->fetchColumn()!==2)throw new RuntimeException('Other company user creation failed');}
elseif($case==='delete'){if($pdo->query("SELECT id FROM users WHERE email='user29@example.invalid'")->fetchColumn())throw new RuntimeException('User not deleted');}
elseif($case==='role'){if($pdo->query("SELECT id FROM roles WHERE tenant_id=2 AND name='Delete test29'")->fetchColumn())throw new RuntimeException('Other company role not deleted');}
elseif(!str_contains($html,'No puede eliminar su propia cuenta')||!$pdo->query('SELECT id FROM users WHERE id=100')->fetchColumn())throw new RuntimeException('Self deletion not blocked');
if($case!=='self'&&str_contains($html,'alert alert-danger'))throw new RuntimeException('Unexpected rejection: '.strip_tags($html));$finished=true;echo 'USER_ROLE_POST_PASS: '.$case.PHP_EOL;
