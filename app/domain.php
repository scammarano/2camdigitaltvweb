<?php
require_once __DIR__.'/bootstrap.php';
function must_admin():void{require_permission('users.manage');}
function post_tenant():int{
    $t=tenant();if(is_super() && isset($_POST['tenant_id'])){
        $id=(int)$_POST['tenant_id'];if(!dbone('SELECT id FROM tenants WHERE id=?',[$id]))throw new RuntimeException('Empresa inválida');return $id;
    }return $t;
}
function company_select(int $selected=0,string $attributes=''):string{
    $chosen=$selected>0?$selected:tenant();
    // Tenant always resolved in the server, regardless of client-submitted values.
    if(!is_super()){
        $owned=dbone('SELECT name FROM tenants WHERE id=?',[tenant()]);
        return '<label class="form-label">Empresa</label><input class="form-control mb-2" type="text" value="'.h($owned['name']??'Empresa asignada').'" readonly aria-label="Empresa asignada"><input type="hidden" name="tenant_id" value="'.tenant().'">';
    }
    $out='<label class="form-label">Empresa</label><select required name="tenant_id" class="form-select mb-2" '.$attributes.'>';
    foreach(dball('SELECT id,name FROM tenants ORDER BY name') as $company)$out.='<option value="'.(int)$company['id'].'"'.($chosen===(int)$company['id']?' selected':'').'>'.h($company['name']).'</option>';
    return $out.'</select>';
}
/** Always look up the original owner on the server: editing may change the destination tenant. */
function owned_edit_record(string $table,int $id):array{
    if(!in_array($table,['media','playlists','devices','schedules'],true)||$id<1)throw new RuntimeException('Registro inválido');
    $record=is_super()?dbone("SELECT * FROM $table WHERE id=?",[$id]):dbone("SELECT * FROM $table WHERE id=? AND tenant_id=?",[$id,tenant()]);
    if(!$record)throw new RuntimeException('Registro no encontrado o sin permiso en esta empresa');
    return $record;
}
function assert_unlinked_before_transfer(string $table,int $id,int $old):void{
    if($table==='media'){
        if(dbone("SELECT id FROM playlist_items WHERE tenant_id=? AND media_id=? AND kind='media' LIMIT 1",[$old,$id]))throw new RuntimeException('Este medio pertenece a una lista. Quítalo de las listas antes de cambiarlo de empresa.');
    }elseif($table==='playlists'){
        $checks=[
          ['playlist_items','playlist_id'],['playlist_items','child_playlist_id'],
          ['schedule_items','playlist_id'],['schedules','playlist_id'],['devices','active_playlist_id']
        ];
        foreach($checks as [$tab,$col])if(dbone("SELECT id FROM $tab WHERE tenant_id=? AND $col=? LIMIT 1",[$old,$id]))throw new RuntimeException('La lista está utilizada en contenidos, pantallas o programaciones. Desvincúlala antes de cambiar la empresa.');
    }elseif($table==='devices'){
        if(dbone('SELECT id FROM schedules WHERE tenant_id=? AND device_id=? LIMIT 1',[$old,$id]))throw new RuntimeException('La pantalla tiene programaciones. Elimínalas o reasígnalas antes de transferir la pantalla a otra empresa.');
    }
}
function layouts():array{return ['fullscreen'=>'Pantalla completa','bottom'=>'Barra inferior (20% alto)','side'=>'Barra lateral derecha (40% ancho)','half_horizontal'=>'Media pantalla horizontal (50%)','half_vertical'=>'Media pantalla vertical (50%)'];}
function orientations():array{return ['landscape'=>'Pantalla horizontal','portrait'=>'Pantalla vertical','any'=>'Ambas pantallas (indiferente)'];}
function transitions():array{return ['fade'=>'Fundido suave','slide-left'=>'Deslizar izquierda','slide-right'=>'Deslizar derecha','zoom'=>'Zoom suave','cut'=>'Corte inmediato'];}
function safe_transition(string $raw,string $default='fade'):string{return isset(transitions()[$raw])?$raw:$default;}
function safe_transition_ms($raw):int{return max(0,min(3000,(int)$raw));}
function sel(string $name,array $opts,?string $selected=''):string{
    $out='<select class="form-select mb-2" name="'.h($name).'">';foreach($opts as $id=>$label)$out.='<option value="'.h((string)$id).'" '.((string)$id===$selected?'selected':'').'>'.h($label).'</option>';return $out.'</select>';
}
function compatible(array $parent,array $child):bool{
    return $parent['layout_type']===$child['layout_type']&&($parent['orientation']==='any'||$child['orientation']==='any'||$parent['orientation']===$child['orientation']);
}
function validate_graph(int $tid,int $root,int $child):void{
    if($root===$child)throw new RuntimeException('Una lista no puede incluirse a sí misma');
    $seen=[];$visit=function(int $id,int $level)use(&$visit,&$seen,$tid,$root):void{
        if($id===$root)throw new RuntimeException('Referencia circular');if($level>12)throw new RuntimeException('Profundidad máxima: 12');
        if(isset($seen[$id]))return;$seen[$id]=true;
        foreach(dball("SELECT child_playlist_id FROM playlist_items WHERE tenant_id=? AND playlist_id=? AND kind='playlist'",[$tid,$id]) as $r)$visit((int)$r['child_playlist_id'],$level+1);
    };$visit($child,0);
}
function expanded(int $tid,int $id,array &$stack=[],int $depth=0):array{
    if($depth>12||in_array($id,$stack,true))throw new RuntimeException('Lista circular o profundidad excesiva');
    $settings=dbone('SELECT transition_effect,transition_ms FROM playlists WHERE tenant_id=? AND id=?',[$tid,$id]);
    if(!$settings)throw new RuntimeException('Lista anidada no encontrada');
    $listEffect=safe_transition((string)$settings['transition_effect']);
    $listMs=safe_transition_ms($settings['transition_ms']);
    $stack[]=$id;$out=[];
    foreach(dball('SELECT * FROM playlist_items WHERE tenant_id=? AND playlist_id=? ORDER BY position,id',[$tid,$id]) as $item){
        for($i=0;$i<min(30,max(1,(int)$item['repeat_count']));$i++){
            if($item['kind']==='media'){
                $m=dbone('SELECT id,name,mime,orientation,layout_type,sha256,size_bytes FROM media WHERE id=? AND tenant_id=?',[(int)$item['media_id'],$tid]);
                if($m)$out[]=['media_id'=>(int)$m['id'],'name'=>$m['name'],'mime'=>$m['mime'],'orientation'=>$m['orientation'],'layout'=>$m['layout_type'],'sha256'=>$m['sha256'],'size'=>(int)$m['size_bytes'],'duration'=>max(1,(int)$item['duration_seconds']),'transition'=>($item['transition_effect']==='inherit'?$listEffect:safe_transition((string)$item['transition_effect'])),'transition_ms'=>($item['transition_ms']===null?$listMs:safe_transition_ms($item['transition_ms']))];
            }else{
                $children=expanded($tid,(int)$item['child_playlist_id'],$stack,$depth+1);
                if($children){
                    // A parent list item may override the transition INTO an included list.
                    if($item['transition_effect']!=='inherit')$children[0]['transition']=safe_transition((string)$item['transition_effect']);
                    if($item['transition_ms']!==null)$children[0]['transition_ms']=safe_transition_ms($item['transition_ms']);
                }
                $out=array_merge($out,$children);
            }
            if(count($out)>2000)throw new RuntimeException('Lista excede 2000 elementos');
        }
    }array_pop($stack);return $out;
}

/** A null company filter means all companies and is allowed to SUPERADMIN only. */
function company_scope(?string $requested=null):?int {
    if (!is_super()) return tenant();
    if ($requested===null || $requested==='' || $requested==='all') return null;
    if (!ctype_digit($requested) || (int)$requested<1) throw new RuntimeException('Filtro de empresa inválido');
    $company=(int)$requested;
    if (!dbone('SELECT id FROM tenants WHERE id=?',[$company]))throw new RuntimeException('Empresa no encontrada');
    return $company;
}
function company_filter_control(?int $scope):string {
    if(!is_super())return '';
    $html='<div class="ds-filter-field"><label for="ds-company-filter">Empresa</label><select class="form-select" id="ds-company-filter" name="company"><option value="all"'.($scope===null?' selected':'').'>Todas las empresas</option>';
    foreach(dball('SELECT id,name FROM tenants ORDER BY name') as $t) {
        $html.='<option value="'.(int)$t['id'].'"'.($scope===(int)$t['id']?' selected':'').'>'.h($t['name']).'</option>';
    }
    return $html.'</select></div>';
}
