# Ruta de migración: actualizaciones del portal desde GitHub

## Objetivo

Desarrollar y versionar únicamente el portal `scammarano/2camdigitaltvweb` (repositorio privado). A partir de una versión aprobada, permitir que SUPERADMIN pulse **Buscar/Instalar actualización desde GitHub**, eliminando la necesidad de subir archivos ZIP manualmente. Como alternativa futura, publicación automática mediante GitHub Actions con aprobación manual para producción.

## Separación

- Código web: este repositorio.
- Android/APK: repositorios separados. El portal incluye el catálogo y la gestión de APK, no los binarios APK en Git.
- Datos del servidor: MySQL, archivos multimedia, config, claves y respaldos NO se suben ni se sobrescriben.

## Propuesta de implementación segura

1. Fuente canónica: conciliar el código del repositorio con snapshot real y limpio del hosting.
2. CI: lint PHP, pruebas del instalador y listas de exclusión.
3. Publicación: tags anotados `web-vX.Y.Z`, paquete/release inmutable creado por Actions a partir del commit validado.
4. Autenticación para repo privado: GitHub App o token de alcance mínimo read-only almacenado en configuración del SERVIDOR (fuera de webroot, nunca en GitHub ni logs).
5. Botón en portal `Actualizar desde GitHub`: comprobar versión remota, mostrar cambios, dar opción de instalar después de respaldo y preflight.
6. Integridad: release con manifiesto de hashes y firma validada contra clave pública fija del servidor; no confiar solo en TLS ni en nombre de tag.
7. Instalar con el motor actual de backup+rollback y auditoría. El paquete puede ser interno en Actions; la persona no subirá ZIP ni usará FTP.
8. Promoción: primero entorno de staging, luego producción; modo manual antes de activar autoaplicación.
9. Mantener actualizador ZIP existente como vía de recuperación mientras se valida GitHub.

## Alternativa

GitHub Actions -> despliegue por SSH con clave restringida y aprobación manual de entorno protegido. Requiere acceso SSH en hosting y scripts transaccionales; evitar publicar por FTP o sobreescrituras directas sin respaldo.

## Pendientes ANTES de activar

- Confirmar la versión instalada y obtener snapshot actual de código sin datos sensibles.
- Comprobar permisos y compatibilidad de hosting PHP/cPanel con GitHub API y/o SSH.
- Decidir política de firma de releases sin divulgar la clave privada RSA ya existente.
- Integrar botón de GitHub al actualizador existente y probar rollback.
