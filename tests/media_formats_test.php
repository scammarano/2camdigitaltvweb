<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/device_config.php';require_once ROOT.'/app/media_geometry.php';
function ensure(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
ensure(count(ds_config_schema()['fields'])===43,'Per-screen geometry fields remain');
$c=ds_config_validate(['overlay_width_pct'=>77,'queue_height_pct'=>66]);ensure(!isset($c['overlay_width_pct'],$c['queue_height_pct']),'Legacy overrides survived migration');
$g=ds_media_geometry('landscape','bottom');ensure($g['width']===1920&&$g['height']===216,'Initial media geometry changed');
$f=ds_formats_current();$rev=ds_formats_update(['bottom_height_pct'=>20,'side_width_pct'=>35],$f['revision']);
ensure(ds_media_geometry('landscape','side')['width']===672,'Central width not used');
ensure(ds_media_geometry('portrait','side')['width']===378,'Portrait geometry not derived centrally');
$rejected=false;try{ds_formats_update(['bottom_height_pct'=>25,'side_width_pct'=>35],$rev);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'medio #');}
ensure($rejected,'Unknown/incompatible existing media did not block change');ensure(ds_formats_current()['revision']===$rev,'Rejected change mutated revision');
$rejected=false;try{ds_formats_update(['bottom_height_pct'=>20,'side_width_pct'=>36],$rev-1);}catch(RuntimeException $e){$rejected=true;}ensure($rejected,'Stale format revision accepted');
db()->beginTransaction();ds_formats_lock_geometry('landscape','side',672,1080);db()->rollBack();
$rejected=false;db()->beginTransaction();try{ds_formats_lock_geometry('landscape','side',1000,1080);}catch(RuntimeException $e){$rejected=true;}finally{db()->rollBack();}ensure($rejected,'Upload geometry bypass accepted');
echo "MEDIA_FORMATS_CENTRAL_PASS: shared geometry, legacy override migration, media protection, revision conflict, upload validation\n";
