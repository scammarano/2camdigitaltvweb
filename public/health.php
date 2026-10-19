<?php
require __DIR__.'/_init.php';require_permission('updates.manage');
header('Content-Type: text/plain; charset=utf-8');
echo 'Digital Signage version: '.trim(file_get_contents(ROOT.'/version.txt'))."\n";
