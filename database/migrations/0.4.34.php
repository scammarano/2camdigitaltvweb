<?php
/** Device PIN orders now support pre-pairing and safe token rotation. */
declare(strict_types=1);
return static function(PDO $pdo):void{
 $previous=require ROOT.'/database/migrations/0.4.33.php';$previous($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.34')");
};
