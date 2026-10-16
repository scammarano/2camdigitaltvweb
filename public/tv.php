<?php
/** TV-friendly alias without a redirect: old Fire OS Downloader compatibility. */
declare(strict_types=1);
$_GET['app']='android-tv';
$_GET['standalone']='1';
require __DIR__.'/app-downloads.php';
