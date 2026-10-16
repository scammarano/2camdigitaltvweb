<?php
/** 2Cam Digital Signage: strict tenant-scoped destructive actions. No implicit cascades. */
declare(strict_types=1);
require_once __DIR__.'/domain.php';

function ds_cleanup_record(string $entity,int $id,int $tid,bool $lock=false):array {
    $tables=['media'=>'media','playlists'=>'playlists','schedules'=>'schedules','mode_blocks'=>'device_mode_blocks','devices'=>'devices'];
    if(!isset($tables[$entity]) || $id<1 || $tid<1)throw new RuntimeException('Registro de limpieza inválido.');
    if(!is_super() && $tid!==tenant())throw new RuntimeException('No puedes eliminar registros de otra empresa.');
    $sql='SELECT * FROM '.$tables[$entity].' WHERE id=? AND tenant_id=?'.($lock?' FOR UPDATE':'');
    $row=dbone($sql,[$id,$tid]);
    if(!$row)throw new RuntimeException('El registro ya no existe o no pertenece a esta empresa. Actualiza el listado.');
    return $row;
}

/** Return specific, user-actionable blockers without mutating other records. */
function ds_cleanup_dependencies(string $entity,int $id,int $tid,array $row):array {
    $issues=[];
    if($entity==='media'){
        $links=dball("SELECT DISTINCT p.name FROM playlist_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.kind='media' AND i.media_id=? ORDER BY p.name LIMIT 6",[$tid,$id]);
        if($links)$issues[]='Se utiliza en estas listas: '.implode(', ',array_column($links,'name')).'. Primero quita el medio de ellas.';
        if(dbone('SELECT id FROM media WHERE tenant_id=? AND filename=? AND id<>? LIMIT 1',[$tid,$row['filename'],$id]))
            $issues[]='El archivo físico está compartido con otro registro: se bloquea la eliminación para protegerlo.';
    }elseif($entity==='playlists'){
        $parents=dball("SELECT DISTINCT p.name FROM playlist_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.kind='playlist' AND i.child_playlist_id=? LIMIT 6",[$tid,$id]);
        if($parents)$issues[]='Está incluida en las listas: '.implode(', ',array_column($parents,'name')).'. Elimina esas inclusiones primero.';
        $assigned=dball('SELECT name FROM devices WHERE tenant_id=? AND active_playlist_id=? ORDER BY name LIMIT 6',[$tid,$id]);
        if($assigned)$issues[]='Lista predeterminada de pantallas: '.implode(', ',array_column($assigned,'name')).'. Desasígnala primero.';
        $schedules=dball('SELECT DISTINCT s.id FROM schedules s WHERE s.tenant_id=? AND s.playlist_id=? LIMIT 6',[$tid,$id]);
        $sequences=dball('SELECT DISTINCT schedule_id FROM schedule_items WHERE tenant_id=? AND playlist_id=? LIMIT 6',[$tid,$id]);
        if($schedules || $sequences)$issues[]='Forma parte de una o más programaciones. Edita o elimina esos bloques antes de borrar la lista.';
    }elseif($entity==='devices'){
        if(!empty($row['device_token_hash']))$issues[]='La pantalla sigue emparejada. Pulsa «Desvincular» y confirma antes de eliminarla.';
        $schedules=dbone('SELECT id FROM schedules WHERE tenant_id=? AND device_id=? LIMIT 1',[$tid,$id]);
        if($schedules)$issues[]='La pantalla tiene programaciones. Elimínalas o reasígnalas primero.';
        $modeBlocks=dbone('SELECT id FROM device_mode_blocks WHERE tenant_id=? AND device_id=? LIMIT 1',[$tid,$id]);
        if($modeBlocks)$issues[]='La pantalla tiene bloques de programación de modos. Elimínalos primero.';
    }
    return $issues;
}

