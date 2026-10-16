<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/device_config.php';
 foreach(['device_config_desired','device_config_profiles','device_config_backups'] as $table){
  foreach($pdo->query("SELECT ".($table==='device_config_desired'?'device_id':'id')." row_id,config_json FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row){
   $settings=ds_config_validate(json_decode($row['config_json'],true,32,JSON_THROW_ON_ERROR));
   $q=$pdo->prepare("UPDATE `$table` SET config_json=? WHERE ".($table==='device_config_desired'?'device_id':'id')."=?");$q->execute([ds_config_json($settings),$row['row_id']]);
  }
 }
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.19')");
};
