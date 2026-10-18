<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $m=require ROOT.'/database/migrations/0.4.28.php';$m($pdo);
 $first=!$pdo->query("SELECT 1 FROM schema_migrations WHERE id='0.4.29'")->fetchColumn();
 foreach([['playlists','mode_mask','TINYINT UNSIGNED NOT NULL DEFAULT 15'],['media','separator_key','VARCHAR(100) NULL']] as [$table,$col,$def]){
  $s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$col]);if(!$s->fetchColumn())$pdo->exec("ALTER TABLE $table ADD $col $def");
 }
 $pdo->exec('ALTER TABLE media MODIFY tenant_id BIGINT UNSIGNED NULL');
 if(!$pdo->query("SHOW INDEX FROM media WHERE Key_name='uq_separator_key'")->fetch())$pdo->exec('ALTER TABLE media ADD UNIQUE KEY uq_separator_key(separator_key)');
 $pdo->exec("CREATE TABLE IF NOT EXISTS application_configs(tenant_id BIGINT UNSIGNED NOT NULL,app VARCHAR(12) NOT NULL,revision BIGINT NOT NULL DEFAULT 0,settings_json MEDIUMTEXT NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(tenant_id,app),FOREIGN KEY(tenant_id) REFERENCES tenants(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS application_entities(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,app VARCHAR(12) NOT NULL,kind VARCHAR(24) NOT NULL,name VARCHAR(160) NOT NULL,parent_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NULL,device_id BIGINT UNSIGNED NULL,config_json TEXT NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,FOREIGN KEY(tenant_id) REFERENCES tenants(id),FOREIGN KEY(parent_id) REFERENCES application_entities(id) ON DELETE RESTRICT,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE RESTRICT,UNIQUE KEY uq_application_screen(device_id),INDEX(tenant_id,app,kind)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS application_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,app VARCHAR(12) NOT NULL,label VARCHAR(120) NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,revoked_at DATETIME NULL,FOREIGN KEY(tenant_id) REFERENCES tenants(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 if($first){foreach($pdo->query("SELECT id,permissions_json FROM roles WHERE name='Administrador de empresa' AND is_system=1")->fetchAll(PDO::FETCH_ASSOC) as $r){$p=json_decode($r['permissions_json'],true)?:[];if(in_array('devices.edit',$p,true)){$p=array_values(array_unique(array_merge($p,['turnos.view','turnos.manage','sports.view','sports.manage','integrations.manage'])));$s=$pdo->prepare('UPDATE roles SET permissions_json=? WHERE id=?');$s->execute([json_encode($p),$r['id']]);}}}
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.29')");
 require_once ROOT.'/app/media_separator.php';ds_seed_separators();
};
