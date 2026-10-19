<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_permission('media.view');$r=is_super()?dbone('SELECT * FROM media WHERE id=?',[(int)($_GET['id']??0)]):ds_readable_media((int)($_GET['id']??0),tenant());if(!$r){http_response_code(404);exit;}
$file=ROOT.'/storage/media/'.(int)$r['tenant_id'].'/'.$r['filename'];if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: '.$r['mime']);header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="media"');header('Content-Length: '.filesize($file));readfile($file);
