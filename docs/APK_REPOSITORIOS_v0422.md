# Publicaciones APK desde GitHub — Web 0.4.22 / APK 0.4.16

## Activación en el portal

1. Instalar el ZIP RSA 0.4.22 desde Actualizar desde GitHub o carga ZIP. Acumulativo desde 0.4.16; conserva config, secretos, medios y configuración. Respaldar antes de instalar.
2. Sistema → Repositorios y publicaciones APK. Configurar cada aplicación en su modal, activar y guardar. El listado abre a ancho completo.
3. Usar el token existente del actualizador o guardar un token propio por repositorio. Fine-grained PAT, repositorios seleccionados, Contents: Read-only. No escribir tokens en chat, logs ni manifiestos.
4. Digital Signage tiene preconfigurados paquete y certificado permanentes. Su repositorio de ENTREGAS es actualmente `scammarano/2camdigitaltvweb`; el código fuente sigue en `scammarano/digital-signage-android`. No confundirlos. Turnos y Sports apuntan a sus repositorios separados, desactivados hasta configurar paquete/certificado reales y publicar paquetes firmados.
5. Buscar ahora importa las publicaciones admisibles. Para ejecución con la web cerrada, crear en el hosting el Cron cada cinco minutos mostrado en esta página. Usar PHP CLI 8.1+ con pdo_mysql, zip, openssl, iconv y cURL o allow_url_fopen. Sin Cron no hay consulta periódica real. No se crea Cron automáticamente al aplicar el ZIP.
6. La nueva APK aparecerá publicada en Pruebas y la APK emparejada la encontrará con «Estables y pruebas». «Solo estables» exige promoción manual. Android/Fire OS conserva la confirmación de instalación.

## Contrato de publicación

Asset en una release completada (no draft), normal o prerelease:
`2CAM_APK_<platform>_v<version_name>_RSA.zip`, comprimido ≤50 MB, APK expandida ≤120 MiB.

Tres entradas exactas: `manifest.json`, `manifest.sig` (RSA-SHA256 base64) y `app.apk`.
El JSON firmado incluye schema=1, product=2cam-apk, platform, package, version_name, version_code, min_sdk, channel=debug, certificate_sha256, size, sha256 y notes.
La autoridad RSA es la clave PÚBLICA de confianza del actualizador ya instalada en el portal. Ninguna clave privada va en el ZIP web ni en el hosting.

El portal verifica firma RSA 3072+, identidad por aplicación, certificado declarado autorizado, hash/tamaño del binario y los valores reales de AndroidManifest.xml. La firma RSA autentica la declaración del certificado hecha por el publicador; PHP no ejecuta apksigner ni verifica por sí solo todas las firmas Android. Por eso `scripts/package_apk_release.py` exige apksigner, valida el certificado REAL y v1/API19 ANTES de firmar el sobre. La APK instalada vuelve a verificar el certificado Android al actualizar.

El script recibe el APK definitivo ya firmado, el certificado permitido, aapt, apksigner.jar y la clave RSA OFFLINE. No acepta candidatos test-signed distintos de la firma permanente. Subir el ZIP resultante como asset de una release. El publicador de entregas verifica la firma y hashes antes de subir.

## Política y recuperación

- Todo importado entra a Pruebas, incluso si GitHub etiqueta la release como estable. Solo SUPERADMIN promueve a Estable mediante POST+CSRF+confirmación. Se crea un registro Estable que referencia los mismos bytes y conserva el registro Pruebas; sin recompilar.
- Stable-only no recibe pruebas; Estables y pruebas recibe el código más alto entre canales admitidos, sin downgrade. Beta queda como compatibilidad de API para clientes anteriores; la nueva interfaz ofrece solo las dos opciones. Configuraciones beta previas migran a Solo estables por seguridad.
- No se reemplaza una versión existente con bytes distintos; no se reactiva una versión retirada al consultar otra vez. Se rechazan otra aplicación, SHA incorrecto, firma desconocida, ZIP extra/traversal y manifiesto inconsistente.
- Bloqueo compartido entre importación, conexión y promoción evita ejecuciones concurrentes. Hasta tres assets nuevos por repositorio por pasada; consulta las 30 releases recientes, continúa el resto en próximas pasadas. No dispara descargas desde las consultas de dispositivos.
- Errores por repositorio quedan visibles con hora UTC e incidencia; no ocultan los otros repositorios. El catálogo anterior permanece disponible si falla una descarga o validación.
- No se modifica código ni se instala actualización en los dispositivos de Turnos/Sports: sus proyectos deben consumir la política de canales y publicar su sobre firmado. No afirmar actualización end-to-end de esas apps hasta integrarlas y probarlas.
- La firma automática permanente dentro de CI necesita aprovisionamiento de secretos protegido, que NO se ha efectuado. El flujo actual admite entregas finales firmadas offline; no publica automáticamente cada candidato de compilación.

## Pruebas de aceptación en instalación real

Activar conexión DS, Buscar ahora, observar APK nueva en Pruebas, comprobar hash público; dispositivo 0.4.14/15 con canal pruebas encuentra 0.4.16 y actualiza sin borrar datos con la firma permanente. Solo estables no debe recibirla hasta promoción. Promover conserva SHA; repetir importación no duplica ni reactiva retiradas. Cortar conexión y observar incidencia manteniendo catálogo anterior. Verificar dos ejecuciones Cron con la web cerrada. Comprobar cuatro modos, formatos centrales no editables, HUD y Sports LAN en Fire OS5/API22. Pendiente validación física y hosting real.
