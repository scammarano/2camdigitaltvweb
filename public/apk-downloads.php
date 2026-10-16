<?php
/** Legacy URL: one canonical APK catalogue. No duplicate UI or duplicate admin menu. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$qs=['app'=>'android-tv'];
$channel=(string)($_GET['channel']??'');
if(in_array($channel,['stable','beta','debug'],true))$qs['channel']=$channel;
if((string)($_GET['standalone']??'')==='1')$qs['standalone']='1';
header('Cache-Control: no-store');
header('Location: '.url('app-downloads.php').'?'.http_build_query($qs,'','&',PHP_QUERY_RFC3986),true,302);
exit;
