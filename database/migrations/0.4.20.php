<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $pdo->exec('CREATE TABLE IF NOT EXISTS media_format_settings(id TINYINT UNSIGNED PRIMARY KEY,revision BIGINT UNSIGNED NOT NULL DEFAULT 1,bottom_height_pct TINYINT UNSIGNED NOT NULL DEFAULT 20,side_width_pct TINYINT UNSIGNED NOT NULL DEFAULT 40,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $pdo->exec('INSERT IGNORE INTO media_format_settings(id,revision) VALUES(1,1)');
 $old=require ROOT.'/database/migrations/0.4.19.php';$old($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.20')");
};
