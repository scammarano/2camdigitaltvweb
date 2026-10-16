<?php
require __DIR__.'/_init.php';require_permission('companies.manage');
$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  if(isset($_POST['use'])){$d=dbone('SELECT id FROM tenants WHERE id=?',[(int)$_POST['use']]);if(!$d)throw new RuntimeException('Empresa inválida');$_SESSION['active_tenant']=(int)$d['id'];log_event('tenant_switch',['selected'=>(int)$d['id']]);redirect('index.php');}
  $name=trim($_POST['name']??'');if($name==='')throw new RuntimeException('Nombre requerido');
  execsql('INSERT INTO tenants(name) VALUES(?)',[$name]);require_once ROOT.'/app/schema.php';ensure_schema();log_event('tenant_created',['name'=>$name]);$ok='Empresa creada';
 }catch(Throwable $e){$error=$e instanceof PDOException?'Empresa duplicada o datos inválidos':$e->getMessage();}
}
$rows=dball('SELECT id,name,created_at FROM tenants ORDER BY name');
render('Empresas',function()use($rows,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 create_modal('Nueva empresa',fn()=>field('name','Nombre de empresa'));
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Creación</th><th>Contexto</th></tr></thead><tbody>';
 foreach($rows as $r){echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['created_at']).'</td><td>';form_open();echo '<button class="btn btn-sm btn-outline-primary" name="use" value="'.$r['id'].'">Trabajar con esta empresa</button></form></td></tr>';}
 echo '</tbody></table></div>';
});
