<?php
declare(strict_types=1);
require_once __DIR__.'/device_config.php';
function ds_assign_readable_playlist(int $tid,int $did,int $pid,int $mode,int $revision,int $uid):int{
 require_permission('devices.edit');if(!is_super()&&tenant()!==$tid)throw new RuntimeException('Empresa inválida');
 $d=dbone('SELECT orientation FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]);$p=ds_content_record('playlists',$pid,$tid);
 if(!$d||!$p||$mode<1||$mode>4||!((int)$p['mode_mask']&(1<<($mode-1)))||!in_array($p['orientation'],['any',$d['orientation']],true))throw new RuntimeException('Lista no disponible para esta pantalla o modo');
 $layout=$p['layout_type'];if($mode===1&&$layout!=='fullscreen')throw new RuntimeException('Cartelera requiere pantalla completa');
 $current=ds_config_for_device($did,$tid);if($current['revision']!==$revision)throw new RuntimeException('La pantalla cambió. Recarga antes de asignar');
 $c=$current['settings'];$c['enabled_modes']|=1<<($mode-1);$c['primary_mode']=$mode;
 if($mode===1)$c['signage_playlist_id']=$pid;else{$prefix=[2=>'overlay',3=>'queue',4=>'scoreboard'][$mode];$c[$prefix.'_layout']=$layout;$c[$prefix.'_playlist_'.$layout]=$pid;}
 return ds_config_publish($did,$tid,$c,$revision,$uid);
}
