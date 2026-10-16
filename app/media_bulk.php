<?php
/** Transactional, tenant-isolated mass actions for Digital Signage media assets. */
declare(strict_types=1);
require_once __DIR__.'/domain.php';
require_once __DIR__.'/media_geometry.php';

/** @return list<int> */
function ds_bulk_ids($raw):array {
    if(!is_array($raw) || count($raw)<1 || count($raw)>100)
        throw new RuntimeException('Selecciona entre 1 y 100 medios visibles para la operación masiva.');
    $ids=[];
    foreach($raw as $v){
        if(!is_string($v) || !ctype_digit($v) || (int)$v<1 || (int)$v>2147483647)
            throw new RuntimeException('La selección contiene un identificador no válido.');
        $ids[]=(int)$v;
    }
    if(count(array_unique($ids))!==count($ids))throw new RuntimeException('La selección contiene registros repetidos.');
    sort($ids,SORT_NUMERIC);
    return $ids;
}
/** Return every record locked by id; validate original owners, filter and action privileges. */
function ds_bulk_locked_records(array $ids,?int $visibleScope):array {
    $params=$ids;
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $records=dball('SELECT * FROM media WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE',$params);
    if(count($records)!==count($ids))throw new RuntimeException('Uno o varios medios fueron eliminados o modificados. Actualiza la biblioteca y selecciona nuevamente.');
    foreach($records as $r){
        if(!is_super() && (int)$r['tenant_id']!==tenant())
            throw new RuntimeException('No puedes modificar medios de otra empresa.');
        if($visibleScope!==null && (int)$r['tenant_id']!==$visibleScope)
            throw new RuntimeException('La selección contiene medios fuera de la empresa filtrada. Vuelve al listado.');
    }
    return $records;
}
function ds_bulk_source_file(array $m):string {
    $stored=(string)$m['filename'];
    if(!preg_match('/^[a-f0-9]{40}$/D',$stored))throw new RuntimeException('El medio #'.$m['id'].' utiliza almacenamiento antiguo. Debe revisarse antes de moverlo o duplicarlo.');
    $path=ROOT.'/storage/media/'.(int)$m['tenant_id'].'/'.$stored;
    if(!is_file($path) || is_link($path) || !is_readable($path))throw new RuntimeException('No se encontró el archivo de «'.$m['name'].'».');
    return $path;
}
function ds_bulk_target(int $destination):void {
    if($destination<=0 || !dbone('SELECT id FROM tenants WHERE id=?',[$destination]))
        throw new RuntimeException('Selecciona una empresa de destino válida.');
    if(!is_super() && $destination!==tenant())
        throw new RuntimeException('Solo SUPERADMIN puede copiar o mover medios a otra empresa.');
}
/** All-or-nothing operation: filesystem compensating actions if SQL rolls back. */
function ds_bulk_media_action(string $action,array $ids,?int $visibleScope,int $destination,string $orientation,string $layout):string {
    require_permission('media.edit');
    if(!in_array($action,['bulk_copy','bulk_move','bulk_classify'],true))
        throw new RuntimeException('Acción masiva no reconocida.');
    if(in_array($action,['bulk_copy','bulk_move'],true))ds_bulk_target($destination);
    if($action==='bulk_classify' && $orientation==='keep' && $layout==='keep')
        throw new RuntimeException('Indica al menos una clasificación para modificar.');
    if($orientation!=='keep' && !in_array($orientation,['landscape','portrait','any'],true))
        throw new RuntimeException('Orientación de destino inválida.');
    if($layout!=='keep' && !isset(layouts()[$layout]))
        throw new RuntimeException('Zona de presentación inválida.');

    $newFiles=[];$moves=[];$counter=0;$same=0;
    db()->beginTransaction();
    try{
        ds_formats_current(true);
        $rows=ds_bulk_locked_records($ids,$visibleScope);
        $totalBytes=0;
        foreach($rows as $m){
            $old=(int)$m['tenant_id'];$id=(int)$m['id'];
            if($action==='bulk_move' && $old!==$destination){
                assert_unlinked_before_transfer('media',$id,$old);
                if(dbone('SELECT id FROM media WHERE tenant_id=? AND filename=? AND id<>? LIMIT 1',[$old,(string)$m['filename'],$id]))
                    throw new RuntimeException('«'.$m['name'].'» comparte un archivo físico con otro registro. No puede moverse hasta corregir esa relación.');
            }
            if($action==='bulk_classify'){
                $newOri=$orientation==='keep'?(string)$m['orientation']:$orientation;
                $newLayout=$layout==='keep'?(string)$m['layout_type']:$layout;
                if($newOri===$m['orientation'] && $newLayout===$m['layout_type']){$same++;continue;}
                if(str_starts_with((string)$m['mime'],'image/')){
                    if($newOri==='any')throw new RuntimeException('Para cambiar la clasificación de «'.$m['name'].'», elige una pantalla horizontal o vertical; «Ambas» no define medidas únicas.');
                    $geometry=ds_media_geometry($newOri,$newLayout);
                    if(!$geometry)throw new RuntimeException('Formato de destino incompatible.');
                    $source=ds_bulk_source_file($m);
                    $dims=@getimagesize($source);
                    if(!$dims || !ds_media_geometry_check((int)$dims[0],(int)$dims[1],$geometry))
                        throw new RuntimeException(ds_media_geometry_error((string)$m['name'],(int)($dims[0]??0),(int)($dims[1]??0),$geometry));
                }
                $links=dball("SELECT p.name,p.orientation,p.layout_type FROM playlist_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.kind='media' AND i.media_id=?",[$old,$id]);
                foreach($links as $p)if(!compatible($p,['orientation'=>$newOri,'layout_type'=>$newLayout]))
                    throw new RuntimeException('«'.$m['name'].'» está en la lista «'.$p['name'].'», incompatible con el nuevo formato. Desvincúlalo primero.');
                execsql('UPDATE media SET orientation=?,layout_type=? WHERE id=? AND tenant_id=?',[$newOri,$newLayout,$id,$old]);
                $counter++;continue;
            }
            if($action==='bulk_move' && $old===$destination){$same++;continue;}
            $source=ds_bulk_source_file($m);
            $totalBytes+=(int)$m['size_bytes'];
            if($totalBytes>1024*1024*1024)
                throw new RuntimeException('El lote excede 1 GB; divide la operación para proteger el espacio del servidor.');
            $targetDir=ROOT.'/storage/media/'.$destination;
            if(!is_dir($targetDir) && !mkdir($targetDir,0700,true) && !is_dir($targetDir))
                throw new RuntimeException('No se puede crear el almacenamiento de la empresa destino.');
            $newStored=bin2hex(random_bytes(20));$destinationPath=$targetDir.'/'.$newStored;
            if(is_file($destinationPath) || is_link($destinationPath))throw new RuntimeException('Colisión improbable de almacenamiento; reintenta la acción.');
            if($action==='bulk_copy'){
                if(!copy($source,$destinationPath))throw new RuntimeException('No se pudo copiar «'.$m['name'].'».');
                $newFiles[]=$destinationPath;
                @chmod($destinationPath,0600);
                if(filesize($destinationPath)!==(int)$m['size_bytes'] || !hash_equals((string)$m['sha256'],hash_file('sha256',$destinationPath)))
                    throw new RuntimeException('La copia de «'.$m['name'].'» no superó la verificación de integridad.');
                execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(?,?,?,?,?,?,?,?,?,?)',
                  [$destination,(string)$m['name'],$newStored,(string)$m['mime'],(int)$m['size_bytes'],(string)$m['sha256'],(string)$m['orientation'],(string)$m['layout_type'],$m['width_px'],$m['height_px']]);
            }else{
                if(!rename($source,$destinationPath))throw new RuntimeException('No se pudo mover «'.$m['name'].'».');
                $moves[]=['from'=>$source,'to'=>$destinationPath];
                execsql('UPDATE media SET tenant_id=?,filename=? WHERE id=? AND tenant_id=?',[$destination,$newStored,$id,$old]);
            }
            $counter++;
        }
        if($counter===0)throw new RuntimeException('No hay registros que cambiar: los medios ya tienen esa empresa o clasificación.');
        db()->commit();
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        foreach(array_reverse($moves) as $move){if(is_file($move['to']) && !rename($move['to'],$move['from']))log_event('media_bulk_rollback_restore_failed',['file'=>basename($move['to'])]);}
        foreach($newFiles as $path){if(is_file($path) && !@unlink($path))log_event('media_bulk_rollback_file_remove_failed',['file'=>basename($path)]);}
        if($e instanceof PDOException && (string)$e->getCode()==='23000')
            throw new RuntimeException('No se completó el lote porque existe una relación o duplicidad en la base de datos. No se aplicaron cambios.');
        throw $e;
    }
    log_event('media_bulk_success',['action'=>$action,'count'=>$counter,'skipped'=>$same,'destination_company'=>$destination>0?$destination:null,'selected_ids'=>$ids]);
    $text=match($action){'bulk_copy'=>'duplicados','bulk_move'=>'movidos','bulk_classify'=>'reclasificados'};
    return $counter.' medio(s) '.$text.' correctamente. '.($same>0?$same.' sin cambios. ':'').'La selección se ha restablecido.';
}
