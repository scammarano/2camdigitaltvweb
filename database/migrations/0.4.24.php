<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.23.php';$previous($pdo);
 // Only seed the untouched, disabled placeholder. Never overwrite an administrator's app identity.
 $s=$pdo->prepare("UPDATE apk_repository_sources SET android_package=?,certificate_sha256=?,token_mode='public',revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE platform='sports-control' AND repository='scammarano/2cam-sports' AND android_package='' AND certificate_sha256='' AND enabled=0");
 $s->execute(['services.twocam.sportscontrol','7bd01e3921783cbbad9e09214d2857a75208a71c56215c219217c101829af7e0']);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.24')");
};
