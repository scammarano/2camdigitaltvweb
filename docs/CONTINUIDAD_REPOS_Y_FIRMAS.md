# Continuidad obligatoria: repositorios y firmas

Al continuar 2Cam Digital Signage, leer PRIMERO GitHub, nunca reconstruir desde resúmenes anteriores:
- Web: scammarano/2camdigitaltvweb (rama feat/device-log-export-v0431).
- Android TV: scammarano/digital-signage-android (rama feat/boot-diagnostics-v0422).
- Verificar HEAD, versiones, cambios, dependencias, migraciones, contratos, CI y última release. Revisar portal, APK y panel LAN antes de modificar una funcionalidad común.
- Las releases GitHub, el portal instalado y el Fire Stick instalado son tres estados independientes; no afirmar instalación sin comprobarla.

## Claves públicas de confianza: prohibido rotarlas o perderlas por cambiar de hilo

1. Actualizador Signage 2026: conservar .release-assets/update-public.txt. SHA256 DER: 5ad31c4313966cbb414facb56fcd8196be065910201aabfedfbee042bc2c49ae. En hosting: config/update-rsa-pubkey.pem. Asset público: 2CAM_SIGNAGE_RSA_PUBLIC_KEY_2026.pem (Web release 0.4.33). El ZIP no debe borrar, sustituir ni reubicar este archivo del hosting.
2. Certificado Android Signage 2026: SHA256 X.509 52468eda51fbe7de6886fd800874f73566bdaa7165732e96f03764483840ec2f. Mantener este mismo firmante en siguientes APK para permitir actualizaciones. Certificado anterior 5390caf27181bb1163bb165867be5a5a83e748899957f0e0f68606ed6282f47a: no volver a usarlo.
3. Firma pública histórica Sports/Turnos: conservar app/trust/legacy-apk-rsa-public.txt. SHA256 DER 1c6623f7cd4ed5b1c18b7f3254e6e050a39c6bea8f2d7cd9faaecc56287a10f4. Permitirla exclusivamente para paquetes históricos Sports/Turnos, NO para el actualizador web o Signage.
4. Las claves públicas verifican, pero no firman. Los firmantes privados deben preservarse en bóveda duradera, separados del repo y del chat; nunca subirlos a artefactos. Si el material de firma requerido no está disponible, detener publicación sin crear otro firmante.
5. Toda rotación futura requiere aprobación explícita, migración, verificación de huellas y plan de continuidad. Mantener compatibles los certificados de las aplicaciones ya distribuidas.

## Arquitectura y errores detectados

- Cartelera: publicidad pantalla completa. Overlay, Turnos y Marcador: una misma fuente de listas publicitarias Overlay; Turnos/Marcador mantienen su lógica propia.
- Una elección Automático/Manual y modo único local; no dejar controles de permiso ocultos que bloqueen modo activo.
- Pendientes reportados en TV 0.4.22: PIN asignado desde web ANTES de emparejar no se instala y la app exige crear uno; arranque automático no inicializa catálogo vacío; Overlay devuelve «Este modo está deshabilitado» por enabled_modes aunque la vista previa funcione.
- Corregir cada problema en repos correspondientes con pruebas de primera instalación y reemparejamiento, API22/29 y CI PHP/MySQL. No declarar solucionado hasta verificar.
- Versionar cambios; compilar, firmar, comprobar hashes, publicar release y validar importación; luego verificar hosting y Fire Stick por separado. Nunca prometer publicación o instalación antes de evidencias.
