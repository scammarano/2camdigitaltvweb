<?php
declare(strict_types=1);
$root=dirname(__DIR__);$lock=$root.'/storage/installed.lock';
if(is_file($lock)){http_response_code(403);exit('Instalación bloqueada.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  foreach(['host','db','user','admin_email','admin_pass','company','app_url'] as $k)if(trim($_POST[$k]??'')==='')throw new RuntimeException('Falta: '.$k);
  if(strlen($_POST['admin_pass'])<12)throw new RuntimeException('Contraseña: mínimo 12 caracteres');
  $c=['app_url'=>rtrim($_POST['app_url'],'/'),'app_key'=>bin2hex(random_bytes(32)),'db_host'=>$_POST['host'],'db_name'=>$_POST['db'],'db_user'=>$_POST['user'],'db_pass'=>$_POST['pass']??'','timezone'=>'UTC'];
  $pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $schema=file_get_contents($root.'/database/schema.sql');
  foreach(array_filter(array_map('trim',explode(';',$schema))) as $statement)$pdo->exec($statement);
  $migration=file_get_contents($root.'/database/migrations/0.2.0.sql');foreach(array_filter(array_map('trim',explode(';',$migration))) as $statement)$pdo->exec($statement);$pdo->exec("INSERT INTO schema_migrations(id) VALUES('0.2.0')");
  $pdo->beginTransaction();
  $s=$pdo->prepare('INSERT INTO tenants(name) VALUES(?)');$s->execute([trim($_POST['company'])]);$tid=$pdo->lastInsertId();
  $s=$pdo->prepare("INSERT INTO users(tenant_id,name,email,password_hash,role) VALUES(?,?,?,?,'superadmin')");
  $s->execute([$tid,'Administrador',strtolower(trim($_POST['admin_email'])),password_hash($_POST['admin_pass'],PASSWORD_DEFAULT)]);
  $pdo->commit();
  if(!is_dir($root.'/config'))mkdir($root.'/config',0700,true);
  $file=$root.'/config/config.php';
  if(file_put_contents($file,'<?php return '.var_export($c,true).';')===false)throw new RuntimeException('No se pudo escribir config');
  if(!is_dir($root.'/storage'))mkdir($root.'/storage',0700,true);
  require_once $root.'/app/schema.php';ensure_schema();
  if(file_put_contents($lock,gmdate('c'))===false)throw new RuntimeException('No se pudo bloquear instalador');
  header('Location: '.rtrim($c['app_url'],'/').'/login.php');exit;
 }catch(Throwable $e){$error='No se pudo completar la instalación: '.$e->getMessage();}
}
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalador Digital Signage</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/signage.css?v=0.3.2"><body class="ds-body"><main class="container py-5" style="max-width:800px"><div class="mb-4"><div class="ds-eyebrow">DIGITAL SIGNAGE · CONFIGURACIÓN</div><h1 class="fw-bold mt-2">Instalación del portal</h1><p class="text-muted">Configura la base de datos y el primer administrador. El instalador quedará bloqueado al finalizar.</p></div>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="post" class="card card-body row g-3">
<?php foreach(['host'=>'Servidor MySQL','db'=>'Base de datos existente','user'=>'Usuario MySQL','pass'=>'Contraseña MySQL','company'=>'Empresa inicial','admin_email'=>'Correo SUPERADMIN','admin_pass'=>'Clave SUPERADMIN (12+ caracteres)','app_url'=>'URL pública completa hasta /public'] as $k=>$label):?>
<div class="col-md-6"><label class="form-label"><?=htmlspecialchars($label)?></label><input class="form-control" name="<?=$k?>" value="<?=htmlspecialchars($_POST[$k]??($k==='host'?'localhost':''))?>" <?=$k==='pass'||$k==='admin_pass'?'type="password"':''?> <?=$k==='pass'?'':'required'?>></div>
<?php endforeach;?><div class="col-12"><button class="btn btn-primary">Instalar</button></div></form></main></body></html>
