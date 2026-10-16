<?php
/** Preflight database repair for legacy v0.1–0.3.3 installations. No destructive SQL. */
return static function(PDO $pdo): void {
    require_once ROOT.'/app/schema.php';
    // Ensure expected columns and role/scheduling tables even where an earlier
    // migration was marked successful before it actually completed.
    $actions = ensure_schema();
    foreach (['devices'=>['active_playlist_id','orientation','device_token_hash'],
              'playlists'=>['orientation','layout_type'],
              'media'=>['orientation','layout_type']] as $table=>$columns) {
        foreach ($columns as $column) {
            if (!column_exists($table,$column)) throw new RuntimeException("Falta la columna $table.$column después de reparación");
        }
    }
    foreach (['schedule_items','roles'] as $table) {
        if (!table_exists($table)) throw new RuntimeException("Falta la tabla $table después de reparación");
    }
    execsql('INSERT IGNORE INTO schema_migrations(id) VALUES(?)',['0.3.4']);
    log_event('schema_checked_v034',['changes'=>count($actions)]);
};
