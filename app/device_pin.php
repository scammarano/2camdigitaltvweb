<?php
/** One-shot device PIN reset: encrypted at rest, device authenticated, never in common settings. */
declare(strict_types=1);
function ds_pin_key():string {
    $k=(string)(cfg()['app_key']??'');
    if(strlen($k)<24)throw new RuntimeException('La clave del servidor no está disponible');
    return hash('sha256','2cam-device-admin-pin-v1:'.$k,true);
}
function ds_pin_issue(int $tenant,int $device,string $pin,int $user):int {
    if(!preg_match('/^[0-9]{6,12}$/D',$pin))throw new RuntimeException('El PIN requiere entre 6 y 12 dígitos');
    $key=ds_pin_key();
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($pin,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'2cam-pin-v1');
    if(!is_string($cipher)||strlen($tag)!==16)throw new RuntimeException('No se pudo proteger el PIN');
    $db=db();$db->beginTransaction();
    try {
        $s=$db->prepare('SELECT device_token_hash FROM devices WHERE id=? AND tenant_id=? FOR UPDATE');$s->execute([$device,$tenant]);
        $bound=$s->fetchColumn();
        // An unpaired screen has no bearer token yet. Reserve this order for the
        // tenant/device identity; pairing will atomically bind its fresh token.
        if(!is_string($bound)||!preg_match('/^[a-f0-9]{64}$/D',$bound))$bound=str_repeat('0',64);
        $s=$db->prepare('SELECT revision,requested_at FROM device_pin_commands WHERE tenant_id=? AND device_id=? FOR UPDATE');$s->execute([$tenant,$device]);$prior=$s->fetch(PDO::FETCH_ASSOC);
        if($prior && strtotime((string)$prior['requested_at'])>time()-20)throw new RuntimeException('Espera 20 segundos antes de solicitar otro cambio de PIN');
        $revision=(int)($prior['revision']??0)+1;
        $s=$db->prepare("INSERT INTO device_pin_commands(device_id,tenant_id,revision,token_binding,ciphertext,iv,auth_tag,status,requested_by,requested_at,expires_at,applied_at) VALUES(?,?,?,?,?,?,?,'pending',?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),NULL) ON DUPLICATE KEY UPDATE revision=VALUES(revision),tenant_id=VALUES(tenant_id),token_binding=VALUES(token_binding),ciphertext=VALUES(ciphertext),iv=VALUES(iv),auth_tag=VALUES(auth_tag),status='pending',requested_by=VALUES(requested_by),requested_at=UTC_TIMESTAMP(),expires_at=VALUES(expires_at),applied_at=NULL");
        $s->execute([$device,$tenant,$revision,$bound,$cipher,$iv,$tag,$user]);
        $db->commit();
        log_event('device_pin_requested',['device_id'=>$device,'tenant_id'=>$tenant,'revision'=>$revision]);
        return $revision;
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
function ds_pin_pending(int $tenant,int $device,string $tokenHash):array {
    $row=dbone("SELECT revision,ciphertext,iv,auth_tag FROM device_pin_commands WHERE device_id=? AND tenant_id=? AND token_binding=? AND status='pending' AND expires_at>UTC_TIMESTAMP()",[$device,$tenant,$tokenHash]);
    if(!$row)return ['pending'=>false];
    $pin=openssl_decrypt((string)$row['ciphertext'],'aes-256-gcm',ds_pin_key(),OPENSSL_RAW_DATA,(string)$row['iv'],(string)$row['auth_tag'],'2cam-pin-v1');
    if(!is_string($pin)||!preg_match('/^[0-9]{6,12}$/D',$pin))throw new RuntimeException('No se pudo recuperar orden de PIN');
    return ['pending'=>true,'revision'=>(int)$row['revision'],'pin'=>$pin];
}
function ds_pin_ack(int $tenant,int $device,string $tokenHash,int $revision):bool {
    if($revision<1)return false;
    $s=db()->prepare("UPDATE device_pin_commands SET status='applied',ciphertext=NULL,iv=NULL,auth_tag=NULL,applied_at=UTC_TIMESTAMP() WHERE tenant_id=? AND device_id=? AND token_binding=? AND revision=? AND status='pending' AND expires_at>UTC_TIMESTAMP()");
    $s->execute([$tenant,$device,$tokenHash,$revision]);
    if($s->rowCount()===1) {
        log_event('device_pin_applied',['tenant_id'=>$tenant,'device_id'=>$device,'revision'=>$revision]);
        return true;
    }
    // An ACK retry after a lost response is idempotent.
    return (bool)dbone("SELECT 1 FROM device_pin_commands WHERE tenant_id=? AND device_id=? AND token_binding=? AND revision=? AND status='applied'",[$tenant,$device,$tokenHash,$revision]);
}

/** Must be called inside the successful pairing transaction for this exact screen. */
function ds_pin_bind_after_pair(int $tenant,int $device,string $tokenHash):void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$tokenHash))throw new InvalidArgumentException('Token hash inválido');
    $s=db()->prepare("UPDATE device_pin_commands SET token_binding=? WHERE tenant_id=? AND device_id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()");
    $s->execute([$tokenHash,$tenant,$device]);
    if($s->rowCount()>0)log_event('device_pin_rebound',['tenant_id'=>$tenant,'device_id'=>$device]);
}
