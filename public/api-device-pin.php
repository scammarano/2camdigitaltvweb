<?php
/** Authenticated one-shot administrative PIN instructions. Never cache or log PIN. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/device_pin.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
function dp_out(array $data,int $status=200):never{
    http_response_code($status);
    echo json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try{
    $token=device_bearer_token();
    if($token===null)dp_out(['error'=>'Dispositivo no autorizado'],401);
    $hash=hash('sha256',$token);
    $row=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[$hash]);
    if(!$row)dp_out(['error'=>'Dispositivo no autorizado'],401);
    $did=(int)$row['id'];$tid=(int)$row['tenant_id'];
    if(($_GET['op']??'')==='pending'){
        if($_SERVER['REQUEST_METHOD']!=='GET')dp_out(['error'=>'Método inválido'],405);
        dp_out(ds_pin_pending($tid,$did,$hash));
    }
    if(($_GET['op']??'')==='applied'){
        if($_SERVER['REQUEST_METHOD']!=='POST')dp_out(['error'=>'Método inválido'],405);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>2000)dp_out(['error'=>'Payload excesivo'],413);
        $data=json_decode(file_get_contents('php://input',false,null,0,2001),true);
        $revision=$data['revision']??null;
        if(!is_int($revision)||$revision<1)dp_out(['error'=>'Revisión inválida'],400);
        $ok=ds_pin_ack($tid,$did,$hash,$revision);
        dp_out(['applied'=>$ok],$ok?200:409);
    }
    dp_out(['error'=>'Operación no disponible'],404);
} catch(Throwable $e){
    $incident=strtoupper(bin2hex(random_bytes(6)));
    log_event('device_pin_error',['incident'=>$incident,'class'=>get_class($e)]);
    dp_out(['error'=>'Error de PIN administrativo','incident'=>$incident],500);
}
