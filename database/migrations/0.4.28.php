<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $m=require ROOT.'/database/migrations/0.4.27.php';$m($pdo);
 $first=!$pdo->query("SELECT 1 FROM schema_migrations WHERE id='0.4.28'")->fetchColumn();
 foreach(['media','playlists'] as $table){$s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,'is_shared']);if(!$s->fetchColumn())$pdo->exec("ALTER TABLE $table ADD is_shared TINYINT NOT NULL DEFAULT 0");}
 $s=$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='denied_permissions_json'");if(!$s->fetchColumn())$pdo->exec('ALTER TABLE users ADD denied_permissions_json TEXT NULL');
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_service_state(device_id BIGINT UNSIGNED PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,suspended TINYINT NOT NULL DEFAULT 0,revision BIGINT NOT NULL DEFAULT 0,reason VARCHAR(190) NOT NULL DEFAULT '',requested_by BIGINT UNSIGNED NULL,requested_at DATETIME NULL,reported_revision BIGINT NULL,reported_suspended TINYINT NULL,reported_at DATETIME NULL,FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,FOREIGN KEY(tenant_id) REFERENCES tenants(id),INDEX(tenant_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.28')");
 if($first){$s=$pdo->query("SELECT id,permissions_json FROM roles WHERE name='Administrador de empresa' AND is_system=1");foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$perms=json_decode($r['permissions_json'],true)?:[];if(in_array('devices.edit',$perms,true)&&!in_array('devices.suspend',$perms,true)){$perms[]='devices.suspend';$u=$pdo->prepare('UPDATE roles SET permissions_json=? WHERE id=?');$u->execute([json_encode($perms),$r['id']]);}}}
};
