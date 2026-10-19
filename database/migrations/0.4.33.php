<?php
/** Cumulative package plus scope-bound trusted legacy public keys for Sports/Turnos. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.32.php';$previous($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.33')");
};
