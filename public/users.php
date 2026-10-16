<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_permission('users.manage');
$t=tenant();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  $target=post_tenant();$action=$_POST['action']??'create';$id=(int)($_POST['id']??0);
  $old=$id?dbone('SELECT * FROM users WHERE id=? AND tenant_id=?',[$id,$target]):null;
  if($action==='edit'&&!$old)throw new RuntimeException('Usuario no encontrado en esta empresa');
  if($old && $old['role']==='superadmin' && !is_super())throw new RuntimeException('No puede modificar al SUPERADMIN');
  $name=trim($_POST['name']??'');$email=strtolower(trim($_POST['email']??''));
  if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Nombre o correo inválidos');
  $roleId=(int)($_POST['role_id']??0);
  $r=dbone('SELECT * FROM roles WHERE id=? AND tenant_id=?',[$roleId,$target]);if(!$r)throw new RuntimeException('El rol debe pertenecer a esta empresa');
  $rp=json_decode($r['permissions_json'],true)?:[];
  if(!is_super() && array_diff($rp,permissions()))throw new RuntimeException('No puede asignar un rol con permisos superiores a los suyos');
  $active=isset($_POST['is_active'])?1:0;
  $pass=$_POST['password']??'';
  if($pass!==''&&strlen($pass)<12)throw new RuntimeException('Contraseña mínima: 12 caracteres');
  if($action==='create'){
   if(strlen($pass)<12)throw new RuntimeException('Contraseña mínima: 12 caracteres');
   execsql("INSERT INTO users(tenant_id,name,email,password_hash,role,role_id,is_active) VALUES(?,?,?,?,'operator',?,1)",[$target,$name,$email,password_hash($pass,PASSWORD_DEFAULT),$roleId]);
  }elseif($action==='edit'){
   if((int)$old['id']===(int)auth()['id'] && $active===0)throw new RuntimeException('No puede desactivar su propia cuenta');
   if($old['role']==='superadmin'){$active=1;$roleId=(int)($old['role_id']?:$roleId);}
   execsql('UPDATE users SET name=?,email=?,role_id=?,is_active=? WHERE id=? AND tenant_id=?',[$name,$email,$roleId,$active,$id,$target]);
   if($pass!==''){if(strlen($pass)<12)throw new RuntimeException('Contraseña mínima: 12 caracteres');execsql('UPDATE users SET password_hash=? WHERE id=? AND tenant_id=?',[password_hash($pass,PASSWORD_DEFAULT),$id,$target]);}
  }else throw new RuntimeException('Acción inválida');
  log_event('user_changed',['action'=>$action,'company'=>$target,'edited_user'=>$id]);$ok='Usuario guardado';
 }catch(Throwable $e){$error=$e instanceof PDOException?'Correo duplicado o datos inválidos':$e->getMessage();log_event('user_rejected',['reason'=>$error]);}
}
$rows=dball('SELECT u.id,u.tenant_id,u.name,u.email,u.role,u.role_id,u.is_active,r.name role_name,t.name company FROM users u JOIN tenants t ON t.id=u.tenant_id LEFT JOIN roles r ON r.id=u.role_id AND r.tenant_id=u.tenant_id WHERE u.tenant_id=? ORDER BY u.name',[$t]);
$roles=dball('SELECT id,name,tenant_id FROM roles WHERE tenant_id=? ORDER BY name',[$t]);
render('Usuarios',function()use($rows,$roles,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 create_modal('Nuevo usuario',function()use($roles){echo company_select().'<input type="hidden" name="action" value="create">';field('name','Nombre');field('email','Correo','email');field('password','Contraseña (mínimo 12 caracteres)','password');echo '<label>Rol</label><select class="form-select mb-3" name="role_id">';foreach($roles as $r)echo '<option value="'.$r['id'].'">'.h($r['name']).'</option>';echo '</select>';});
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Nombre</th><th>Correo</th><th>Empresa</th><th>Rol</th><th>Estado</th><th></th></tr></thead><tbody>';
 $dsModals='';foreach($rows as $r){$dlg='usr'.(int)$r['id'];echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['email']).'</td><td>'.h($r['company']).'</td><td>'.h($r['role']==='superadmin'?'SUPERADMIN':($r['role_name']??'Sin rol')).'</td><td>'.((int)$r['is_active']?'<span class="ds-pill-good">● Activo</span>':'<span class="ds-pill-off">● Inactivo</span>').'</td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#'.$dlg.'">Editar</button></td></tr>';
 ob_start();echo '<div class="modal fade" id="'.$dlg.'" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Editar usuario</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="tenant_id" value="'.$r['tenant_id'].'"><input type="hidden" name="id" value="'.$r['id'].'"><label>Nombre</label><input name="name" class="form-control mb-2" value="'.h($r['name']).'" required><label>Correo</label><input name="email" type="email" class="form-control mb-2" value="'.h($r['email']).'" required><label>Rol</label><select name="role_id" class="form-select mb-2">';
 foreach($roles as $role)echo '<option value="'.$role['id'].'" '.((int)$role['id']===(int)$r['role_id']?'selected':'').'>'.h($role['name']).'</option>';
 echo '</select><label>Nueva contraseña (dejar vacío para conservar)</label><input type="password" name="password" autocomplete="new-password" class="form-control mb-2"><label class="d-block mb-3"><input type="checkbox" name="is_active" value="1" '.((int)$r['is_active']?'checked':'').'> Usuario activo</label><button class="btn btn-primary">Guardar</button></form></div></div></div></div>';$dsModals.=ob_get_clean();
 }
 echo '</tbody></table></div>'.$dsModals;
});
