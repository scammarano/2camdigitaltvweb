<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/apk_repository.php';
try{$results=apk_repositories_sync();echo json_encode(['at'=>gmdate('c'),'results'=>$results],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;foreach($results as $r)if(!$r['ok'])exit(1);}
catch(Throwable $e){fwrite(STDERR,"No se pudo sincronizar APK; revisa los registros del portal.\n");exit(1);}
