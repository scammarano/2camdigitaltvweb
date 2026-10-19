# Conectar la pantalla única con el catálogo central

Requiere portal **0.4.29+** y Signage **0.4.20-debug+**. Son versiones de componentes diferentes. Publicar una release no acredita que el hosting o el Fire se hayan actualizado.

1. Seleccionar la empresa en el portal. En Turnos, crear sala y vincular su pantalla de Digital Signage; en Sports, crear sede y pista y vincular su pantalla. Una pantalla tiene una asignación activa a Turnos o a Sports.
2. Preparar una playlist compatible con el modo, formato y orientación. Asignarla en la configuración de esa pantalla. Los porcentajes de las franjas siguen siendo parámetros centrales de Medios.
3. Emparejar la APK única de Signage y seleccionar/publicar Turnos o Marcador deportivo. No hace falta una segunda APK de pantalla para leer este catálogo. Activar DEBUG si se desea ver la revisión.
4. Comprobar identidad y sala/pista en pantalla. El catálogo se consulta cada 15 segundos; ABAJO × 3 solicita sincronización. ARRIBA × 3 o MENU abre ajustes con PIN.
5. Cortar Internet: se conserva el último catálogo válido. Al reconectar, una asignación inactiva o revocada deja de usarse cuando el servidor la rechaza. Cambiar portal o emparejamiento nunca reutiliza la copia anterior.

El HUD indica revisión guardada localmente o uso de copia local. Los logs `application_config_received` y `application_config_applied` NO sustituyen el ACK formal v2, y no acreditan que el motor de Turnos/Sports esté conectado. Esta fase no emite tickets, cambia puntos, descarga logos ni envía WhatsApp. Sports LAN conserva sus reglas y controles existentes.

Para importar la APK publicada, SUPERADMIN abre **Publicaciones APK → Repositorios y publicaciones → Buscar ahora en todos los repositorios activos**. La publicación entra en **Pruebas**; promoverla a Estable es una decisión manual. La consulta desatendida del portal requiere el Cron descrito en esa página; no basta con publicar en GitHub. El Fire consulta el catálogo del portal, no importa releases de GitHub por sí mismo.

Si el instalador de una versión anterior no puede abrir la actualización, abrir la nueva APK desde Downloader o el instalador externo, conservando la aplicación instalada. Las entregas 0.4.19/0.4.20 usan el mismo certificado permanente. Un certificado instalado diferente requiere evaluación aparte; no borrar datos como solución al fallo de URI.

Validación automatizada: catálogos generados con el PHP0.4.29 y MySQL reales, leídos por Android en API22/API29; aislamiento por empresa, sala/pista, portal, credencial y aplicación; revisión, persistencia y revocación; regresiones de instalador, geometría, Overlay y Sports LAN. La verificación del hosting y del Fire físico queda pendiente.
