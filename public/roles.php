<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_permission('roles.manage');
$t=tenant();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  $target=post_tenant();$action=$_POST['action']??'create';$id=(int)($_POST['id']??0);
  $role=$id?dbone('SELECT * FROM roles WHERE id=? AND tenant_id=?',[$id,$target]):null;
  if(in_array($action,['edit','delete'],true)&&!$role)throw new RuntimeException('Rol no encontrado en esta empresa');
  if($action==='delete'){
   if((int)$role['is_system'])throw new RuntimeException('No puede eliminar roles base. Puede cambiar sus permisos mediante edición.');
   if(dbone('SELECT id FROM users WHERE role_id=? LIMIT 1',[$id]))throw new RuntimeException('No se puede eliminar: hay usuarios asignados');
   execsql('DELETE FROM roles WHERE id=? AND tenant_id=?',[$id,$target]);
  }else{
   $name=trim($_POST['name']??'');if($name===''||strlen($name)>120)throw new RuntimeException('Nombre inválido');
   if($role && (int)$role['is_system']===1 && $name!==$role['name'])throw new RuntimeException('No puede cambiar el nombre de un rol base');
   $requested=array_values(array_unique(array_map('strval',$_POST['permissions']??[])));
   $valid=array_keys(permission_names());if(array_diff($requested,$valid))throw new RuntimeException('Permiso desconocido');
   if(!is_super() && array_diff($requested,permissions()))throw new RuntimeException('No puede conceder permisos superiores a los suyos');
   if(in_array('companies.manage',$requested,true)||in_array('updates.manage',$requested,true))throw new RuntimeException('Estos permisos solo corresponden al SUPERADMIN');
   if($action==='create')execsql('INSERT INTO roles(tenant_id,name,permissions_json) VALUES(?,?,?)',[$target,$name,json_encode($requested)]);
   elseif($action==='edit')execsql('UPDATE roles SET name=?,permissions_json=? WHERE id=? AND tenant_id=?',[$name,json_encode($requested),$id,$target]);
   else throw new RuntimeException('Acción inválida');
  }
  log_event('role_changed',['action'=>$action,'role_id'=>$id,'company'=>$target]);$ok='Rol actualizado correctamente';
 }catch(Throwable $e){$error=$e instanceof PDOException?'Nombre de rol duplicado o datos inválidos':$e->getMessage();log_event('role_rejected',['reason'=>$error]);}
}
$rows=dball('SELECT r.*,t.name tenant_name FROM roles r JOIN tenants t ON t.id=r.tenant_id WHERE r.tenant_id=? ORDER BY r.name',[$t]);
render('Roles y permisos',function()use($rows,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 $perms=permission_names();
 create_modal('Nuevo rol',function()use($perms){echo company_select().'<input type="hidden" name="action" value="create">';field('name','Nombre');foreach($perms as $id=>$label){if(in_array($id,['companies.manage','updates.manage'],true))continue;echo '<label class="d-block"><input type="checkbox" name="permissions[]" value="'.h($id).'"> '.h($label).'</label>';}});
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Rol</th><th>Empresa</th><th>Permisos</th><th>Acciones</th></tr></thead><tbody>';
 $dsModals='';foreach($rows as $r){$ids=json_decode($r['permissions_json'],true)?:[];$dialog='role'.(int)$r['id'];echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['tenant_name']).'</td><td>'.($ids?implode(' ',array_map(fn($p)=>'<span class="ds-mini-pill">'.h($perms[$p]??$p).'</span>',$ids)):'<span class="ds-muted">Sin permisos</span>').'</td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#'.$dialog.'">Editar</button> ';
 if(!(int)$r['is_system']){form_open();echo '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn btn-sm btn-outline-danger" onclick="return confirm(\'¿Eliminar rol sin usuarios asignados?\')">Eliminar</button></form>';}
 echo '</td></tr>';ob_start();echo '<div class="modal fade" id="'.$dialog.'" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5>Editar rol</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="'.$r['id'].'"><input type="hidden" name="tenant_id" value="'.$r['tenant_id'].'"><label>Nombre</label><input class="form-control mb-3" name="name" value="'.h($r['name']).'" required>';
 foreach($perms as $p=>$label){if(in_array($p,['companies.manage','updates.manage'],true))continue;echo '<label class="d-block"><input type="checkbox" name="permissions[]" value="'.h($p).'" '.(in_array($p,$ids,true)?'checked':'').'> '.h($label).'</label>';}
 echo '<button class="btn btn-primary mt-3">Guardar</button></form></div></div></div></div>';$dsModals.=ob_get_clean();
 }
 echo '</tbody></table></div>'.$dsModals;
});
