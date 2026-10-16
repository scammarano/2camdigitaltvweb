<?php
declare(strict_types=1);
const ROOT = __DIR__.'/..';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
function cfg(): array {static $c=null;return $c ??= require ROOT.'/config/config.php';}
function db(): PDO {
    static $pdo=null;
    if ($pdo===null) {$c=cfg();$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
    ]);} return $pdo;
}
function url(string $path=''):string{return rtrim(cfg()['app_url'],'/').'/'.ltrim($path,'/');}
function h(?string $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect(string $path):never{header('Location: '.url($path));exit;}
function csrf():string{return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function check_csrf():void{if(!hash_equals(csrf(),(string)($_POST['_csrf']??''))){http_response_code(419);exit('Formulario vencido. Actualice la página.');}}
function dball(string $sql,array $params=[]):array{$s=db()->prepare($sql);$s->execute($params);return $s->fetchAll();}
function dbone(string $sql,array $params=[]):?array{$s=db()->prepare($sql);$s->execute($params);$r=$s->fetch();return $r===false?null:$r;}
function execsql(string $sql,array $params=[]):int{$s=db()->prepare($sql);$s->execute($params);return $s->rowCount();}
function user():?array{
    if (empty($_SESSION['user']['id']))return null;
    try{$u=dbone('SELECT id,tenant_id,name,email,role,role_id,is_active FROM users WHERE id=?',[(int)$_SESSION['user']['id']]);}
    catch(PDOException $e){$u=dbone('SELECT id,tenant_id,name,email,role,NULL AS role_id,1 AS is_active FROM users WHERE id=?',[(int)$_SESSION['user']['id']]);}
    if (!$u || !(int)$u['is_active']) {unset($_SESSION['user']);return null;}
    return $u;
}
function auth():array{$u=user();if(!$u)redirect('login.php');return $u;}
function is_super():bool{return (user()['role']??'')==='superadmin';}
function tenant():int{$u=auth();return is_super()?(int)($_SESSION['active_tenant']??$u['tenant_id']):(int)$u['tenant_id'];}
function active_tenant():void{if(tenant()<1){http_response_code(403);exit('No hay empresa activa');}}
function permission_names():array{return [
 'companies.manage'=>'Empresas', 'users.manage'=>'Gestionar usuarios', 'roles.manage'=>'Gestionar roles',
 'media.view'=>'Ver medios','media.edit'=>'Administrar medios','playlists.view'=>'Ver listas','playlists.edit'=>'Administrar listas',
 'devices.view'=>'Ver pantallas','devices.edit'=>'Administrar pantallas','schedules.view'=>'Ver programaciones','schedules.edit'=>'Administrar programaciones',
 'logs.view'=>'Consultar logs de empresa','updates.manage'=>'Actualizar plataforma'];}
function permissions():array{
    $u=user();if(!$u)return [];
    if($u['role']==='superadmin')return array_keys(permission_names());
    if(empty($u['role_id']))return ($u['role']==='admin')?array_values(array_diff(array_keys(permission_names()),['companies.manage','updates.manage'])):
        ['media.view','playlists.view','devices.view','schedules.view'];
    $r=dbone('SELECT permissions_json FROM roles WHERE id=? AND tenant_id=?',[(int)$u['role_id'],(int)$u['tenant_id']]);
    if(!$r)return [];
    $perms=json_decode($r['permissions_json'],true);
    return is_array($perms)?array_values(array_intersect($perms,array_keys(permission_names()))):[];
}
function can(string $p):bool{return in_array($p,permissions(),true);}
function require_permission(string $p):void{auth();if(!can($p)){http_response_code(403);exit('No tiene permisos para esta operación.');}}
function fault(Throwable $e,string $where):string{
    $id=strtoupper(substr(bin2hex(random_bytes(6)),0,12));
    log_event('error',['incidence'=>$id,'module'=>$where,'class'=>get_class($e),'message'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);
    return 'No se pudo completar la operación. Incidencia: '.$id;
}
function log_event(string $kind,array $data=[]):void{
    try{
        $dir=ROOT.'/storage/logs';if(!is_dir($dir))mkdir($dir,0700,true);
        $u=$_SESSION['user']??[];
        $entry=['at'=>gmdate('c'),'kind'=>$kind,'uid'=>$u['id']??null,'tenant'=>isset($u['id'])?($_SESSION['active_tenant']??$u['tenant_id']??null):null,'data'=>$data];
        file_put_contents($dir.'/events-'.gmdate('Y-m-d').'.jsonl',json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n",FILE_APPEND|LOCK_EX);
    }catch(Throwable $ignored){}
}

/** Authentication header normalized for Apache PHP/FastCGI setups. */
function device_bearer_token():?string {
 $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
 if($header==='' && function_exists('getallheaders')){foreach(getallheaders() as $key=>$value)if(strtolower((string)$key)==='authorization'){$header=(string)$value;break;}}
 return preg_match('/^Bearer ([a-f0-9]{64})$/D',$header,$m)?$m[1]:null;
}
