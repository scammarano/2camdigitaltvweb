<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/device_config.php';
 foreach(['device_config_desired','device_config_profiles','device_config_backups'] as $table){
  foreach($pdo->query("SELECT ".($table==='device_config_desired'?'device_id':'id')." row_id,config_json FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row){
   $before=json_decode($row['config_json'],true,32,JSON_THROW_ON_ERROR);
   $settings=ds_config_validate($before);
   if($table==='device_config_desired'){
    $pdo->prepare('UPDATE devices SET mode=? WHERE id=?')->execute([$settings['primary_mode'],$row['row_id']]);
    $pdo->prepare('UPDATE device_runtime_options SET modes_enabled=? WHERE device_id=?')->execute([$settings['enabled_modes'],$row['row_id']]);
    if(($before['smart_app']??'')==='scoreboard')$pdo->prepare('UPDATE device_mode_blocks SET mode=4 WHERE device_id=? AND mode=3')->execute([$row['row_id']]);
   }
   $q=$pdo->prepare("UPDATE `$table` SET config_json=? WHERE ".($table==='device_config_desired'?'device_id':'id')."=?");$q->execute([ds_config_json($settings),$row['row_id']]);
  }
 }
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.19')");
};
