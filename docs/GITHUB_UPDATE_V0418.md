# Actualizar desde GitHub — v0.4.18

El portal integra un transporte de paquetes vía GitHub privado, pero **no instala commits ni archivos arbitrarios**. Solo permite instalar releases `web-vX.Y.Z` que adjunten un ZIP con manifiesto y firma **RSA-SHA256** compatible con el actualizador previo.

Código: `app/github_updater.php`, interfaz: `public/github-updates.php`. Token read-only en `storage/secrets/github_read_token`, sin reflejarlo en respuestas ni logs. El ZIP se descarga primero en `storage/tmp/`, donde se comprueba la firma y la versión. Después se entrega a `update_apply()` para respaldo y migraciones. El ZIP local continúa funcionando.

Próximos pasos para un flujo completamente automático desde commits: importar y conciliar el código fuente íntegro en el repositorio, validar CI y preparar un proceso de publicación de releases firmadas. No se deben compartir ni subir claves privadas sin aprobación y control de entorno.
