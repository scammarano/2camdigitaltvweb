<?php
/** Runtime scheduling uses the screen/site time zone (no global PHP timezone assumption). */
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/playback.php';
function mode_blocks_for_device(int $tenantId,int $deviceId):array {
  return dball('SELECT id,mode,days_mask,start_time,end_time,valid_from,valid_until,priority,enabled FROM device_mode_blocks WHERE tenant_id=? AND device_id=? AND enabled=1 ORDER BY priority DESC,id DESC',[$tenantId,$deviceId]);
}
function mode_block_active(array $block,DateTimeImmutable $now):bool {
  // As with content schedules, v0.4 accepts same-day windows only.
  return playback_schedule_matches($block,$now);
}
function resolve_mode(array $device,array $blocks,DateTimeImmutable $now,int $enabledMask=15):int {
  foreach($blocks as $block)if(mode_block_active($block,$now)) {
    $mode=(int)$block['mode'];if($mode>=1&&$mode<=4 && ($enabledMask&(1<<($mode-1))))return $mode;
  }
  $fallback=(int)$device['mode'];return $fallback>=1&&$fallback<=4&&($enabledMask&(1<<($fallback-1)))?$fallback:0;
}
function runtime_opts(int $tenantId,int $deviceId):array {
  $r=dbone('SELECT overlay_interval_sec,overlay_duration_sec,modes_enabled FROM device_runtime_options WHERE tenant_id=? AND device_id=?',[$tenantId,$deviceId]);
  return ['overlay_interval_sec'=>max(60,min(86400,(int)($r['overlay_interval_sec']??900))),
          'overlay_duration_sec'=>max(3,min(120,(int)($r['overlay_duration_sec']??12))),
          'modes_enabled'=>15]; // Deprecated flags cannot silently suppress a selected mode.
}
function live_panel(int $tenantId,int $deviceId):array {
  $r=dbone('SELECT widget_type,payload_json,updated_at FROM device_live_panels WHERE tenant_id=? AND device_id=?',[$tenantId,$deviceId]);
  if(!$r)return ['widget_type'=>'queue','payload'=>['title'=>'Sala de espera','ticket'=>'--','counter'=>'--'],'updated_at'=>null];
  $data=json_decode((string)$r['payload_json'],true);
  return ['widget_type'=>$r['widget_type'],'payload'=>is_array($data)?$data:[],'updated_at'=>$r['updated_at']];
}
