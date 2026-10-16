<?php
declare(strict_types=1);
/** Read-only thumbnail. Permissions are checked for each tenant even when SUPERADMIN views all. */
require __DIR__.'/_init.php';
require_once ROOT.'/app/domain.php';
require_permission('media.view');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(400);exit;}
$row=is_super()
 ?dbone('SELECT tenant_id,filename,mime FROM media WHERE id=?',[$id])
 :dbone('SELECT tenant_id,filename,mime FROM media WHERE id=? AND tenant_id=?',[$id,tenant()]);
if(!$row){http_response_code(404);exit;}
$mime=(string)$row['mime'];
if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)){http_response_code(415);exit;}
$filename=(string)$row['filename'];
if($filename!==basename($filename)||!preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/D',$filename) || $filename==='.' || $filename==='..'){
 http_response_code(404);exit;
}
$root=realpath(ROOT.'/storage/media/'.(int)$row['tenant_id']);
$file=$root?realpath($root.DIRECTORY_SEPARATOR.$filename):false;
if(!$root||!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)){
 http_response_code(404);exit;
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'");
// The original is available to authenticated users from any authorized tenant too.
if((string)($_GET['full']??'')==='1'){
 header('Content-Type: '.$mime);
 header('Content-Disposition: inline; filename="media"');
 header('Content-Length: '.filesize($file));
 readfile($file);
 exit;
}
$info=@getimagesize($file);
if(!$info){http_response_code(415);exit;}
// Downsample with GD where available. Avoid allocating huge bitmaps on shared hosting.
$w=(int)$info[0];$h=(int)$info[1];$sampled=false;
if($w>0&&$h>0&&$w*$h<=16000000&&function_exists('imagecreatetruecolor')){
 $read=match($mime){
  'image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng',
  'image/webp'=>'imagecreatefromwebp','image/gif'=>'imagecreatefromgif',default=>null
 };
 if($read!==null&&function_exists($read)&&function_exists('imagejpeg')){
  $src=@$read($file);
  if($src!==false){
   $scale=min(1.0,190/max(1,$w),135/max(1,$h));
   $tw=max(1,(int)round($w*$scale));$th=max(1,(int)round($h*$scale));
   $dst=imagecreatetruecolor($tw,$th);
   if($dst!==false){
    $bg=imagecolorallocate($dst,255,255,255);imagefill($dst,0,0,$bg);
    if(imagecopyresampled($dst,$src,0,0,0,0,$tw,$th,$w,$h)){
     header('Content-Type: image/jpeg');
     imagejpeg($dst,null,78);$sampled=true;
    }
    imagedestroy($dst);
   }
   imagedestroy($src);
  }
 }
}
if(!$sampled){
 header('Content-Type: '.$mime);
 header('Content-Length: '.filesize($file));
 readfile($file);
}
