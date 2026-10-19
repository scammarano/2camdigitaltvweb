<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';

function ds_playlist_stamp(array $items):string {
    $state=[];
    foreach($items as $i)$state[]=array_map(static fn($k)=>(string)($i[$k]??''),['id','position','kind','media_id','child_playlist_id','duration_seconds','repeat_count','transition_effect','transition_ms']);
    return hash_hmac('sha256',json_encode($state,JSON_THROW_ON_ERROR),csrf());
}
function ds_playlist_integer($value,int $min,int $max,string $label):int {
    if(!is_scalar($value)||!preg_match('/^[0-9]+$/D',(string)$value)||(float)$value<$min||(float)$value>$max)throw new RuntimeException($label.': indica un entero entre '.$min.' y '.$max);
    return (int)$value;
}
function ds_playlist_duration($value):float {
    if(!is_scalar($value))throw new RuntimeException('Duración inválida');
    $text=str_replace(',','.',(string)$value);
    if(!preg_match('/^[0-9]+(?:\.[0-9])?$/D',$text)||(float)$text<0.1||(float)$text>86400)throw new RuntimeException('Duración: indica entre 0,1 y 86400 segundos, en pasos de 0,1');
    return (float)$text;
}
/** A single transaction for the complete grid; reject stale and forged rows. */
function ds_playlist_save_grid(int $tid,int $pid,array $rows,string $stamp):void {
    require_permission('playlists.edit');
    if(!is_super()&&$tid!==tenant())throw new RuntimeException('Lista fuera de tu empresa');
    db()->beginTransaction();
    try{
        if(!dbone('SELECT id FROM playlists WHERE tenant_id=? AND id=? FOR UPDATE',[$tid,$pid]))throw new RuntimeException('Lista inválida');
        $items=dball('SELECT * FROM playlist_items WHERE tenant_id=? AND playlist_id=? ORDER BY position,id FOR UPDATE',[$tid,$pid]);
        if(!hash_equals(ds_playlist_stamp($items),$stamp))throw new RuntimeException('La lista cambió desde que la abriste. Recarga antes de modificarla; no se guardó ningún renglón.');
        if(count($rows)!==count($items))throw new RuntimeException('Los renglones no coinciden con la lista. Recarga e inténtalo de nuevo.');
        foreach($items as $i){
            $r=$rows[(int)$i['id']]??null;if(!is_array($r))throw new RuntimeException('Renglón inválido');
            $seconds=ds_playlist_duration($r['duration']??'');
            $repeat=ds_playlist_integer($r['repeat']??'',1,30,'Repeticiones');
            $effect=$r['transition_effect']??'';if(!is_string($effect)||($effect!=='inherit'&&!isset(transitions()[$effect])))throw new RuntimeException('Efecto inválido');
            $ms=($r['transition_ms']??'')===''?null:ds_playlist_integer($r['transition_ms'],0,3000,'Duración del efecto');
            execsql('UPDATE playlist_items SET duration_seconds=?,repeat_count=?,transition_effect=?,transition_ms=? WHERE tenant_id=? AND playlist_id=? AND id=?',[$seconds,$repeat,$effect,$ms,$tid,$pid,(int)$i['id']]);
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

/** Duplicate selected entries immediately after their originals, or remove only selected rows. */
function ds_playlist_rows_action(int $tid,int $pid,array $selection,string $action,string $stamp):int {
    require_permission('playlists.edit');
    if(!is_super()&&$tid!==tenant())throw new RuntimeException('Lista fuera de tu empresa');
    if(!in_array($action,['duplicate','delete'],true)||!$selection||count($selection)>2000)throw new RuntimeException('Selecciona entre 1 y 2000 renglones');
    $ids=[];foreach($selection as $id)$ids[]=ds_playlist_integer($id,1,PHP_INT_MAX,'Renglón');
    $ids=array_values(array_unique($ids));db()->beginTransaction();
    try{
        if(!dbone('SELECT id FROM playlists WHERE tenant_id=? AND id=? FOR UPDATE',[$tid,$pid]))throw new RuntimeException('Lista inválida');
        $items=dball('SELECT * FROM playlist_items WHERE tenant_id=? AND playlist_id=? ORDER BY position,id FOR UPDATE',[$tid,$pid]);
        if(!hash_equals(ds_playlist_stamp($items),$stamp))throw new RuntimeException('La lista cambió. Recarga antes de aplicar la selección.');
        $owned=array_map('intval',array_column($items,'id'));if(array_diff($ids,$owned))throw new RuntimeException('La selección contiene renglones de otra lista o empresa');
        $pos=0;
        foreach($items as $i){
            $selected=in_array((int)$i['id'],$ids,true);
            if($selected&&$action==='delete'){execsql('DELETE FROM playlist_items WHERE tenant_id=? AND playlist_id=? AND id=?',[$tid,$pid,(int)$i['id']]);continue;}
            $pos+=10;execsql('UPDATE playlist_items SET position=? WHERE tenant_id=? AND playlist_id=? AND id=?',[$pos,$tid,$pid,(int)$i['id']]);
            if($selected&&$action==='duplicate'){
                $pos+=10;execsql('INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,child_playlist_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(?,?,?,?,?,?,?,?,?,?)',[$tid,$pid,$i['kind'],$i['media_id'],$i['child_playlist_id'],$pos,$i['duration_seconds'],$i['repeat_count'],$i['transition_effect'],$i['transition_ms']]);
            }
        }
        db()->commit();return count($ids);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
