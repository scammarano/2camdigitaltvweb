<?php
/** P6: cumulative update, preserving all tenant/device configuration and PIN history. */
declare(strict_types=1);
return static function(PDO $pdo):void {
    $previous=require ROOT.'/database/migrations/0.4.33.php';
    $previous($pdo);
    // PIN staging reuses device_pin_commands from 0.4.31; no secrets are migrated.
    // Historical mode masks are normalized at read/save without rewriting valid rows.
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.34')");
};
