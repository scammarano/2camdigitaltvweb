# Evolución a actualizaciones desde GitHub

## Estado actual
El repositorio `scammarano/2camdigitaltvweb` es PRIVADO y se dedica exclusivamente al código web. Esta primera estructura de gestión no constituye todavía una copia de la instalación actual ni habilita despliegues.

## Fuente canónica
Importar/revisar el código de la web v0.4.17 y compararlo con el servidor actual. Ninguna actualización podrá instalarse solo a partir de un repositorio parcial.

## Publicar desde GitHub (diseño)
1. Se valida código PHP/MySQL y lista de cambios en Actions.
2. Se aprueba un tag inmutable `web-vX.Y.Z`.
3. Una release incluye archivo interno firmado y manifiesto con SHA-256 por archivo, lista de migraciones, versión mínima y reversión.
4. **SUPERADMIN** pulsa `Buscar actualizaciones en GitHub` dentro del portal.
5. El portal usa credenciales READ-ONLY para el repositorio privado, almacenadas fuera del webroot (no en GitHub ni en logs) y conecta por HTTPS.
6. Verifica firma del release/manifiesto, hashes y versión; muestra vista previa de archivos y migraciones.
7. Respalda MySQL y archivos, bloquea operaciones críticas, aplica los cambios preservando `config/`, `storage/`, `public/downloads/`, medios y APK.
8. Verifica salud y versión, registra auditoría y habilita restauración al respaldo anterior.

**El usuario NO descarga ni carga un ZIP manualmente.** El paquete intermedio es generado y consumido por las máquinas. El actualizador ZIP actual se conserva provisionalmente para recuperación.

## Alternativa futura
GitHub Actions puede desplegar por SSH a un entorno staging, probarlo y promoverlo a producción con aprobación explícita, cuando cPanel disponga de SSH y podamos garantizar backup/rollback. Nunca desplegar simplemente copiando archivos en caliente ni por FTP sin protección.

## Pendientes para habilitar
- Conciliar repositorio e instalación real.
- Añadir endpoint y UI GitHub al updater PHP.
- Configurar credencial de lectura de GitHub para repositorio privado.
- Establecer firma y clave de release, sin divulgar la clave de firma privada actual.
- Probar rollback y migraciones en staging.
