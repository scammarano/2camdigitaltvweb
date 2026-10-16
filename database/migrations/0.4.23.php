<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.22.php';$previous($pdo);
 $column=$pdo->query("SHOW COLUMNS FROM apk_repository_sources LIKE 'token_mode'")->fetch(PDO::FETCH_ASSOC);
 if(!$column||!str_contains($column['Type'],"'public'"))$pdo->exec("ALTER TABLE apk_repository_sources MODIFY token_mode ENUM('shared','custom','public') NOT NULL DEFAULT 'shared'");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.23')");
};
