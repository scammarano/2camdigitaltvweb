# Acceso público temporal — Web 0.4.23

Actualización acumulativa desde 0.4.16. No cambia la APK: sigue siendo 0.4.16-debug, firma permanente y versionCode416.

## Para probar ahora

1. El propietario cambia a público en GitHub el repositorio que realmente contiene las ENTREGAS. No se modifica visibilidad desde esta entrega.
2. Instalar ZIP web0.4.23 usando el actualizador ZIP. Si la instalación anterior exige token, no podrá activar el nuevo modo hasta instalar este ZIP; eso se resuelve con la carga web, sin FTP.
3. En Actualizar desde GitHub: «Comprobar y usar repositorio público sin token». Consulta, descarga e instalación conservan firma RSA, hashes, respaldo y confirmación de instalar.
4. En Repositorios y publicaciones APK: Configurar → Credencial de lectura → «Público, sin token» → Activar y guardar → Buscar ahora. Esto se configura individualmente por aplicación. El Cron existente sigue funcionando.
5. Digital Signage publica sus assets en scammarano/2camdigitaltvweb. Hacer público solo scammarano/digital-signage-android no vuelve públicos esos assets. Turnos y Sports utilizan sus respectivos repositorios de entregas y siguen necesitando sobres RSA válidos; no se inventan APK existentes.

## Volver a privado

Cambiar visibilidad en GitHub y configurar en el portal «Usar token del actualizador web» o «Token propio» por aplicación. En el actualizador web, comprobar y guardar el token activa el modo privado. No se borran catálogo, APK importadas, promoción a Estable ni historial. Los tokens guardados se conservan al elegir público, pero NO se envían: no hay fallback implícito de público a autenticado. Si vuelve a privado antes de configurar el token, la consulta falla y conserva el catálogo anterior.

## Validación y límites

Publicación o descarga anónima no reemplaza autenticidad: se siguen exigiendo RSA y comprobaciones del APK. La promoción a Estable permanece manual y exclusiva de SUPERADMIN. El selector Estables/Pruebas de la APK no cambia.

Pruebas: headers sin Authorization en metadatos y descarga pública, no reenviar token a CDN, público↔privado conservando credencial y catálogo, migración ENUM idempotente y respaldo/actualización firmada acumulativa. Transporte público real probado por CI con metadatos y un asset público de GitHub CLI; importación firmada real se prueba aparte contra la release de Digital Signage. La visibilidad y operación del hosting del usuario requieren comprobación allí.

GitHub limita las consultas anónimas por IP. Mantener el Cron cada cinco minutos y evitar consultas repetidas innecesarias; un token de lectura mejora ese límite cuando se configure posteriormente.
