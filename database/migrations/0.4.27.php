<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
    $previous=require ROOT.'/database/migrations/0.4.26.php';$previous($pdo);
    $s=$pdo->query("SELECT DATA_TYPE,NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlist_items' AND COLUMN_NAME='duration_seconds'");$c=$s->fetch(PDO::FETCH_ASSOC);
    if(!$c)throw new RuntimeException('Falta duration_seconds');
    if(strtolower($c['DATA_TYPE'])!=='decimal'||(int)$c['NUMERIC_SCALE']!==1)$pdo->exec('ALTER TABLE playlist_items MODIFY duration_seconds DECIMAL(11,1) NOT NULL DEFAULT 10.0');
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.27')");
};