function ds_cleanup_delete(string $entity,int $id,int $tid):string {
    $permissions=['media'=>'media.edit','playlists'=>'playlists.edit','schedules'=>'schedules.edit',
                  'mode_blocks'=>'schedules.edit','devices'=>'devices.edit'];
    if(!isset($permissions[$entity]))throw new RuntimeException('Tipo de eliminación no admitido.');
    require_permission($permissions[$entity]);
    $original='';$staged='';$moved=false;$committed=false;$row=null;
    db()->beginTransaction();
    try{
        $row=ds_cleanup_record($entity,$id,$tid,true);
        $issues=ds_cleanup_dependencies($entity,$id,$tid,$row);
        if($issues)throw new RuntimeException('No se puede eliminar: '.implode(' ',$issues));
        if($entity==='media'){
            // Uploaded media are random, non-user-controlled 40-character filenames.
            $filename=(string)$row['filename'];
            if(!preg_match('/^[a-f0-9]{40}$/D',$filename))throw new RuntimeException('Nombre de archivo antiguo/no seguro. Solicita revisión antes de eliminar.');
            $dir=ROOT.'/storage/media/'.$tid;
            $original=$dir.'/'.$filename;
            if(is_link($original))throw new RuntimeException('Archivo físico inválido: enlace simbólico.');
            if(is_file($original)){
                $staged=$dir.'/.deleting-'.$id.'-'.bin2hex(random_bytes(10));
                if(!rename($original,$staged))throw new RuntimeException('No se pudo preparar el borrado físico. Verifica permisos de almacenamiento.');
                $moved=true;
            }
        }
        $table=['media'=>'media','playlists'=>'playlists','schedules'=>'schedules',
                'mode_blocks'=>'device_mode_blocks','devices'=>'devices'][$entity];
        if(execsql('DELETE FROM '.$table.' WHERE id=? AND tenant_id=?',[$id,$tid])!==1)
            throw new RuntimeException('El registro fue modificado mientras se eliminaba. Actualiza y reintenta.');
        db()->commit();$committed=true;
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        if($moved && is_file($staged) && !rename($staged,$original))
            log_event('cleanup_restore_failed',['entity'=>$entity,'id'=>$id,'company'=>$tid]);
        if($e instanceof PDOException && (string)$e->getCode()==='23000')
            throw new RuntimeException('El registro tiene relaciones activas. Desvincúlalo de listas, programaciones o pantallas antes de borrarlo.');
        throw $e;
    }
    if($committed && $moved && is_file($staged) && !@unlink($staged)){
        log_event('cleanup_file_remove_failed',['entity'=>$entity,'id'=>$id,'company'=>$tid,'file_hash'=>$row['sha256']??null]);
        return 'Registro eliminado, pero el archivo quedó pendiente de limpieza física. Revisa los logs de la plataforma.';
    }
    log_event('cleanup_deleted',['entity'=>$entity,'id'=>$id,'company'=>$tid,'name'=>$row['name']??'','physical_file_removed'=>$moved]);
    return match($entity){
        'media'=>'Medio y archivo eliminados del servidor. Los reproductores actualizarán su caché en una sincronización posterior.',
        'playlists'=>'Lista eliminada. Los archivos de sus medios se conservaron.',
        'devices'=>'Pantalla eliminada. Se eliminaron también sus configuraciones e historiales asociados.',
        'mode_blocks'=>'Bloque de modo eliminado.',
        default=>'Programación eliminada.'
    };
}

/** Stop a linked device before deleting its record; existing schedules remain untouched. */
function ds_cleanup_unpair_device(int $id,int $tid):string {
    require_permission('devices.edit');
    db()->beginTransaction();
    try{
        $row=ds_cleanup_record('devices',$id,$tid,true);
        execsql('UPDATE devices SET device_token_hash=NULL,pairing_code_hash=NULL,pairing_expires=NULL,last_seen=NULL WHERE id=? AND tenant_id=?',[$id,$tid]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    log_event('device_unpaired_by_admin',['company'=>$tid,'id'=>$id]);
    return 'Pantalla desvinculada. El reproductor deberá emparejarse nuevamente para conectarse.';
}

/** Bootstrap modal with a POST/CSRF protected form, no fixed sidebar editor. */
function ds_cleanup_modal(string $entity,int $id,int $tid,string $name,string $hint=''):string {
    $modal='remove-'.$entity.'-'.$id;
    $label=match($entity){'media'=>'medio','playlists'=>'lista','devices'=>'pantalla','mode_blocks'=>'bloque de modo',default=>'programación'};
    ob_start();
    echo '<div class="modal fade" id="'.h($modal).'" tabindex="-1" aria-labelledby="'.h($modal).'-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">';
    echo '<div class="modal-header"><h5 class="modal-title" id="'.h($modal).'-title">Eliminar '.h($label).'</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
    echo '<p>¿Eliminar definitivamente <strong>'.h($name).'</strong>?</p><p class="text-danger fw-semibold">Esta acción no se puede deshacer. Si el registro está en uso, la operación será bloqueada.</p>';
    if($hint!=='')echo '<p class="small text-muted">'.h($hint).'</p>';
    form_open();echo '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="tenant_id" value="'.$tid.'">';
    echo '<label for="confirm-'.$entity.'-'.$id.'" class="form-label">Escribe ELIMINAR para confirmar</label>';
    echo '<input id="confirm-'.$entity.'-'.$id.'" name="confirm_delete" class="form-control" required pattern="ELIMINAR" autocomplete="off" placeholder="ELIMINAR">';
    echo '<div class="modal-footer px-0 pb-0 mt-3"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Eliminar definitivamente</button></div></form></div></div></div></div>';
    return (string)ob_get_clean();
}
function ds_cleanup_confirm():void {
    if((string)($_POST['confirm_delete']??'')!=='ELIMINAR')throw new RuntimeException('Para eliminar, escribe ELIMINAR exactamente en la confirmación.');
}
