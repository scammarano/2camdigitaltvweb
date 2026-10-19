# Sports Control publicado — portal web 0.4.24

La APK de control Sports 0.3.0-test se publica como Pruebas desde su repo `scammarano/2cam-sports`, release `sports-control-v0.3.0-test`. Es el archivo original del CI 38778477403, fuente e59b4141046f7a6b35a25bfa26c28097fe209751. No se recompiló ni se cambió su firma.

- Paquete Android: `services.twocam.sportscontrol`, versionCode3, minSdk23 (Android6+ para teléfono/tablet).
- SHA APK: `3acaf8319e56128e74e63941a1365630eeeaab8e9d9c9d656bccc124e7ec76f1`.
- Certificado Android original: `7bd01e3921783cbbad9e09214d2857a75208a71c56215c219217c101829af7e0`.
- Sobre del importador: `2CAM_APK_sports-control_v0.3.0-test_RSA.zip`, firmado por la autoridad RSA del portal y verificado contra APK real mediante apksigner/aapt antes de publicar.

El CI original pasó 23 tests de servicio, lint, compilación y prueba instrumentada API29 con SPORTS_ANDROID_SMOKE_PASS. Sports se conecta por LAN directamente al Sports del Fire dentro de Digital Signage; no requiere Node ni PC. Descubrimiento mDNS, enlazado por código y operaciones idempotentes. No es una APK para Fire OS5/API22: en el Fire se instala Digital Signage0.4.16. Hardware/red física aún por probar.

La web0.4.24 es acumulativa desde0.4.16 e incluye los cambios0.4.23 de acceso público sin token. Precarga la identidad real y el modo público de Sports SOLO si la conexión Sports estaba sin identidad, desactivada y apuntando al repo estándar. No sobrescribe configuración del administrador ni activa la conexión automáticamente. Después de instalar: Sistema → Repositorios y publicaciones APK → Sports → Configurar → Activar → Guardar → Buscar ahora. El Cron posterior importa nuevos sobres; promoción a Estable sigue siendo manual.

La app SportsControl0.3.0 no tiene aún su propio buscador de actualizaciones desde el portal. La publicación en el catálogo permite descargarla e instalarla; no implica actualización automática de esta app ni garantiza continuidad de su antigua firma debug en próximas compilaciones. No se ha aprovisionado su clave privada original como secreto persistente. El publicador rechaza cambios de certificado respecto a esta entrega.

Turnos seguía privado y vacío al revisar el16/10/2026; sin releases, Actions, rama main utilizable ni APK localizada en los archivos. Solo se localizó el documento de definición. No se inventó una app de Turnos ni se publicó un visor de Signage como si fuera su motor.

Ningún cambio de visibilidad, despliegue de hosting ni instalación física se realizó automáticamente.
