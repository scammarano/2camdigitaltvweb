<?php
/**
 * 2Cam Digital Signage — GitHub release transport for the existing signed updater.
 * The GitHub PAT permits READ ONLY access to one private repository. It is never
 * used as an authenticity signal: app/updater.php's trusted RSA signature is.
 */
declare(strict_types=1);
require_once __DIR__.'/updater.php';

const DS_GITHUB_REPO = 'scammarano/2camdigitaltvweb';
const DS_GITHUB_API = 'https://api.github.com/repos/'.DS_GITHUB_REPO;
const DS_GITHUB_JSON_LIMIT = 2000000;
const DS_GITHUB_ASSET_LIMIT = 50000000;

function gh_token_path():string {
    return ROOT.'/storage/secrets/github_read_token';
}
function gh_token_read():?string {
    $file=gh_token_path();
    if(!is_file($file))return null;
    $token=trim((string)@file_get_contents($file));
    return ($token!=='' && strlen($token)<=300)?$token:null;
}
function gh_token_save(string $token):void {
    if(strlen($token)<20||strlen($token)>300||!preg_match('/^[A-Za-z0-9_\-]+$/D',$token)) {
        throw new RuntimeException('Token inválido. Copia el token completo desde GitHub.');
    }
    $folder=dirname(gh_token_path());
    if(!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {
        throw new RuntimeException('No se pudo crear el directorio privado de credenciales.');
    }
    @chmod($folder,0700);
    $temp=$folder.'/.github-'.bin2hex(random_bytes(8));
    if(file_put_contents($temp,$token."\n",LOCK_EX)!==strlen($token)+1){
        @unlink($temp);throw new RuntimeException('No fue posible guardar la credencial en almacenamiento privado.');
    }
    @chmod($temp,0600);
    if(!rename($temp,gh_token_path())){@unlink($temp);throw new RuntimeException('No fue posible activar la credencial.');}
    @chmod(gh_token_path(),0600);
}
function gh_token_remove():void {
    $file=gh_token_path();
    if(is_file($file) && !unlink($file))throw new RuntimeException('No fue posible quitar el token almacenado.');
}
/** Fixed host and path: no user-supplied URLs or repository names. */
function gh_api_url(string $path):string {
    if($path!=='' && (!preg_match('#^/[A-Za-z0-9_.?=&/\-]+$#D',$path) || str_contains($path,'..'))){
        throw new RuntimeException('Ruta GitHub no permitida.');
    }
    return DS_GITHUB_API.$path;
}
function gh_http_json(string $path,string $token):array {
    $url=gh_api_url($path);
    $headers=[
        'Authorization: Bearer '.$token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: 2Cam-DigitalSignage-Web-Updater',
    ];
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        if($ch===false)throw new RuntimeException('No se puede iniciar consulta HTTPS a GitHub.');
        $data='';
        curl_setopt_array($ch,[
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_TIMEOUT=>25,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>static function($curl,string $part)use(&$data):int {
                if(strlen($data)+strlen($part)>DS_GITHUB_JSON_LIMIT)return 0;
                $data.=$part;return strlen($part);
            },
        ]);
        $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if($ok===false && $code===0)throw new RuntimeException('No se pudo conectar a api.github.com. Revisa HTTPS/cURL del hosting.');
        if(strlen($data)>DS_GITHUB_JSON_LIMIT)throw new RuntimeException('Respuesta de GitHub demasiado grande.');
    }else{
        if(!filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
            throw new RuntimeException('El hosting debe habilitar PHP cURL o allow_url_fopen para consultar GitHub.');
        }
        $context=stream_context_create([
            'http'=>['method'=>'GET','header'=>implode("\r\n",$headers),
                'timeout'=>25,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],
            'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
        ]);
        $stream=@fopen($url,'rb',false,$context);
        if(!$stream)throw new RuntimeException('No se pudo conectar a GitHub por HTTPS.');
        $status=$http_response_header[0]??'';
        $code=preg_match('#^HTTP/\S+ (\d{3})#',$status,$matches)?(int)$matches[1]:0;
        $data=stream_get_contents($stream,DS_GITHUB_JSON_LIMIT+1);fclose($stream);
        if(!is_string($data)||strlen($data)>DS_GITHUB_JSON_LIMIT)throw new RuntimeException('Respuesta GitHub inválida o demasiado grande.');
    }
    if($code===401)throw new RuntimeException('GitHub rechazó el token (401). Crea uno nuevo con lectura del repositorio.');
    if($code===403)throw new RuntimeException('GitHub devolvió 403. Revisa permisos del token, autorización SSO y límites de API.');
    if($code===404)throw new RuntimeException('Repositorio o recurso privado no accesible (404). Revisa el acceso del token a '.DS_GITHUB_REPO.'.');
    if($code!==200)throw new RuntimeException('GitHub respondió HTTP '.$code.'. Reintenta o revisa el diagnóstico.');
    try{$result=json_decode($data,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('GitHub devolvió JSON no válido.');}
    if(!is_array($result))throw new RuntimeException('Datos inesperados en la respuesta de GitHub.');
    return $result;
}
function gh_test_connection(string $token):array {
    $repo=gh_http_json('',$token);
    if(($repo['full_name']??'')!==DS_GITHUB_REPO || !($repo['private']??false)){
        throw new RuntimeException('El token no corresponde al repositorio privado configurado.');
    }
    $readme=gh_http_json('/contents/README.md',$token);
    if(($readme['type']??'')!=='file')throw new RuntimeException('El token no tiene acceso de lectura al código del repositorio.');
    $releases=gh_http_json('/releases?per_page=30',$token);
    return ['repository'=>DS_GITHUB_REPO,'private'=>true,'contents_readable'=>true,
        'release_count'=>count($releases),'current_version'=>trim((string)@file_get_contents(ROOT.'/version.txt'))];
}
/** Select ONLY completed, non-prerelease, versioned releases with a signed ZIP. */
function gh_available_releases(string $token):array {
    $raw=gh_http_json('/releases?per_page=30',$token);
    $current=trim((string)@file_get_contents(ROOT.'/version.txt'));
    return gh_filter_releases($raw,$current);
}
/** Pure function, independently testable without GitHub/network access. */
function gh_filter_releases(array $raw,string $current):array {
    $out=[];
    foreach($raw as $release){
        if(!is_array($release)||($release['draft']??true)||($release['prerelease']??false))continue;
        $tag=(string)($release['tag_name']??'');
        if(!preg_match('/^web-v(\d+\.\d+\.\d+)$/D',$tag,$match))continue;
        $version=$match[1];
        if(!version_compare($version,$current,'>'))continue;
        $asset=null;
        foreach(($release['assets']??[]) as $candidate){
            if(!is_array($candidate))continue;
            $filename=(string)($candidate['name']??'');
            if(!preg_match('/^SIGNAGE_UPDATE_v'.preg_quote($version,'/').'_[A-Za-z0-9_.-]*RSA\.zip$/D',$filename))continue;
            if(!is_int($candidate['id']??null) || ($candidate['size']??0)<100 || ($candidate['size']??0)>DS_GITHUB_ASSET_LIMIT)continue;
            $asset=$candidate;break;
        }
        if(!$asset)continue;
        $out[]=['id'=>(int)$release['id'],'tag'=>$tag,'version'=>$version,
            'asset_id'=>(int)$asset['id'],'asset_name'=>(string)$asset['name'],
            'size'=>(int)$asset['size'],'digest'=>(string)($asset['digest']??''),
            'published_at'=>(string)($release['published_at']??''),
            'notes'=>substr(strip_tags((string)($release['body']??'')),0,700)];
    }
    usort($out,static fn($a,$b)=>version_compare($b['version'],$a['version']));
    return $out;
}
function gh_asset_allowed_redirect(string $url):bool {
    $parts=parse_url($url);
    if(!is_array($parts)||strtolower($parts['scheme']??'')!=='https')return false;
    $host=strtolower((string)($parts['host']??''));
    if(!in_array($host,['release-assets.githubusercontent.com','objects.githubusercontent.com','github.com','api.github.com'],true))return false;
    if(isset($parts['user'])||isset($parts['pass'])||isset($parts['port']))return false;
    return true;
}
/** Stream binary to a private tempfile. Never forward the PAT to a redirect host. */
function gh_download_asset(string $token,int $assetId,string $target):int {
    if($assetId<1)throw new RuntimeException('Identificador de APK/paquete inválido.');
    if(!function_exists('curl_init') && !filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
        throw new RuntimeException('El hosting requiere PHP cURL o allow_url_fopen para descargar releases privadas.');
    }
    $url=gh_api_url('/releases/assets/'.$assetId);
    $totalRedirects=0;
    do{
        if(!gh_asset_allowed_redirect($url))throw new RuntimeException('GitHub intentó redirigir a un destino no permitido.');
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));
        $firstParty=$host==='api.github.com';
        $headers=['Accept: application/octet-stream','X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: 2Cam-DigitalSignage-Web-Updater'];
        if($firstParty)$headers[]='Authorization: Bearer '.$token;
        // PHP streams fallback for shared hosting without the cURL extension.
        if(!function_exists('curl_init')){
            $context=stream_context_create([
                'http'=>['method'=>'GET','header'=>implode("\r\n",$headers),
                    'timeout'=>150,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],
                'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
            ]);
            $stream=@fopen($url,'rb',false,$context);
            if($stream===false)throw new RuntimeException('No se pudo descargar el release por HTTPS.');
            $meta=stream_get_meta_data($stream);$responseHeaders=$meta['wrapper_data']??[];
            $status=preg_match('#^HTTP/\S+ (\d{3})#',(string)($responseHeaders[0]??''),$matches)?(int)$matches[1]:0;
            $location='';
            foreach($responseHeaders as $header){if(stripos($header,'Location:')===0)$location=trim(substr($header,9));}
            if(in_array($status,[301,302,303,307,308],true)){
                fclose($stream);
                if(++$totalRedirects>4||$location==='')throw new RuntimeException('Redirección GitHub inválida o excesiva.');
                $url=$location;continue;
            }
            if($status!==200){fclose($stream);throw new RuntimeException('GitHub no entregó el archivo (HTTP '.$status.').');}
            $file=fopen($target,'wb');
            if($file===false){fclose($stream);throw new RuntimeException('No puede abrirse el archivo temporal de descarga.');}
            $size=0;
            try{
                while(!feof($stream)){
                    $data=fread($stream,32768);
                    if($data===false)throw new RuntimeException('Error leyendo actualización GitHub.');
                    if($data==='')continue;
                    $size+=strlen($data);
                    if($size>DS_GITHUB_ASSET_LIMIT)throw new RuntimeException('Paquete GitHub superior al límite permitido.');
                    if(fwrite($file,$data)!==strlen($data))throw new RuntimeException('Error escribiendo actualización temporal.');
                }
            }finally{fclose($file);fclose($stream);}
            if($size<100)throw new RuntimeException('Archivo descargado demasiado pequeño.');
            return $size;
        }
        $file=fopen($target,'wb');if($file===false)throw new RuntimeException('No puede abrirse el archivo temporal de descarga.');
        $size=0;$location='';
        $ch=curl_init($url);
        if($ch===false){fclose($file);throw new RuntimeException('cURL no disponible para este enlace.');}
        curl_setopt_array($ch,[
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>180,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HEADERFUNCTION=>static function($ch,string $header)use(&$location):int {
                if(stripos($header,'Location:')===0)$location=trim(substr($header,9));
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use($file,&$size):int {
                $len=strlen($chunk);$size+=$len;
                if($size>DS_GITHUB_ASSET_LIMIT)return 0;
                $written=fwrite($file,$chunk);return ($written===false)?0:$written;
            },
        ]);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);fclose($file);
        if(in_array($status,[301,302,303,307,308],true)){
            if(++$totalRedirects>4)throw new RuntimeException('GitHub redirigió demasiadas veces.');
            if($location==='')throw new RuntimeException('Redirección GitHub sin destino.');
            $url=$location;
            continue;
        }
        if($ok===false)throw new RuntimeException('Se interrumpió la descarga desde GitHub.');
        if($status!==200)throw new RuntimeException('GitHub no entregó el archivo (HTTP '.$status.').');
        if($size<100 || $size>DS_GITHUB_ASSET_LIMIT)throw new RuntimeException('Tamaño de paquete GitHub inválido.');
        return $size;
    }while(true);
}
function gh_install_release(string $token,int $releaseId):array {
    $options=gh_available_releases($token);
    $chosen=null;
    foreach($options as $release){if($release['id']===$releaseId){$chosen=$release;break;}}
    if(!$chosen)throw new RuntimeException('La versión solicitada ya no está disponible o no es superior a la instalada.');
    $folder=ROOT.'/storage/tmp';
    if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))throw new RuntimeException('No pudo crearse almacenamiento temporal privado.');
    $file=tempnam($folder,'gh-release-');
    if($file===false)throw new RuntimeException('No fue posible crear un archivo temporal de actualización.');
    try{
        $size=gh_download_asset($token,$chosen['asset_id'],$file);
        if($size!==$chosen['size'])throw new RuntimeException('Tamaño descargado distinto al declarado por GitHub.');
        $digest=$chosen['digest'];
        if($digest!=='' && preg_match('/^sha256:([a-f0-9]{64})$/D',$digest,$m)){
            if(!hash_equals($m[1],hash_file('sha256',$file)))throw new RuntimeException('SHA-256 del archivo descargado no coincide con GitHub.');
        }
        $manifest=package_info($file); // existing pinned RSA signature + file checks
        if(($manifest['version']??'')!==$chosen['version'])throw new RuntimeException('La versión firmada no coincide con el release de GitHub.');
        $installed=update_apply($file); // existing SQL/file backup + rollback
        log_event('github_update_success',['tag'=>$chosen['tag'],'version'=>$installed['version'],'bytes'=>$size]);
        return $installed;
    }finally{if(is_file($file))unlink($file);}
}
