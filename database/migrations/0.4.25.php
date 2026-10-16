<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.24.php';$previous($pdo);
 // Seed only the original disabled placeholder; never replace administrator-entered identity.
 $s=$pdo->prepare("UPDATE apk_repository_sources SET android_package=?,certificate_sha256=?,token_mode='public',revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE platform='turnos-control' AND repository='scammarano/2cam-turnos' AND android_package='' AND certificate_sha256='' AND enabled=0");
 $s->execute(['services.twocam.turnos','431172fe64dbeb2536f3f08fba96650c5ca6db3fe4cd1f098b216af30638f69c']);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.25')");
};
