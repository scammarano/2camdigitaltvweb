<?php
/** Device PIN orders now support pre-pairing and safe token rotation. */
declare(strict_types=1);
return static function(PDO $pdo):void{
 $previous=require ROOT.'/database/migrations/0.4.33.php';$previous($pdo);
 // Drop obsolete hidden mode flags on previously configured screens.
 $pdo->exec("UPDATE device_runtime_options SET modes_enabled=15 WHERE modes_enabled<>15");
 $pdo->exec("UPDATE device_config_desired SET config_json=JSON_SET(config_json,'$.enabled_modes',15) WHERE JSON_VALID(config_json) AND COALESCE(JSON_EXTRACT(config_json,'$.enabled_modes'),15)<>15");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.34')");
};
