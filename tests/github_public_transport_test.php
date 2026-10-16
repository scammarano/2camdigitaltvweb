<?php
/** Public metadata and asset download, deliberately WITHOUT Actions' ambient token. */
declare(strict_types=1);
require dirname(__DIR__).'/app/github_updater.php';
$repo='cli/cli';$release=gh_http_json('/releases/latest','',$repo);$asset=null;
foreach(($release['assets']??[]) as $candidate)if(preg_match('/_checksums\.txt$/D',(string)($candidate['name']??''))&&($candidate['size']??0)>100){$asset=$candidate;break;}
if(!$asset)throw new RuntimeException('Public GitHub CLI checksum asset unavailable');
foreach(gh_request_headers('') as $header)if(str_starts_with(strtolower($header),'authorization:'))throw new RuntimeException('Anonymous request leaked authorization');
$temp=tempnam(ROOT.'/storage','public-transport-');if($temp===false)throw new RuntimeException('Test tempfile unavailable');
try{
 gh_download_asset('',(int)$asset['id'],$temp,$repo);
 if(filesize($temp)!==(int)$asset['size'])throw new RuntimeException('Public asset download incomplete');
 $digest=(string)($asset['digest']??'');if(str_starts_with($digest,'sha256:')&&!hash_equals(substr($digest,7),hash_file('sha256',$temp)))throw new RuntimeException('Public asset checksum mismatch');
}finally{if(is_file($temp))unlink($temp);}
echo "GITHUB_PUBLIC_TRANSPORT_PASS: anonymous metadata and asset download, no Authorization header, exact size/checksum\n";
