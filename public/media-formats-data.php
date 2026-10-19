<?php
require dirname(__DIR__).'/app/bootstrap.php';auth();require_once ROOT.'/app/media_geometry.php';
header('Content-Type: application/javascript; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$zones=ds_media_zones();foreach($zones as &$z){$z['w']/=100;$z['h']/=100;}unset($z);
echo 'window.DSMediaFormats='.json_encode(['settings'=>ds_formats_current(),'zones'=>$zones],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).';window.DSApplyMediaFormats(window.DSMediaFormats.settings);';
