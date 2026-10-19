<?php
/** Remote administrative PIN: tenant+device binding, encryption, expiry and ACK. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/device_pin.php';
function pin31_check(bool $ok,string $description):void{if(!$ok)throw new RuntimeException($description);}
$m=require ROOT.'/database/migrations/0.4.31.php';$m(db());$m(db());
$token=hash('sha256','device-pin-31-'.bin2hex(random_bytes(8)));
db()->prepare("INSERT INTO devices(tenant_id,name,orientation,mode,device_token_hash) VALUES(2,'PIN31 fixture','landscape',1,?)")->execute([$token]);
$id=(int)db()->lastInsertId();
$pin='91726483';
$rev=ds_pin_issue(2,$id,$pin,100);
pin31_check($rev===1,'First revision wrong');
$row=dbone('SELECT ciphertext,token_binding,status FROM device_pin_commands WHERE device_id=?',[$id]);
pin31_check($row['status']==='pending'&&!str_contains((string)$row['ciphertext'],$pin),'PIN stored as plaintext');
pin31_check(ds_pin_pending(2,$id,$token)['pin']===$pin,'Wrong PIN in device pull');
pin31_check(ds_pin_pending(1,$id,$token)['pending']===false,'Tenant leak');
pin31_check(ds_pin_pending(2,$id,str_repeat('f',64))['pending']===false,'Unpaired token leak');
pin31_check(!ds_pin_ack(2,$id,$token,999),'Unrecognized ACK was accepted');
pin31_check(ds_pin_ack(2,$id,$token,$rev),'Valid ACK rejected');
pin31_check(ds_pin_ack(2,$id,$token,$rev),'Retry ACK not idempotent');
pin31_check(ds_pin_pending(2,$id,$token)['pending']===false,'PIN replay after ACK');
pin31_check(dbone('SELECT ciphertext FROM device_pin_commands WHERE device_id=?',[$id])['ciphertext']===null,'Ciphertext retained after ACK');
db()->prepare("UPDATE device_pin_commands SET requested_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 SECOND) WHERE device_id=?")->execute([$id]);
$rev2=ds_pin_issue(2,$id,'64927518',100);
pin31_check($rev2===2,'Revision not monotonic');
db()->prepare("UPDATE device_pin_commands SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE device_id=?")->execute([$id]);
pin31_check(ds_pin_pending(2,$id,$token)['pending']===false,'Expired PIN delivered');
pin31_check(!ds_pin_ack(2,$id,$token,$rev2),'Expired PIN accepted');
db()->prepare('DELETE FROM device_pin_commands WHERE device_id=?')->execute([$id]);
db()->prepare('DELETE FROM devices WHERE id=?')->execute([$id]);
echo "DEVICE_PIN31_PASS tenant binding encryption ACK expiry and no replay\n";
