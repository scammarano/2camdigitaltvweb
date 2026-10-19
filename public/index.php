<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';$scope=company_scope();
$stats=[];
foreach(['Medios'=>['media','image','media.php'],'Listas'=>['playlists','playlist','playlists.php'],'Pantallas'=>['devices','screen','devices.php'],'Programaciones'=>['schedules','calendar','schedules.php']] as $name=>[$table,$icon,$href]) {
    $stats[]=['label'=>$name,'count'=>(int)dbone("SELECT COUNT(*) n FROM $table".($scope===null?'':' WHERE tenant_id=?'),$scope===null?[]:[$scope])['n'],'icon'=>$icon,'href'=>$href];
}
render('Dashboard',function()use($stats){
    echo '<div class="row g-3 ds-dashboard-stats">';
    foreach($stats as $s) {
        echo '<div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body ds-stat-card"><div class="ds-stat-icon">'.ds_icon($s['icon'],23).'</div><div class="ds-stat-label">'.h($s['label']).'</div><h2>'.(int)$s['count'].'</h2><a class="ds-stat-link" href="'.h(url($s['href'])).'">Administrar '.h(strtolower($s['label'])).' &rarr;</a></div></div></div>';
    }
    echo '</div>';
    echo '<div class="row g-3 mt-1"><div class="col-xl-8"><div class="card h-100"><div class="card-body"><h5>Inicio rápido</h5><p class="text-muted mb-4">Prepara el contenido y asígnalo a las pantallas de tu empresa.</p><div class="ds-quicklinks">';
    foreach([['media.php','1','Subir medios','Carga imágenes y videos, indicando orientación y formato.'],['playlists.php','2','Crear listas','Organiza medios y listas anidadas.'],['devices.php','3','Registrar pantallas','Configura resolución, ubicación y modo.'],['schedules.php','4','Programar','Establece días, horarios y listas.']] as [$path,$step,$name,$desc]) {
       echo '<a class="ds-quicklink" href="'.h(url($path)).'"><span class="ds-step">'.h($step).'</span><span><strong>'.h($name).'</strong><small>'.h($desc).'</small></span><span class="ds-next">&rarr;</span></a>';
    }
    echo '</div></div></div></div><div class="col-xl-4"><div class="card h-100"><div class="card-body"><div class="ds-side-heading">'.ds_icon('health',22).'<h5 class="mb-0">Estado del sistema</h5></div><p class="text-muted mt-3">Portal disponible. Comprueba el funcionamiento de cada módulo desde el panel de diagnóstico.</p><div class="ds-info-line"><span>Orientaciones</span><strong>Horizontal / Vertical</strong></div><div class="ds-info-line"><span>Reproducción</span><strong>Web de pruebas</strong></div><div class="ds-info-line"><span>APK instalada</span><strong>No verificada</strong></div>';
    if(can('updates.manage'))echo '<a class="btn btn-outline-primary mt-3" href="'.h(url('diagnostics.php')).'">Abrir diagnóstico</a>';
    echo '</div></div></div></div>';
});
