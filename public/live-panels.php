<?php
/** Basic manual data-source editor for intelligent display; not full ticketing/score synchronization. */
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_permission('devices.view');
$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  require_permission('devices.edit');$tid=post_tenant();$did=(int)($_POST['device_id']??0);
  if(!dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]))throw new RuntimeException('Pantalla no pertenece a la empresa');
  $action=(string)($_POST['action']??'save');
  if($action==='save'){
   $widget=(string)($_POST['widget_type']??'');if(!in_array($widget,['queue','scoreboard'],true))throw new RuntimeException('Tipo inválido');
   $payload=[];
   if($widget==='queue'){
    foreach(['title','ticket','counter','message'] as $k)$payload[$k]=substr(trim((string)($_POST[$k]??'')),0,$k==='message'?180:70);
    $current=dbone('SELECT payload_json FROM device_live_panels WHERE device_id=? AND tenant_id=? AND widget_type=?',[$did,$tid,'queue']);
    $prev=json_decode((string)($current['payload_json']??'{}'),true)?:[];
    foreach(['last_issued','last_called','call_sequence'] as $k)$payload[$k]=max(0,min(999999,(int)($prev[$k]??0)));
   }else{
    foreach(['home','away','period','clock','competition'] as $k)$payload[$k]=substr(trim((string)($_POST[$k]??'')),0,70);
    foreach(['home_score','away_score'] as $k)$payload[$k]=max(0,min(999,(int)($_POST[$k]??0)));
   }
   execsql('INSERT INTO device_live_panels(device_id,tenant_id,widget_type,payload_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE widget_type=VALUES(widget_type),payload_json=VALUES(payload_json),updated_at=CURRENT_TIMESTAMP',[$did,$tid,$widget,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
   $ok='Pantalla inteligente configurada';
  }else{
   $ops=['queue_issue','queue_call','queue_repeat','queue_reset','home_plus','home_minus','away_plus','away_minus','score_reset'];
   if(!in_array($action,$ops,true))throw new RuntimeException('Acción de operador inválida');
   $pdo=db();$pdo->beginTransaction();
   try{
    $sel=$pdo->prepare('SELECT widget_type,payload_json FROM device_live_panels WHERE tenant_id=? AND device_id=? FOR UPDATE');$sel->execute([$tid,$did]);$row=$sel->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Primero configura la aplicación de esta pantalla');
    $kind=(string)$row['widget_type'];$p=json_decode((string)$row['payload_json'],true,10,JSON_THROW_ON_ERROR);
    if(!is_array($p))$p=[];
    if(str_starts_with($action,'queue_')){
     if($kind!=='queue')throw new RuntimeException('Esta pantalla no utiliza Turnos');
     $issued=max(0,(int)($p['last_issued']??0));$called=max(0,(int)($p['last_called']??0));
     if($action==='queue_issue'){$issued=min(999999,$issued+1);$p['last_issued']=$issued;$ok='Ticket #'.$issued.' emitido';}
     elseif($action==='queue_call'){
      if($called>=$issued)throw new RuntimeException('No hay tickets pendientes; primero emite un ticket');
      $called++;$p['last_called']=$called;$p['ticket']=(string)$called;$p['call_sequence']=min(999999999,1+(int)($p['call_sequence']??0));$ok='Turno #'.$called.' llamado';
     }elseif($action==='queue_repeat'){
      if($called<1)throw new RuntimeException('No hay turno anterior');
      $p['call_sequence']=min(999999999,1+(int)($p['call_sequence']??0));$ok='Turno #'.$called.' repetido';
     }else{$p['last_issued']=0;$p['last_called']=0;$p['ticket']='--';$p['call_sequence']=0;$ok='Contadores de turnos reiniciados';}
    }else{
     if($kind!=='scoreboard')throw new RuntimeException('Esta pantalla no utiliza Marcador');
     if($action==='score_reset'){$p['home_score']=0;$p['away_score']=0;$ok='Marcador reiniciado';}
     else{$home=str_starts_with($action,'home_');$key=$home?'home_score':'away_score';$val=(int)($p[$key]??0);$p[$key]=max(0,min(999,$val+(str_ends_with($action,'plus')?1:-1)));$ok='Marcador actualizado';}
    }
    $u=$pdo->prepare('UPDATE device_live_panels SET payload_json=?,updated_at=CURRENT_TIMESTAMP WHERE tenant_id=? AND device_id=?');
    $u->execute([json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$tid,$did]);$pdo->commit();
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }
  log_event('smart_display_operator',['device_id'=>$did,'tenant_id'=>$tid,'action'=>$action]);
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'live_panels'):$e->getMessage();}
}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$rows=dball('SELECT d.id,d.tenant_id,d.name,t.name company_name,l.widget_type,l.payload_json,l.updated_at FROM devices d JOIN tenants t ON t.id=d.tenant_id LEFT JOIN device_live_panels l ON l.device_id=d.id AND l.tenant_id=d.tenant_id WHERE '.($scope===null?'1=1':'d.tenant_id=?').' ORDER BY t.name,d.name',$scope===null?[]:[$scope]);
render('Pantallas inteligentes',function()use($rows,$scope,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 echo '<p>Turnos: emite tickets, llama el siguiente o repite la llamada. Marcador: modifica los puntos desde los botones + / −. La APK consulta las novedades con el intervalo configurado para Pantalla inteligente.</p>';
 echo '<form method="get" class="ds-filters">'.company_filter_control($scope).'<button class="btn btn-primary">Filtrar</button></form><div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Pantalla</th><th>Aplicación</th><th>En pantalla</th><th>Último cambio</th><th>Acción</th></tr></thead><tbody>';
 $dialogs='';foreach($rows as $r){$id=(int)$r['id'];$p=json_decode((string)($r['payload_json']??''),true);if(!is_array($p))$p=[];
    $state='—';
  if($r['widget_type']==='queue')$state='Turno: '.(string)($p['ticket']??'--').' · Puesto '.(string)($p['counter']??'--').' · Pendientes '.max(0,(int)($p['last_issued']??0)-(int)($p['last_called']??0));
  elseif($r['widget_type']==='scoreboard')$state=(string)($p['home']??'Local').' '.(int)($p['home_score']??0).' : '.(int)($p['away_score']??0).' '.(string)($p['away']??'Visitante');
  echo '<tr><td>'.h($r['company_name']).'</td><td>'.h($r['name']).'</td><td>'.h($r['widget_type']??'Sin configurar').'</td><td>'.h($state).'</td><td>'.h($r['updated_at']??'—').'</td><td>';
  if(can('devices.edit')){
   echo '<div class="d-flex flex-wrap gap-1 mb-2"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#live'.$id.'">Configurar</button></div>';
   $actions=$r['widget_type']==='queue' ? ['queue_issue'=>'+ Emitir ticket','queue_call'=>'Llamar siguiente','queue_repeat'=>'Repetir','queue_reset'=>'Reiniciar']:
      ($r['widget_type']==='scoreboard' ? ['home_plus'=>'Local +','home_minus'=>'Local −','away_plus'=>'Visitante +','away_minus'=>'Visitante −','score_reset'=>'0–0']:[]);
   echo '<div class="d-flex flex-wrap gap-1">';
   foreach($actions as $action=>$label){form_open();echo '<input type="hidden" name="device_id" value="'.$id.'"><input type="hidden" name="tenant_id" value="'.(int)$r['tenant_id'].'"><input type="hidden" name="action" value="'.h($action).'"><button class="btn btn-sm '.(in_array($action,['queue_reset','score_reset'],true)?'btn-outline-danger':'btn-outline-primary').'" type="submit"'.(in_array($action,['queue_reset','score_reset'],true)?' onclick="return confirm(&quot;¿Reiniciar contadores?&quot;)"':'').'>'.h($label).'</button></form>';}
   echo '</div>';
  }
  echo '</td></tr>';
  if(!can('devices.edit'))continue;
  ob_start();echo '<div class="modal fade" id="live'.$id.'" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>'.h($r['name']).'</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="device_id" value="'.$id.'"><input type="hidden" name="tenant_id" value="'.(int)$r['tenant_id'].'"><label>Aplicación</label><select name="widget_type" class="form-select mb-3" data-widget-select><option value="queue"'.(($r['widget_type']??'')==='queue'?' selected':'').'>Sala de espera</option><option value="scoreboard"'.(($r['widget_type']??'')==='scoreboard'?' selected':'').'>Marcador deportivo</option></select>';
  foreach(['queue'=>['title'=>'Título','ticket'=>'Número de turno','counter'=>'Puesto / taquilla','message'=>'Mensaje'],'scoreboard'=>['competition'=>'Torneo','home'=>'Local','away'=>'Visitante','home_score'=>'Goles / puntos local','away_score'=>'Goles / puntos visitante','period'=>'Período / tiempo','clock'=>'Reloj visible']] as $type=>$fields){echo '<div data-widget-fields="'.$type.'">';foreach($fields as $field=>$label){echo '<label>'.h($label).'</label><input class="form-control mb-2" name="'.$field.'" value="'.h((string)($p[$field]??'')).'">';}echo '</div>';}
  echo '<div class="ds-modal-actions"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div></form></div></div></div></div>';$dialogs.=ob_get_clean();
 }
 echo '</tbody></table></div>'.$dialogs.'<script src="'.h(url('assets/js/live-panels.js')).'"></script>';
});
