<?php
/** Cumulative continuation of signed 0.4.31 with post-rotation APK catalog fixes. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.31.php';$previous($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.32')");
};
