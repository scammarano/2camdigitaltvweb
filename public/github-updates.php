<?php
/** SUPERADMIN GitHub updater: token stored only in private server storage. */
require __DIR__.'/_init.php';
require_once ROOT.'/app/github_updater.php';
require_permission('updates.manage');
if(!is_super()){http_response_code(403);exit('Actualizaciones GitHub: solo SUPERADMIN.');}
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$message='';$error='';$test=null;$releases=[];
$action=(string)($_POST['action']??'');
if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    try{
        if($action==='save_token'){
            if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){
                if(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')!=='https')throw new RuntimeException('Guarda credenciales solamente mediante HTTPS.');
            }
            $candidate=trim((string)($_POST['github_token']??''));
            $test=gh_test_connection($candidate); // do not persist an unusable token
            gh_token_save($candidate);
            gh_access_mode_save('private');
            $message='Token de solo lectura guardado. Conexión comprobada.';
        }elseif($action==='public'){
            $test=gh_test_connection('');
            gh_access_mode_save('public');
            $message='Acceso público sin token activado. Las firmas RSA y los hashes siguen siendo obligatorios.';
        }elseif($action==='delete_token'){
            gh_access_mode_save('private');gh_token_remove();$message='Conexión retirada. Si había un token, revócalo también en GitHub si ya no lo utilizarás.';
        }elseif($action==='test'){
            $token=gh_access_token();if($token===null)throw new RuntimeException('Selecciona acceso público o guarda un token GitHub válido.');
            $test=gh_test_connection($token);$message='Conexión probada correctamente; no se modificó ningún archivo.';
        }elseif($action==='install'){
            $token=gh_access_token();if($token===null)throw new RuntimeException('Primero configura el acceso a GitHub.');
            if(($_POST['confirm']??'')!=='INSTALAR' || ($_POST['ack_backup']??'')!=='1'){
                throw new RuntimeException('Debes confirmar el respaldo y escribir INSTALAR.');
            }
            $id=filter_var($_POST['release_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($id===false)throw new RuntimeException('Selecciona una versión válida.');
            $result=gh_install_release($token,(int)$id);
            $message='Actualización instalada: '.(string)$result['version'].'. Respaldo: '.(string)$result['backup'];
        }else{throw new RuntimeException('Operación no reconocida.');}
    }catch(Throwable $e){$incident=fault($e,'github_updates');$error=$e instanceof RuntimeException?substr($e->getMessage(),0,400).' · '.$incident:$incident;}
}
$connected=gh_access_token()!==null;
if($connected){
    try{$releases=gh_available_releases((string)gh_access_token());}
    catch(Throwable $e){$error=$error?:fault($e,'github_releases');}
}
render('Actualizar desde GitHub',function()use($connected,$message,$error,$test,$releases){
    if($message)echo '<div class="alert alert-success">'.h($message).'</div>';
    if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
    echo '<div class="alert alert-info">Repositorio: <strong>scammarano/2camdigitaltvweb</strong>. Versión instalada: <strong>'.h(trim(file_get_contents(ROOT.'/version.txt'))).'</strong>. Solo instalamos <strong>releases firmados con la clave RSA confiable del portal</strong>, no cualquier commit de main.</div>';
    echo '<div class="card card-body mb-3"><h5>1. Conectar GitHub (solo lectura)</h5>';
    echo '<p>Modo actual: <strong>'.(gh_access_mode()==='public'?'Público, sin token':'Privado, con token').'</strong>. Puedes pasar de público a privado guardando un token; se conserva el historial y la validación de firmas.</p><form method="post" class="mb-3"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="public"><button class="btn btn-outline-primary">Comprobar y usar repositorio público sin token</button></form>';
    echo '<p>Genera un <strong>fine-grained personal access token</strong> con acceso exclusivo a este repositorio y permiso <strong>Contents: Read-only</strong> (Metadata se agrega automáticamente). No pegues el token en chats ni en repositorios.</p>';
    echo '<p>Estado: <strong>'.($connected?'Conexión configurada':'Sin configurar').'</strong>. Los tokens opcionales se guardan en almacenamiento privado, nunca se muestran después de guardarlos.</p>';
    echo '<form method="post" class="mb-2"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="save_token"><label for="github-token" class="form-label">Nuevo token GitHub</label><input id="github-token" name="github_token" type="password" class="form-control" autocomplete="off" required minlength="20" maxlength="300" placeholder="github_pat_..." spellcheck="false"><button class="btn btn-primary mt-2">Comprobar y guardar</button></form>';
    if($connected){
        echo '<div class="d-flex gap-2 flex-wrap"><form method="post"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="test"><button class="btn btn-outline-primary">Probar conexión sin instalar</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'¿Quitar acceso GitHub de este portal?\')"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="delete_token"><button class="btn btn-outline-danger">Desconectar GitHub</button></form></div>';
    }
    if($test){echo '<div class="alert alert-success mt-3">Acceso de lectura confirmado · Repositorio '.($test['private']?'privado':'público').' · '.(int)$test['release_count'].' release(s) visibles.</div>';}
    echo '</div>';
    echo '<div class="card card-body mb-3"><h5>2. Buscar actualizaciones aprobadas</h5>';
    if(!$connected)echo '<p>Conecta GitHub para consultar las versiones disponibles.</p>';
    elseif(!$releases)echo '<p>No hay releases nuevos, compatibles y firmados publicados todavía. Esta es una prueba de conexión válida, no un error. GitHub necesita un release <code>web-vX.Y.Z</code> con un ZIP RSA firmado.</p>';
    else{
        echo '<p>Selecciona una versión superior. El portal descargará el archivo desde GitHub, <strong>verificará la firma RSA y los hashes</strong>, creará respaldo SQL/archivos y aplicará la actualización con el mismo motor del ZIP local.</p>';
        foreach($releases as $item){
            echo '<div class="border rounded p-3 mb-3"><strong>'.h($item['tag']).'</strong> · '.h($item['asset_name']).' · '.number_format($item['size']/1048576,2).' MB';
            if($item['notes']!=='')echo '<p class="text-muted mt-2">'.h($item['notes']).'</p>';
            echo '<form method="post"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="install"><input type="hidden" name="release_id" value="'.(int)$item['id'].'">';
            echo '<label class="mb-2 d-block"><input type="checkbox" name="ack_backup" value="1" required> Confirmo que tengo respaldo externo y autorizo actualizar producción.</label>';
            echo '<label class="form-label">Escribe INSTALAR</label><input class="form-control mb-2" type="text" name="confirm" required autocomplete="off" maxlength="8" placeholder="INSTALAR"><button class="btn btn-primary">Verificar firma, respaldar e instalar</button></form></div>';
        }
    }
    echo '</div><div class="alert alert-warning">Conserva <a href="'.h(url('updates.php')).'">Actualizaciones ZIP</a> como recuperación. No actualices desde un repositorio incompleto ni despliegues commits sin firma.</div>';
});
