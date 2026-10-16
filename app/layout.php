<?php
/** Digital Signage unified shell — visual update 0.3.2, Bootstrap local. */
function ds_icon(string $name, int $size=19):string {
    $paths=[
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'companies'=>'<rect x="4" y="7" width="16" height="14" rx="1"/><path d="M8 7V3h8v4M8 12h2m4 0h2m-8 4h2m4 0h2"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'playlist'=>'<path d="M4 6h13M4 11h13M4 16h8"/><path d="m17 15 5 3-5 3v-6Z"/>',
        'screen'=>'<rect x="2" y="3" width="20" height="15" rx="2"/><path d="M8 22h8m-4-4v4"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>',
        'play'=>'<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8Z"/>',
        'logs'=>'<path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-5-5H8Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/>',
        'update'=>'<path d="M12 3v12m-4-4 4 4 4-4M5 17v4h14v-4"/>',
        'backup'=>'<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 2 18 2 18 0V5M3 12c0 2 18 2 18 0"/>',
        'health'=>'<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>'
    ];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['dashboard']).'</svg>';
}
function ds_nav_groups():array {
    return [
        'General'=>[
            ['index.php','Dashboard',null,'dashboard'],
            ['tenants.php','Empresas','companies.manage','companies'],
            ['users.php','Usuarios','users.manage','users'],
            ['roles.php','Roles y permisos','roles.manage','shield']
        ],
        'Contenido y pantallas'=>[
            ['media.php','Biblioteca de medios','media.view','image'],
            ['media-formats.php','Formatos de medios','media.view','image'],
            ['media-guide.php','Guía de formatos y tamaños','media.view','image'],
            ['playlists.php','Listas de reproducción','playlists.view','playlist'],
            ['devices.php','Pantallas','devices.view','screen'],
            ['schedules.php','Programación de contenido','schedules.view','calendar'],
            ['mode-schedules.php','Programación de modos','schedules.view','calendar'],
            ['device-options.php','Configuración APK','devices.view','health'],
            ['device-manager.php','Administración remota APK','devices.view','health'],
            ['device-diagnostics.php','Logs y sincronización de pantallas','devices.view','logs'],
            ['device-profiles.php','Perfiles de configuración','devices.view','health'],
            ['player.php','Reproductor de pruebas',null,'play']
        ],
        // Applications remain independent: this is only the temporary operational
        // bridge while 2Cam Turnos and 2Cam Sports implement their own portals.
        'Aplicaciones vinculadas'=>[
            ['app-downloads.php','Descargar aplicaciones',null,'update'],
            ['live-panels.php','Turnos y marcadores (pruebas)','devices.view','screen']
        ],
        'Sistema'=>[
            ['logs.php','Logs','logs.view','logs'],
            ['github-updates.php','Actualizar desde GitHub','updates.manage','update'],
            ['updates.php','Actualizaciones ZIP (respaldo)','updates.manage','update'],
            ['apk-releases.php','Publicar y administrar APK','updates.manage','update'],
            ['apk-repositories.php','Repositorios y publicaciones APK','updates.manage','update'],
            ['backups.php','Respaldos','updates.manage','backup'],
            ['diagnostics.php','Diagnóstico','updates.manage','health'],
            ['upgrade.php','Revisión / migración','updates.manage','health']
        ]
    ];
}
function page(string $title,string $content):void {
    $current=basename(parse_url($_SERVER['SCRIPT_NAME']??'index.php', PHP_URL_PATH)??'index.php');
    $descriptions=[
        'index.php'=>'Resumen de tu plataforma de señalización digital.',
        'tenants.php'=>'Selecciona la empresa de trabajo y administra las organizaciones.',
        'users.php'=>'Gestiona el acceso de las personas de la empresa.',
        'roles.php'=>'Define los permisos de cada perfil.',
        'media.php'=>'Administra imágenes y videos compatibles con tus listas.',
        'playlists.php'=>'Combina medios y otras listas en secuencias reutilizables.',
        'devices.php'=>'Configura pantallas, ubicación y emparejamiento.',
        'schedules.php'=>'Asigna contenidos y horarios a cada pantalla.',
        'mode-schedules.php'=>'Alterna Cartelera, Overlay y Pantalla inteligente por horario.',
        'live-panels.php'=>'Controles provisionales para probar las pantallas conectadas a 2Cam Turnos y 2Cam Sports. La gestión completa estará en cada aplicación.',
        'device-options.php'=>'Modos habilitados, Overlay y telemetría de la APK.',
        'device-manager.php'=>'Configura, respalda y restaura ajustes de las APK por empresa y pantalla.',
        'device-diagnostics.php'=>'Consulta eventos de cada pantalla y solicita su sincronización prioritaria.',
        'device-profiles.php'=>'Crea perfiles y distribuye ajustes entre las pantallas de cada empresa.',
        'logs.php'=>'Consulta y exporta eventos de diagnóstico.',
        'updates.php'=>'Instala versiones mediante archivos ZIP firmados.',
        'github-updates.php'=>'Conecta tu repositorio privado y actualiza desde versiones RSA firmadas sin cargar archivos ZIP manualmente.',
        'apk-releases.php'=>'Sube, publica y retira las versiones APK de Digital Signage, Turnos y Sports.',
        'app-downloads.php'=>'Descargas de 2Cam Digital Signage, 2Cam Turnos y 2Cam Sports, con sus versiones e instrucciones.',
        'backups.php'=>'Consulta los respaldos creados por el actualizador.',
        'diagnostics.php'=>'Comprueba los requisitos y el estado del sistema.',
        'upgrade.php'=>'Revisa y repara el esquema de datos cuando sea necesario.',
        'player.php'=>'Vista de comprobación del contenido programado.'
    ];
    $nav='';
    foreach(ds_nav_groups() as $group=>$items) {
        $links='';
        foreach($items as [$path,$label,$permission,$icon]) {
            if($permission!==null&&!can($permission))continue;
            $active=$path===$current;
            $links.='<a class="ds-nav-link'.($active?' active':'').'" href="'.h(url($path)).'"'.($active?' aria-current="page"':'').'>'.ds_icon($icon).'<span>'.h($label).'</span></a>';
        }
        if($links!=='')$nav.='<div class="ds-nav-group"><div class="ds-nav-label">'.h($group).'</div>'.$links.'</div>';
    }
    $me=auth();$role=is_super()?'SUPERADMIN':'Usuario de empresa';
    $company=(string)tenant();$companyRow=dbone('SELECT name FROM tenants WHERE id=?',[(int)$company]);$companyName=$companyRow['name']??('Empresa #'.$company);
    if(is_super())$companyText='<a href="'.h(url('tenants.php')).'" class="ds-company-chip" title="Cambiar empresa"><span class="ds-chip-dot"></span>'.h($companyName).' <span class="ds-chip-arrow">⌄</span></a>';
    else $companyText='<span class="ds-company-chip"><span class="ds-chip-dot"></span>'.h($companyName).'</span>';
    $v=is_file(ROOT.'/version.txt')?trim((string)file_get_contents(ROOT.'/version.txt')):'0.3.x';
    $description=$descriptions[$current]??'Administración de Digital Signage.';
    $css=h(url('assets/css/signage.css'));
    $bootstrap=h(url('assets/vendor/bootstrap.min.css'));
    $bootstrapJs=h(url('assets/vendor/bootstrap.bundle.min.js'));
    $js=h(url('assets/js/signage.js'));
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>'.h($title).' | Digital Signage</title><link rel="stylesheet" href="'.$bootstrap.'"><link rel="stylesheet" href="'.$css.'?v='.rawurlencode($v).'"></head>';
    echo '<body class="ds-body"><div class="ds-app">';
    echo '<aside class="ds-sidebar" id="ds-sidebar" aria-label="Navegación principal"><a href="'.h(url('index.php')).'" class="ds-brand"><span class="ds-brand-icon">'.ds_icon('screen',25).'</span><span class="ds-brand-text">Digital <strong>Signage</strong><small>PORTAL DE GESTIÓN</small></span></a><div class="ds-sidebar-scroll-controls"><button type="button" class="ds-sidebar-scroll-button" id="ds-nav-up" aria-label="Subir en el menú">▲ SUBIR</button><button type="button" class="ds-sidebar-scroll-button" id="ds-nav-down" aria-label="Bajar en el menú">▼ BAJAR</button></div><div class="ds-sidebar-inner" id="ds-sidebar-scroll" tabindex="0">'.$nav.'</div><div class="ds-sidebar-footer"><span class="ds-availability-dot"></span> Portal operativo <span class="ds-version">v'.h($v).'</span></div></aside>';
    echo '<div class="ds-main-wrap"><header class="ds-topbar"><button class="ds-menu-toggle" type="button" id="ds-menu-toggle" aria-label="Abrir menú" aria-controls="ds-sidebar" aria-expanded="false">'.ds_icon('menu',23).'</button><div class="ds-topbar-title">Digital Signage <span class="ds-topbar-divider">/</span> <span>'.h($title).'</span></div><div class="ds-topbar-right"><a href="'.h(url('app-downloads.php')).'" class="ds-top-download" title="Centro de descargas de aplicaciones">⬇ APK</a>'.$companyText.'<div class="ds-profile"><div class="ds-avatar">'.h(strtoupper(substr((string)$me['name'],0,1))).'</div><span class="ds-profile-name">'.h((string)$me['name']).'<small>'.h($role).'</small></span></div><a href="'.h(url('logout.php')).'" class="ds-logout" title="Cerrar sesión" aria-label="Cerrar sesión">'.ds_icon('logout',20).'</a></div></header>';
    echo '<main class="ds-main" id="main"><div class="ds-page-heading"><div><div class="ds-eyebrow">PANEL DE CONTROL <span>/</span> '.h($title).'</div><h1>'.h($title).'</h1><p>'.h($description).'</p></div></div><section class="ds-content" aria-label="Contenido">'.$content.'</section><footer class="ds-footer">Digital Signage · Portal de administración <span>v'.h($v).'</span></footer></main></div><div class="ds-backdrop" id="ds-backdrop" hidden></div></div>';
    echo '<script src="'.h(url('assets/js/media-format-runtime.js')).'"></script><script src="'.h(url('media-formats-data.php')).'"></script><script src="'.$bootstrapJs.'"></script><script src="'.$js.'?v='.rawurlencode($v).'"></script></body></html>';
}
function render(string $title,callable $fn):void{ob_start();$fn();page($title,ob_get_clean());}
function field(string $name,string $label,string $type='text',string $value=''):void {
    echo '<div class="mb-3"><label class="form-label" for="f-'.h($name).'">'.h($label).'</label><input class="form-control" id="f-'.h($name).'" type="'.h($type).'" name="'.h($name).'" value="'.h($value).'" required></div>';
}
function form_open():void{echo '<form method="post"><input type="hidden" name="_csrf" value="'.h(csrf()).'">';}
function create_modal(string $title,callable $fn):void{
    echo '<div class="ds-actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><span class="ds-plus">+</span> Nuevo</button></div>';
    echo '<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h($title).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
    form_open();$fn();echo '<div class="ds-modal-actions"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div></div>';
}
