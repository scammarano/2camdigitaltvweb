# GitHub Updater v0.4.18 — arranque seguro

## Estado
Se preparó un puente web **0.4.18** que añade el apartado `Sistema → Actualizar desde GitHub` al portal PHP.

**Este repositorio está todavía en fase de importación inicial:** el código completo del portal reconstruido hasta 0.4.18 debe importarse y cotejarse con producción antes de usar GitHub como fuente de releases. No despliegues el contenido parcial del repositorio.

## Autenticación de servidor
- Fine-grained PAT con Resource owner `scammarano`.
- Repository access `Only select repositories`: `2camdigitaltvweb`.
- Contents: **Read-only**, Metadata: Read automático.
- Se introduce únicamente en el panel HTTPS del portal, jamás en GitHub ni en conversaciones.
- El portal lo guarda privado en `storage/secrets/github_read_token`, sin mostrarlo ni registrarlo.
- Primero se prueba la lectura de `README.md` y la lista de releases. La conexión de lectura no instala nada.

## Instalación desde GitHub
- **No** se instala directamente la rama `main`.
- Una release aprobada debe tener tag `web-vX.Y.Z` y adjuntar archivo `SIGNAGE_UPDATE_vX.Y.Z_*RSA.zip`.
- El portal descarga el archivo de la API privada de GitHub sin transmitir credenciales a otras CDNs.
- Verifica el manifiesto RSA-SHA256 mediante la clave pública del servidor, hashes individuales y versión coincidente.
- Reutiliza el motor de instalación existente: copia de seguridad SQL y de archivos, migraciones y auditoría.
- ZIP manual sigue habilitado como recuperación.

## Pendientes
1. Completar código fuente y conciliarlo con la instalación actual.
2. Subir una release aprobada y firmada nueva (actualmente no hay ninguna).
3. Crear fine-grained PAT y probarlo en el portal.
4. Probar instalación real primero en staging, incluyendo rollback tras error.
5. Automatizar empaquetado y firma de releases mediante un flujo de GitHub Actions protegido (sin subir jamás la clave privada al código fuente).
