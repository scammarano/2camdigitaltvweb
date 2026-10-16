<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/device_config.php';
require dirname(__DIR__).'/app/schema.php';
$pdo=db();$pdo->exec(file_get_contents(ROOT.'/database/schema.sql'));ensure_schema();
foreach(['0.4.0','0.4.1'] as $v){$m=require ROOT.'/database/migrations/'.$v.'.php';$m($pdo);}
$pdo->exec("INSERT INTO tenants(id,name) VALUES(1,'Company A'),(2,'Company B')");
$pdo->exec("INSERT INTO devices(id,tenant_id,name,orientation,mode) VALUES(1,1,'Screen A','landscape',1),(2,2,'Screen B','landscape',1)");
$pdo->exec("INSERT INTO playlists(id,tenant_id,name,orientation,layout_type) VALUES(1,1,'A bottom','landscape','bottom'),(2,2,'B bottom','landscape','bottom'),(3,1,'A side','landscape','side')");
$v=ds_config_defaults();$v['queue_playlist_bottom']=1;
ds_config_publish(1,1,$v,0);
if(ds_config_for_device(1,1)['revision']!==1)throw new RuntimeException('Publish failed');
foreach([2,3] as $bad){$v['queue_playlist_bottom']=$bad;$caught=false;try{ds_config_publish(1,1,$v,1);}catch(RuntimeException $e){$caught=true;}if(!$caught)throw new RuntimeException('Wrong tenant/shape accepted');}
if(ds_config_for_device(1,1)['revision']!==1)throw new RuntimeException('Rejected config changed revision');
$m=require ROOT.'/database/migrations/0.4.19.php';$m($pdo);$m($pdo);
echo 'TENANT_SHAPE_PASS: publish, cross-company reject, geometry reject, migration idempotency'.PHP_EOL;
