<?php
/** Target display geometry: values are canvas pixels, never physical centimeters. */
declare(strict_types=1);
require_once __DIR__.'/media_formats.php';
function ds_media_orientations():array {
    return ['landscape'=>['name'=>'Pantalla horizontal','width'=>1920,'height'=>1080],
            'portrait'=>['name'=>'Pantalla vertical','width'=>1080,'height'=>1920]];
}
function ds_media_zones():array {
    $f=ds_formats_current();
    return ['fullscreen'=>['name'=>'Pantalla completa','w'=>100,'h'=>100,'x'=>0,'y'=>0],
            'bottom'=>['name'=>'Barra inferior','w'=>100,'h'=>$f['bottom_height_pct'],'x'=>0,'y'=>100-$f['bottom_height_pct']],
            'side'=>['name'=>'Barra lateral derecha','w'=>$f['side_width_pct'],'h'=>100,'x'=>100-$f['side_width_pct'],'y'=>0],
            'half_horizontal'=>['name'=>'Media pantalla horizontal (inferior)','w'=>100,'h'=>50,'x'=>0,'y'=>50],
            'half_vertical'=>['name'=>'Media pantalla vertical (derecha)','w'=>50,'h'=>100,'x'=>50,'y'=>0]];
}
function ds_media_geometry(string $orientation,string $zone):?array {
    $o=ds_media_orientations()[$orientation]??null;$z=ds_media_zones()[$zone]??null;
    if(!$o||!$z)return null;
    $w=(int)round($o['width']*$z['w']/100);$h=(int)round($o['height']*$z['h']/100);
    return ['width'=>$w,'height'=>$h,'orientation'=>$orientation,'zone'=>$zone,
      'canvas_width'=>$o['width'],'canvas_height'=>$o['height'],
      'screen_name'=>$o['name'],'zone_name'=>$z['name'],
      'width_pct'=>$z['w'],'height_pct'=>$z['h'],'x_pct'=>$z['x'],'y_pct'=>$z['y']];
}
function ds_media_geometry_check(int $w,int $h,array $target,float $tolerance=0.04):bool {
    if($w<=0||$h<=0)return false;
    $expected=$target['width']/$target['height'];$actual=$w/$h;
    return abs($actual/$expected-1)<= $tolerance;
}
function ds_media_geometry_error(string $filename,int $w,int $h,array $target):string {
    $label=$target['screen_name'].' + '.$target['zone_name'];
    $expected=$target['width'].' × '.$target['height'].' px';
    return 'El archivo «'.$filename.'» mide '.$w.' × '.$h.' px y su proporción no coincide con «'.$label.'». '
      .'Para esa zona genera un arte de '.$expected.' (ancho × alto) o un tamaño proporcional. '
      .'Elige otra zona si ese archivo estaba diseñado para un formato diferente. No se importó ningún archivo del lote.';
}
