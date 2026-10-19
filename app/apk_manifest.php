<?php
/** Small, bounded Android binary XML metadata reader.
 * Reads AndroidManifest.xml from a standard APK using built-in PHP and ZipArchive.
 * The APK package signature is NOT verified by this parser.
 */
declare(strict_types=1);

function ds_axml_u16(string $data, int $at):int {
    if($at<0 || $at+2>strlen($data))throw new RuntimeException('Manifest Android incompleto');
    return unpack('v',substr($data,$at,2))[1];
}
function ds_axml_u32(string $data, int $at):int {
    if($at<0 || $at+4>strlen($data))throw new RuntimeException('Manifest Android incompleto');
    return unpack('V',substr($data,$at,4))[1];
}
function ds_axml_length8(string $s,int &$pos,int $bound):int {
    if($pos>=$bound)throw new RuntimeException('Cadena APK incompleta');
    $n=ord($s[$pos++]);
    if($n&0x80){if($pos>=$bound)throw new RuntimeException('Cadena APK incompleta');$n=(($n&0x7f)<<8)|ord($s[$pos++]);}
    return $n;
}
function ds_axml_length16(string $s,int &$pos,int $bound):int {
    if($pos+2>$bound)throw new RuntimeException('Cadena APK incompleta');
    $n=ds_axml_u16($s,$pos);$pos+=2;
    if($n&0x8000){if($pos+2>$bound)throw new RuntimeException('Cadena APK incompleta');$n=(($n&0x7fff)<<16)|ds_axml_u16($s,$pos);$pos+=2;}
    return $n;
}
function ds_axml_pool(string $xml,int $start,int $size,int $header):array {
    $end=$start+$size;
    if($header<28||$size<$header || $end>strlen($xml))throw new RuntimeException('Índice de cadenas APK incorrecto');
    $count=ds_axml_u32($xml,$start+8);
    if($count>20000 || $header+$count*4>$size)throw new RuntimeException('Manifest APK excede el límite de cadenas');
    $flags=ds_axml_u32($xml,$start+16);
    $stringsStart=ds_axml_u32($xml,$start+20);
    $utf8=($flags&0x100)!==0;
    $pool=[];
    for($i=0;$i<$count;$i++){
        $off=ds_axml_u32($xml,$start+$header+$i*4);
        $at=$start+$stringsStart+$off;
        if($at<$start+$header||$at>=$end)throw new RuntimeException('Cadena APK fuera de rango');
        if($utf8){
            ds_axml_length8($xml,$at,$end); // UTF-16 char count: informational
            $bytes=ds_axml_length8($xml,$at,$end);
            if($bytes>8192||$at+$bytes+1>$end||ord($xml[$at+$bytes])!==0)throw new RuntimeException('Cadena UTF-8 inválida');
            $value=substr($xml,$at,$bytes);
        }else{
            $chars=ds_axml_length16($xml,$at,$end);
            $bytes=$chars*2;
            if($bytes>16384||$at+$bytes+2>$end || ds_axml_u16($xml,$at+$bytes)!==0)throw new RuntimeException('Cadena UTF-16 inválida');
            $raw=substr($xml,$at,$bytes);
            if(function_exists('iconv'))$value=iconv('UTF-16LE','UTF-8',$raw);
            else throw new RuntimeException('PHP requiere iconv para interpretar este APK');
            if($value===false)throw new RuntimeException('Manifest APK no contiene texto UTF-16 válido');
        }
        $pool[]=$value;
    }
    return $pool;
}
function ds_axml_string(array $pool,int $idx):?string {
    return $idx===0xffffffff?null:($pool[$idx]??null);
}
function ds_axml_parse(string $xml):array {
    $len=strlen($xml);
    if($len<32||$len>2097152)throw new RuntimeException('Manifest Android demasiado grande o incompleto');
    if(ds_axml_u16($xml,0)!==0x0003 || ds_axml_u16($xml,2)!==8 || ds_axml_u32($xml,4)!==$len)
        throw new RuntimeException('Formato AndroidManifest.xml no compatible');
    $pool=[];$havePool=false;$root=null;$usesSdk=null;
    $pos=8;
    while($pos<$len){
        if($pos+8>$len)throw new RuntimeException('XML APK truncado');
        $kind=ds_axml_u16($xml,$pos);$header=ds_axml_u16($xml,$pos+2);$size=ds_axml_u32($xml,$pos+4);
        if($header<8||$size<$header||$pos+$size>$len)throw new RuntimeException('Sección de Manifest APK incorrecta');
        if($kind===0x0001){
            if($havePool)throw new RuntimeException('Índice duplicado en Manifest APK');
            $pool=ds_axml_pool($xml,$pos,$size,$header);$havePool=true;
        }elseif($kind===0x0102){ // XML start element
            if(!$havePool||$size<36)throw new RuntimeException('Etiqueta APK incorrecta');
            $tag=ds_axml_string($pool,ds_axml_u32($xml,$pos+20));
            $attStart=ds_axml_u16($xml,$pos+24);
            $attSize=ds_axml_u16($xml,$pos+26);
            $attCount=ds_axml_u16($xml,$pos+28);
            $attrs=$pos+16+$attStart;
            if($attSize<20||$attCount>128||$attrs+$attSize*$attCount>$pos+$size)
                throw new RuntimeException('Atributos de Manifest APK incorrectos');
            if($tag==='manifest'||$tag==='uses-sdk'){
                $values=[];
                for($i=0;$i<$attCount;$i++){
                    $at=$attrs+$attSize*$i;
                    $name=ds_axml_string($pool,ds_axml_u32($xml,$at+4));
                    if($name===null)continue;
                    $raw=ds_axml_u32($xml,$at+8);
                    $kindValue=ord($xml[$at+15]);
                    $payload=ds_axml_u32($xml,$at+16);
                    $value=ds_axml_string($pool,$raw);
                    if($value===null){
                        if($kindValue===0x03)$value=ds_axml_string($pool,$payload);
                        elseif(in_array($kindValue,[0x10,0x11],true))$value=(string)$payload;
                    }
                    if($value!==null)$values[$name]=$value;
                }
                if($tag==='manifest' && $root===null)$root=$values;
                if($tag==='uses-sdk' && $usesSdk===null)$usesSdk=$values;
            }
        }
        $pos+=$size;
    }
    if($root===null)throw new RuntimeException('APK sin etiqueta manifest');
    $package=(string)($root['package']??'');
    $version=(string)($root['versionName']??'');
    $code=(string)($root['versionCode']??'');
    $min=(string)($usesSdk['minSdkVersion']??'1');
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+$/D',$package))
        throw new RuntimeException('El APK no declara un nombre de aplicación Android válido');
    if(!preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:-[A-Za-z0-9.-]+)?$/D',$version) || strlen($version)>40)
        throw new RuntimeException('La versión interna del APK no sigue el formato esperado (ej. 0.4.7 o 1.0)');
    if(!ctype_digit($code)||(int)$code<1||(int)$code>2147483647)
        throw new RuntimeException('El APK no declara un código interno de versión válido');
    if(!ctype_digit($min)||(int)$min<1||(int)$min>60)
        throw new RuntimeException('El APK no declara una versión Android mínima compatible');
    return ['package'=>$package,'version_name'=>$version,'version_code'=>(int)$code,'min_sdk'=>(int)$min];
}
function ds_apk_read_manifest(string $path):array {
    if(!class_exists(ZipArchive::class))throw new RuntimeException('El servidor necesita la extensión ZIP de PHP para leer APK');
    $zip=new ZipArchive();
    if($zip->open($path)!==true)throw new RuntimeException('No se pudo abrir el archivo APK');
    try{
        $entry=$zip->statName('AndroidManifest.xml');
        if(!$entry||($entry['size']??0)>2097152||($entry['size']??0)<16)
            throw new RuntimeException('Manifest Android ausente o demasiado grande');
        $xml=$zip->getFromName('AndroidManifest.xml',2097153);
        if($xml===false)throw new RuntimeException('No se pudo leer el Manifest del APK');
        return ds_axml_parse($xml);
    }finally{$zip->close();}
}
