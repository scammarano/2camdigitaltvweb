<?php
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/layout.php';
set_exception_handler(function(Throwable $e):void {http_response_code(500);$id=fault($e,'page_uncaught');echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error | Digital Signage</title><link rel="stylesheet" href="'.h(url('assets/vendor/bootstrap.min.css')).'"><link rel="stylesheet" href="'.h(url('assets/css/signage.css')).'"><body class="ds-player-body"><main class="ds-player-setup"><div class="ds-player-card"><div class="ds-player-kicker">DIGITAL SIGNAGE</div><h1>No se pudo completar la operación</h1><p>Se registró el error para diagnóstico. Referencia: <strong>'.h($id).'</strong></p><a class="btn btn-primary" href="'.h(url('index.php')).'">Volver al inicio</a></div></main></body></html>';});
auth();active_tenant();
