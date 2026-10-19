<?php
/** Public downloader diagnostics without exposing filesystem paths or credentials. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$file=ROOT.'/public/tv.apk';
$ok=is_file($file)&&is_readable($file)&&filesize($file)>100000;
try{$published=(int)(dbone("SELECT COUNT(*) AS n FROM app_releases WHERE platform='android-tv' AND status='published'")['n']??0);}catch(Throwable $e){$published=-1;}
echo json_encode(['portal_version'=>trim((string)file_get_contents(ROOT.'/version.txt')),
  'apk_static_exists'=>$ok,'apk_static_bytes'=>$ok?filesize($file):0,
  'apk_sha256'=>$ok?hash_file('sha256',$file):null,
  'published_releases'=>$published,'direct_url'=>url('tv.apk'),
  'result'=>$ok&&$published>0?'ready':'check_installation'],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
