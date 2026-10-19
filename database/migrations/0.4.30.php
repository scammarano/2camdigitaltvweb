<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
 $previous=require ROOT.'/database/migrations/0.4.29.php';$previous($pdo);
 $s=$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='device_health' AND COLUMN_NAME='local_ip'");
 if(!$s->fetchColumn())$pdo->exec('ALTER TABLE device_health ADD local_ip VARCHAR(45) NULL');
 // Mode eligibility follows geometry; lists no longer need manual mode checkboxes.
 $pdo->exec("UPDATE playlists SET mode_mask=CASE WHEN layout_type='fullscreen' THEN 15 ELSE 14 END");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.30')");
};
