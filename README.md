# 2Cam Digital Signage — Portal Web

Repositorio **exclusivo del portal web** de 2Cam Digital Signage (PHP 8.1 + MySQL).

## Separación de proyectos
- **Aquí:** PHP, HTML, CSS, JavaScript, esquema y migraciones de MySQL, documentación, pruebas y flujo de despliegue.
- **En repositorios independientes:** reproductor Android TV / Fire Stick, controladores de 2Cam Turnos y 2Cam Sports, y sus archivos APK.
- **Solo en el servidor:** configuración privada, credenciales, medios de clientes, descargas APK, logs, backups y claves privadas.

## Preparación de la fuente
La referencia de desarrollo es la versión web **0.4.17**. Antes del primer despliegue desde GitHub, importar y revisar el código completo del portal, contrastándolo con la instalación actual. Un repositorio vacío o que tenga únicamente esta documentación **NO está listo para desplegar**.

## Objetivo de actualización
Incorporar al portal el botón **«Actualizar desde GitHub»**, sin subir manualmente ZIP: buscar release/version aprobada, verificar firma + hashes, hacer respaldo de archivos y MySQL, instalar con migraciones y registrar el resultado; disponer de restauración. El actualizador ZIP se conserva como recuperación.

No habilitar deployment automático ni copiar archivos directamente a producción hasta comprobar staging, configuración y rollback.

Dominio: `https://www.2cam.services/digitaltv/portal/public/`
