# Web 0.4.30 / APK 0.4.21-debug

Los 43 ajustes comparten nombres, opciones, valores predeterminados y pestañas: General, Cartelera, Overlay, Turnos, Marcador deportivo, Conexión Turnos / Marcador, Diagnóstico y Actualizaciones. La TV conserva además Equipo y panel LAN para URL, puerto, PIN y permisos locales; esos permisos/secretos no se administran remotamente.

En TV, los números se editan pulsando OK sobre el botón con su valor. Se abre un editor grande, con foco, validación y Guardar/Cancelar. Contenido centrado, ancho limitado, márgenes de seguridad y pestañas que permanecen visibles. La IP aparece en el inicio, ajustes y panel LAN; el portal muestra la última IPv4 privada reportada, con la fecha del reporte existente. No confundir IP reportada con conectividad actual.

## Overlay: ventana de reproducción, después pausa

Una aparición de 12 s con imágenes de 3 s reproduce A, B, C, D durante esa ventana, con las transiciones de la lista. Luego se oculta y espera la pausa configurada. La siguiente aparición continúa desde el próximo renglón; si la ventana termina a mitad de un renglón, la próxima aparición pasa al siguiente. Una lista corta vuelve a empezar hasta completar la ventana. Los tiempos de imagen y de aparición son distintos y se conservan ambos. La pausa es posterior a la aparición; ya no requiere ser mayor que ella. Overlay mantiene soporte de imágenes, no añade videos sobre otras aplicaciones.

La prueba de Overlay se inicia inmediatamente desde su pestaña. El funcionamiento observado por el usuario incluye YouTube; no se declara un bloqueo permanente sobre esa aplicación. Los permisos y restricciones particulares de Fire OS siguen requiriendo prueba física.

## Vista previa y listas compartidas

Cada selector de lista ofrece Probar. TV reproduce medios en el formato elegido; Turnos/Marcador usan un área de simulación, sin generar llamadas ni puntos. Una lista no descargada puede obtenerse mediante el endpoint de vista previa autenticado del portal0.4.30. La descarga verifica tamaño/SHA, preserva el manifiesto activo y conserva medios mientras dure la prueba. No publica configuración ni confirma un modo efectivo. LAN solicita esa prueba en TV con sesión y CSRF; Fire OS puede impedir abrir actividades desde segundo plano. El portal abre su simulación de formato en el navegador.

En una empresa seleccionada, las listas propias y compartidas ofrecen Usar lista en pantalla, sin copiar archivos. Comprueba permisos de pantalla, empresa, orientación, formato, modos autorizados y revisión concurrente. El original compartido conserva sus permisos de edición. La asignación queda pendiente hasta la confirmación de la APK.

## Validación y prueba física

CI: PIN real, pestañas TV, foco con OK y edición numérica persistida, ventana Overlay con A/B dentro de una aparición, vista previa sin sustituir programación, contrato compartido de43campos y regresiones API22/API29. PHP/MySQL: asignación compartida sin copias, aislamiento, revisión, modo/formato y endpoint de vista previa. Navegador1280×720: pestañas conservan valores, selector compartido y enlace de prueba.

Instalar ZIP firmado desde SUPERADMIN con respaldo. Verificar versión web0.4.30 y APK0.4.21 por separado. Probar IP, flechas/OK/Cancelar, todas las pestañas, JPG A/B/C de3s en ventana12s, pausa y retorno, previsualización de cada formato y asignación de empresaA a pantallaB. La instalación interna se prueba desde una APK que ya tenga el instalador corregido; que Downloader actualice no acredita que el intento interno anterior lo haya conseguido. Se prioriza ACTION_VIEW en API19–23 y se registran acción, esquema y versión Android sin credenciales. No se ha probado el Fire físico desde CI.
