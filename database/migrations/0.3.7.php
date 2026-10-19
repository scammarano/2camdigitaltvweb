<?php
/** Idempotent transition metadata migration; existing playlists/media unchanged. */
return static function(PDO $pdo): void {
    require_once ROOT.'/app/schema.php';
    $actions=[];
    add_column('playlists','transition_effect',"VARCHAR(24) NOT NULL DEFAULT 'fade'",$actions);
    add_column('playlists','transition_ms','SMALLINT UNSIGNED NOT NULL DEFAULT 600',$actions);
    add_column('playlist_items','transition_effect',"VARCHAR(24) NOT NULL DEFAULT 'inherit'",$actions);
    add_column('playlist_items','transition_ms','SMALLINT UNSIGNED NULL DEFAULT NULL',$actions);
    foreach(['playlists'=>['transition_effect','transition_ms'],'playlist_items'=>['transition_effect','transition_ms']] as $table=>$names){
      foreach($names as $name)if(!column_exists($table,$name))throw new RuntimeException('Migración incompleta: '.$table.'.'.$name);
    }
    execsql('INSERT IGNORE INTO schema_migrations(id) VALUES(?)',['0.3.7']);
    log_event('schema_checked_v037',['changes'=>count($actions)]);
};
