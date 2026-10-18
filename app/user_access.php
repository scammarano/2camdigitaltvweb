<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function ds_user_denials($raw,?array $old=null):array {
 if(!is_array($raw))throw new RuntimeException('Restricciones inválidas');
 $denied=[];foreach($raw as $p){if(!is_string($p)||!array_key_exists($p,permission_names()))throw new RuntimeException('Permiso restringido inválido');$denied[]=$p;}
 $denied=array_values(array_unique($denied));
 if(($old['role']??'')==='superadmin'&&$denied)throw new RuntimeException('No se puede restringir al SUPERADMIN');
 if((int)($old['id']??0)===(int)auth()['id']&&in_array('users.manage',$denied,true))throw new RuntimeException('No puedes quitarte tu acceso a la administración de usuarios');
 return $denied;
}
function ds_user_denials_form(array $denied):string {
 $html='<fieldset class="mt-3"><legend class="h6">Restringir acceso de este usuario</legend><p class="ds-hint">Estos bloqueos se suman al rol. No otorgan permisos adicionales. Desactivar Usuario activo bloquea también sus sesiones existentes.</p>';
 foreach(permission_names() as $p=>$label){if(in_array($p,['companies.manage','updates.manage'],true))continue;$html.='<label class="d-block"><input type="checkbox" name="denied_permissions[]" value="'.h($p).'"'.(in_array($p,$denied,true)?' checked':'').'> Bloquear: '.h($label).'</label>';}
 return $html.'</fieldset>';
}
