<?php
/** Independent playlist graph copies; never share records across companies. */
declare(strict_types=1);
require_once __DIR__.'/domain.php';
require_once __DIR__.'/media_formats.php';

function ds_playlist_copy_insert(string $table,array $row,array $overrides):int {
 if(!in_array($table,['playlists','playlist_items','media'],true))throw new RuntimeException('Tipo de copia inválido');
 unset($row['id'],$row['created_at'],$row['updated_at']);$row=array_replace($row,$overrides);
 foreach(array_keys($row) as $key)if(!preg_match('/^[a-z_][a-z0-9_]*$/D',$key))throw new RuntimeException('Columna de copia inválida');
 $columns='`'.implode('`,`',array_keys($row)).'`';$marks=implode(',',array_fill(0,count($row),'?'));
 execsql('INSERT INTO `'.$table.'`('.$columns.') VALUES('.$marks.')',array_values($row));return (int)db()->lastInsertId();
}
function ds_playlist_copy_name(string $name,string $suffix):string {
 $out='';foreach(preg_split('//u',$name,-1,PREG_SPLIT_NO_EMPTY)?:[] as $char){if(strlen($out.$char.$suffix)>160)break;$out.=$char;}
 return $out.$suffix;
}
function ds_playlist_duplicate(int $sourceId,int $destination,string $name):array {
 require_permission('playlists.edit');$name=trim($name);
 if($name===''||strlen($name)>160)throw new RuntimeException('Indica un nombre para la copia (máximo 160 bytes).');
 $source=owned_edit_record('playlists',$sourceId);$sourceTenant=(int)$source['tenant_id'];
 if(!is_super()&&($sourceTenant!==tenant()||$destination!==tenant()))throw new RuntimeException('No puedes copiar listas entre empresas.');
 if($destination!==$sourceTenant)require_permission('media.edit');
 $pdo=db();$files=[];$listMap=[];$mediaMap=[];$stack=[];$itemsCopied=0;$bytes=0;$rootId=0;
 $pdo->beginTransaction();
 try{
  ds_formats_current(true);
  if(!dbone('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$destination]))throw new RuntimeException('Empresa de destino no encontrada');
  if(dbone('SELECT id FROM playlists WHERE tenant_id=? AND name=?',[$destination,$name]))throw new RuntimeException('Ya existe una lista con ese nombre en la empresa de destino. Elige otro nombre.');
  $copyMedia=function(int $id)use(&$mediaMap,&$files,&$bytes,$destination,$sourceTenant):int {
   if(isset($mediaMap[$id]))return $mediaMap[$id];
   $m=dbone('SELECT * FROM media WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$sourceTenant]);
   if(!$m)throw new RuntimeException('Un medio de la lista no existe o pertenece a otra empresa.');
   if($destination===$sourceTenant)return $mediaMap[$id]=$id;
   if(count($mediaMap)>=500)throw new RuntimeException('La copia supera 500 medios. Divide la lista.');
   $bytes+=(int)$m['size_bytes'];if($bytes>1073741824)throw new RuntimeException('La copia supera 1 GB. Divide la lista.');
   if(!preg_match('/^[a-f0-9]{40}$/D',(string)$m['filename']))throw new RuntimeException('Un medio utiliza un nombre de archivo antiguo/no seguro. Revisa su almacenamiento antes de copiarlo.');
   $base=ROOT.'/storage/media';$srcDir=$base.'/'.$sourceTenant;$dir=$base.'/'.$destination;$src=$srcDir.'/'.$m['filename'];
   foreach([ROOT.'/storage',$base,$srcDir,$dir,$src] as $path)if(is_link($path))throw new RuntimeException('El almacenamiento contiene un enlace simbólico no permitido.');
   if(!is_file($src)||!is_readable($src))throw new RuntimeException('No está disponible el archivo de «'.$m['name'].'». No se copió la lista.');
   if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el almacenamiento de destino.');
   $stored=bin2hex(random_bytes(20));$dest=$dir.'/'.$stored;$out=fopen($dest,'xb');if(!$out)throw new RuntimeException('No se pudo crear la copia del medio.');$files[]=$dest;
   try{$in=fopen($src,'rb');if(!$in)throw new RuntimeException('No se pudo leer el medio.');try{stream_copy_to_stream($in,$out,(int)$m['size_bytes']+1);}finally{fclose($in);}}finally{fclose($out);}
   chmod($dest,0600);
   if(filesize($dest)!==(int)$m['size_bytes']||!hash_equals((string)$m['sha256'],hash_file('sha256',$dest)))throw new RuntimeException('La copia de «'.$m['name'].'» no superó la comprobación de integridad.');
   return $mediaMap[$id]=ds_playlist_copy_insert('media',$m,['tenant_id'=>$destination,'filename'=>$stored,'is_shared'=>0]);
  };
  $copyList=function(int $id,int $depth=0)use(&$copyList,$copyMedia,&$listMap,&$stack,&$itemsCopied,&$rootId,$sourceId,$sourceTenant,$destination,$name):int {
   if($depth>12||isset($stack[$id]))throw new RuntimeException('La lista contiene un ciclo o supera 12 niveles.');
   if(isset($listMap[$id]))return $listMap[$id];
   if(count($listMap)>=100)throw new RuntimeException('La copia supera 100 listas anidadas.');
   $p=dbone('SELECT * FROM playlists WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$sourceTenant]);if(!$p)throw new RuntimeException('Una sublista no existe o pertenece a otra empresa.');
   $copyName=$name;
   if($id!==$sourceId){
    $suffix=' · copia '.$rootId.'-'.$id;$copyName=ds_playlist_copy_name((string)$p['name'],$suffix);
    for($n=1;dbone('SELECT id FROM playlists WHERE tenant_id=? AND name=?',[$destination,$copyName]);$n++){
     if($n>20)throw new RuntimeException('No se pudo elegir un nombre único para la sublista.');$copyName=ds_playlist_copy_name((string)$p['name'],$suffix.'-'.$n);
    }
   }
   $new=ds_playlist_copy_insert('playlists',$p,['tenant_id'=>$destination,'name'=>$copyName,'is_shared'=>0]);$listMap[$id]=$new;if($id===$sourceId)$rootId=$new;$stack[$id]=true;
   foreach(dball('SELECT * FROM playlist_items WHERE tenant_id=? AND playlist_id=? ORDER BY position,id FOR UPDATE',[$sourceTenant,$id]) as $item){
    if(++$itemsCopied>2000)throw new RuntimeException('La copia supera 2000 renglones.');
    if($item['kind']==='media'){
     $m=dbone('SELECT orientation,layout_type FROM media WHERE id=? AND tenant_id=?',[(int)$item['media_id'],$sourceTenant]);if(!$m||!compatible($p,$m))throw new RuntimeException('La lista contiene un medio incompatible o de otra empresa.');
     $overrides=['media_id'=>$copyMedia((int)$item['media_id']),'child_playlist_id'=>null];
    }elseif($item['kind']==='playlist'){
     $child=dbone('SELECT orientation,layout_type FROM playlists WHERE id=? AND tenant_id=?',[(int)$item['child_playlist_id'],$sourceTenant]);if(!$child||!compatible($p,$child))throw new RuntimeException('La lista contiene una sublista incompatible o de otra empresa.');
     $overrides=['media_id'=>null,'child_playlist_id'=>$copyList((int)$item['child_playlist_id'],$depth+1)];
    }else throw new RuntimeException('Tipo de renglón no reconocido.');
    ds_playlist_copy_insert('playlist_items',$item,array_merge($overrides,['tenant_id'=>$destination,'playlist_id'=>$new]));
   }
   unset($stack[$id]);return $new;
  };
  $newId=$copyList($sourceId);$pdo->commit();
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  foreach($files as $file)if(is_file($file)&&!unlink($file))log_event('playlist_copy_cleanup_pending',['file'=>basename($file),'company'=>$destination]);
  if($e instanceof PDOException&&($e->errorInfo[1]??0)===1062)throw new RuntimeException('Ya existe una lista con ese nombre. Elige otro nombre y vuelve a intentar.');
  throw $e;
 }
 log_event('playlist_duplicated',['source_company'=>$sourceTenant,'source_playlist'=>$sourceId,'destination_company'=>$destination,'playlist'=>$newId,'lists'=>count($listMap),'items'=>$itemsCopied,'media_copied'=>count($files)]);
 return ['id'=>$newId,'lists'=>count($listMap),'items'=>$itemsCopied,'media_copied'=>count($files)];
}
