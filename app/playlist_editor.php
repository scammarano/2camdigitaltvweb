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
            $seconds=ds_playlist_integer($r['duration']??'',1,86400,'Duración');
            $repeat=ds_playlist_integer($r['repeat']??'',1,30,'Repeticiones');
            $effect=$r['transition_effect']??'';if(!is_string($effect)||($effect!=='inherit'&&!isset(transitions()[$effect])))throw new RuntimeException('Efecto inválido');
            $ms=($r['transition_ms']??'')===''?null:ds_playlist_integer($r['transition_ms'],0,3000,'Duración del efecto');
            execsql('UPDATE playlist_items SET duration_seconds=?,repeat_count=?,transition_effect=?,transition_ms=? WHERE tenant_id=? AND playlist_id=? AND id=?',[$seconds,$repeat,$effect,$ms,$tid,$pid,(int)$i['id']]);
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
