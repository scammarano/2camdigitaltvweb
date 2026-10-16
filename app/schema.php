<?php
/** Idempotent database repair from v0.1/v0.2; never drops existing user data. */
require_once __DIR__.'/bootstrap.php';
function column_exists(string $table,string $column):bool{
    return (bool)dbone('SELECT 1 AS yes FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]);
}
function table_exists(string $table):bool{
    return (bool)dbone('SELECT 1 AS yes FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
}
function add_column(string $table,string $column,string $definition,array &$actions):void{
    // All SQL names/definitions are hardcoded by this application; do not accept user input.
    if(!column_exists($table,$column)){db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");$actions[]="Agregada $table.$column";}
}
function ensure_schema():array{
    $actions=[];
    foreach(['tenants','users','media','playlists','playlist_items','devices','schedules'] as $t){if(!table_exists($t))throw new RuntimeException("Falta la tabla base: $t. No ejecutar reparación sin respaldo y diagnóstico.");}
    $add=[
        'media'=>['orientation'=>"ENUM('landscape','portrait','any') NOT NULL DEFAULT 'any'",'layout_type'=>"ENUM('fullscreen','bottom','side','half_horizontal','half_vertical') NOT NULL DEFAULT 'fullscreen'",'width_px'=>'INT NULL','height_px'=>'INT NULL'],
        'playlists'=>['orientation'=>"ENUM('any','landscape','portrait') NOT NULL DEFAULT 'any'",'layout_type'=>"ENUM('fullscreen','bottom','side','half_horizontal','half_vertical') NOT NULL DEFAULT 'fullscreen'",'transition_effect'=>"VARCHAR(24) NOT NULL DEFAULT 'fade'",'transition_ms'=>'SMALLINT UNSIGNED NOT NULL DEFAULT 600'],
        'devices'=>['orientation'=>"ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape'",'mode'=>'TINYINT UNSIGNED NOT NULL DEFAULT 1','width_px'=>'INT NULL','height_px'=>'INT NULL','location_label'=>'VARCHAR(190) NULL','pairing_code_hash'=>'CHAR(64) NULL','pairing_expires'=>'DATETIME NULL','device_token_hash'=>'CHAR(64) NULL','active_playlist_id'=>'BIGINT UNSIGNED NULL','site_id'=>'BIGINT UNSIGNED NULL','last_seen'=>'DATETIME NULL'],
        'users'=>['role_id'=>'BIGINT UNSIGNED NULL','is_active'=>'TINYINT(1) NOT NULL DEFAULT 1'],
        'schedules'=>['priority'=>'INT NOT NULL DEFAULT 0','valid_from'=>'DATE NULL','valid_until'=>'DATE NULL']
    ];
    foreach($add as $table=>$fields)foreach($fields as $column=>$definition)add_column($table,$column,$definition,$actions);
    if(table_exists('playlist_items')){
       add_column('playlist_items','transition_effect',"VARCHAR(24) NOT NULL DEFAULT 'inherit'",$actions);
       add_column('playlist_items','transition_ms','SMALLINT UNSIGNED NULL DEFAULT NULL',$actions);
    }
    db()->exec('CREATE TABLE IF NOT EXISTS schedule_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,schedule_id BIGINT UNSIGNED NOT NULL,playlist_id BIGINT UNSIGNED NOT NULL,position INT NOT NULL DEFAULT 0,INDEX idx_schedule(tenant_id,schedule_id,position),FOREIGN KEY(schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,FOREIGN KEY(playlist_id) REFERENCES playlists(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    db()->exec('CREATE TABLE IF NOT EXISTS roles(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,permissions_json TEXT NOT NULL,is_system TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_role_tenant_name(tenant_id,name),FOREIGN KEY(tenant_id) REFERENCES tenants(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations(id VARCHAR(80) PRIMARY KEY,applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    db()->exec('INSERT INTO schedule_items(tenant_id,schedule_id,playlist_id,position) SELECT s.tenant_id,s.id,s.playlist_id,0 FROM schedules s WHERE NOT EXISTS(SELECT 1 FROM schedule_items i WHERE i.schedule_id=s.id)');
    foreach(dball('SELECT id FROM tenants') as $t){
        $id=(int)$t['id'];
        $defaults=[
            'Administrador de empresa'=>['users.manage','roles.manage','media.view','media.edit','playlists.view','playlists.edit','devices.view','devices.edit','schedules.view','schedules.edit','logs.view'],
            'Editor de contenidos'=>['media.view','media.edit','playlists.view','playlists.edit','devices.view','schedules.view'],
            'Programador'=>['media.view','playlists.view','devices.view','schedules.view','schedules.edit'],
            'Operador'=>['media.view','playlists.view','devices.view','schedules.view'],
            'Solo lectura'=>['media.view','playlists.view','devices.view','schedules.view']
        ];
        foreach($defaults as $name=>$perms)execsql('INSERT IGNORE INTO roles(tenant_id,name,permissions_json,is_system) VALUES(?,?,?,1)',[$id,$name,json_encode($perms)]);
        $adm=dbone('SELECT id FROM roles WHERE tenant_id=? AND name=?',[$id,'Administrador de empresa']);
        $op=dbone('SELECT id FROM roles WHERE tenant_id=? AND name=?',[$id,'Operador']);
        execsql("UPDATE users SET role_id=? WHERE tenant_id=? AND role='admin' AND role_id IS NULL",[(int)$adm['id'],$id]);
        execsql("UPDATE users SET role_id=? WHERE tenant_id=? AND role='operator' AND role_id IS NULL",[(int)$op['id'],$id]);
    }
    // Critical postcondition. Only mark migration after the schema actually matches required columns.
    foreach($add as $table=>$fields)foreach($fields as $col=>$unused)if(!column_exists($table,$col))throw new RuntimeException("La columna $table.$col sigue ausente");
    foreach(['roles','schedule_items'] as $t)if(!table_exists($t))throw new RuntimeException("Tabla $t ausente");
    execsql('INSERT IGNORE INTO schema_migrations(id) VALUES(?)',['0.3.0']);
    return $actions;
}
function schema_check():array{
    $expected=['media'=>['orientation','layout_type','width_px','height_px'],'playlists'=>['orientation','layout_type','transition_effect','transition_ms'],'playlist_items'=>['transition_effect','transition_ms'],'devices'=>['orientation','location_label','pairing_code_hash','device_token_hash'],'users'=>['role_id','is_active']];
    $missing=[];foreach($expected as $table=>$cols)foreach($cols as $column)if(!column_exists($table,$column))$missing[]="$table.$column";
    foreach(['roles','schedule_items'] as $table)if(!table_exists($table))$missing[]="$table (tabla)";
    return $missing;
}
