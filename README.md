# 2Cam Digital Signage — Portal Web

**Repositorio exclusivo del portal web PHP/MySQL.** Las aplicaciones Android, APK, medios de clientes, respaldos, claves privadas, credenciales y archivos runtime NO forman parte del repositorio.

## Estado de esta base

Código reconstruido a partir del portal completo v0.3.0 y los paquetes firmados incrementales hasta **v0.4.17**, más el código nuevo de integración GitHub v0.4.18. Esta reconstrucción NO prueba que el código sea idéntico al sitio en producción; debe cotejarse con un snapshot actual del servidor antes del primer despliegue.

## Directorios

- `app/`: lógica PHP y actualizador.
- `public/`: páginas, APIs y activos estáticos.
- `database/`: esquema inicial y migraciones versionadas.
- `config/config.example.php`: plantilla de configuración (no contiene credenciales).
- `storage/`: conserva solo un archivo informativo; los datos reales residen en el servidor.
- `.github/workflows/ci.yml`: lint PHP y controles de seguridad en cada push/PR.
- `docs/ACTUALIZACION_DESDE_GITHUB.md`: propuesta y requisitos de la transición.

## Seguridad de despliegue

No copiar los archivos de GitHub al hosting mediante una simple extracción/sobrescritura. La futura actualización desde GitHub deberá:

1. Validar origen/commit o tag aprobado, versión y lista permitida de archivos.
2. Aplicar bloqueo de mantenimiento, preflight y copia de seguridad de archivos + MySQL.
3. Preservar `config/`, `storage/`, `public/downloads/`, medios y logs.
4. Comprobar integridad y ejecutar migraciones versionadas.
5. Aplicar cambios con sustitución atómica; registrar y permitir restaurar el respaldo.
6. Comprobar salud del sitio antes de confirmar.

**El repositorio no implica que el despliegue desde GitHub esté activado.** Se activará solo después de reconciliar esta base con producción y probar la recuperación.

## Función GitHub incorporada en v0.4.18

`public/github-updates.php` y `app/github_updater.php` usan un token de lectura limitado al repo privado, listan releases aprobados y descargan **solo paquetes firmados** para el actualizador existente. El botón requiere una primera instalación firmada del puente v0.4.18 y releases posteriores firmadas en GitHub. No instala directamente commits de `main`. El código fuente reconstruido debe revisarse contra el hosting antes de liberar producción.
