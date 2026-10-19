<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/device_config.php';
$c=ds_config_defaults();
$c['overlay_layout']='side';$c['overlay_playlist_side']=82;
$c['queue_layout']='bottom';$c['queue_playlist_bottom']=501;
$c['scoreboard_layout']='fullscreen';$c['scoreboard_playlist_fullscreen']=502;
foreach([2,3,4] as $mode) {
    if(ds_mode_layout($c,$mode)!=='side')throw new RuntimeException('Overlay ad geometry diverged');
    if(ds_mode_playlist($c,$mode)!==82)throw new RuntimeException('Overlay ad playlist diverged');
}
if(ds_mode_playlist($c,1)!==$c['signage_playlist_id'])throw new RuntimeException('Cartelera inherited overlay');
ob_start();ds_config_form($c,1,1);$html=ob_get_clean();
foreach(['queue_playlist_bottom','scoreboard_playlist_fullscreen','queue_layout','scoreboard_layout'] as $key){
 if(!str_contains($html,'type="hidden" name="'.$key.'"'))throw new RuntimeException('Legacy field was not hidden: '.$key);
}
if(!str_contains($html,'Publicidad compartida'))throw new RuntimeException('No explanation of Overlay inheritance');
echo "OVERLAY_SHARED31_PASS common ad source with independent Turnos/Sports settings\n";
