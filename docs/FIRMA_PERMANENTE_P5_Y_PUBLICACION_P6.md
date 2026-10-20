# Firma permanente P5 — publicación segura de Digital Signage P6

Estado: proceso de publicación implementado. **No autoriza una clave nueva** ni la
publicación de compilaciones test-signed. La versión de la web es 0.4.34 y el APK
es 0.4.23-debug (versionCode 423).

## Contrato irreversible de continuidad

La última APK publicada bajo P5 es
`web-v0.4.33 / 2Cam_Digital_Signage_v0.4.22_DEBUG.apk`.
La próxima debe usar el **mismo** certificado Android SHA-256:

`52468eda51fbe7de6886fd800874f73566bdaa7165732e96f03764483840ec2f`

Y la misma clave RSA pública instalada como
`config/update-rsa-pubkey.pem`, huella DER SHA-256:

`5ad31c4313966cbb414facb56fcd8196be065910201aabfedfbee042bc2c49ae`

Ningún algoritmo puede recuperar las claves privadas a partir de estos datos
públicos ni a partir de una APK ya firmada. No se deben inventar ni sustituir.

## Configuración inicial de GitHub (una sola vez)

En `scammarano/2camdigitaltvweb`, crear/proteger Environment
`p5-signing` desde Settings → Environments, permitiendo solo la rama de
entrega autorizada y, preferiblemente, requiriendo aprobación de publicación.
Guardar allí *encrypted environment secrets* (no variables públicas):

- `P5_ANDROID_KEYSTORE_B64`: **keystore original P5**, codificado en Base64
  como una sola línea. No es el `debug.keystore` de Actions.
- `P5_ANDROID_STORE_PASSWORD`: contraseña del almacén P5.
- `P5_ANDROID_KEY_ALIAS`: alias de la clave Android P5.
- `P5_ANDROID_KEY_PASSWORD`: contraseña de la clave Android P5.
- `P5_RSA_PRIVATE_PEM`: archivo privado RSA P5/2026 en PEM, texto completo.

Nunca guardar estas claves en Git, ZIP web, portal, logs, issues, o chat.
No reemplazar el keystore anterior por uno recién generado. Cuando se
configuren los secretos, volver a ejecutar la misma ejecución fallida desde
Actions, o lanzar `P5-continuity P6 signed release` desde la rama habilitada.

## Qué hace el flujo

1. Comprueba la CI de portal y la CI Android. Compila Android como `release`
   usando **solo** la ruta de firma P5. La compilación `debug` permanece
   separada y se rotula `TEST-SIGNED-NOT-FOR-DEVICE-UPGRADE`.
2. Descarga de GitHub la APK **real** P5 v0.4.22 y comprueba su firma.
   Comprueba que la APK v0.4.23 lleva exactamente el mismo certificado,
   el mismo package `com.digitalsignage.tv`, minSdk 19, v1 y versionCode 423.
3. Comprueba que la RSA privada coincide con la autoridad pública 2026 fijada
   y genera el ZIP web acumulativo con manifiesto RSA-SHA256, y el sobre APK
   `2CAM_APK_android-tv_v0.4.23-debug_RSA.zip` para el repositorio de APK.
4. Prueba en emuladores API 22 y API 29 el paso **0.4.22 → 0.4.23 con
   `adb install -r`**, verificando conservación de un archivo previo.
5. **Solo si todo pasa**, crea `web-v0.4.34` con el ZIP de actualización y
   el APK/sobre firmados. Jamás reemplaza una release de la misma etiqueta.

Si falta cualquier secreto, CI o firma P5, se detiene sin publicar.
No hay clave privada en el servidor PHP ni en las APK distribuidas.

## Actualización real por los dispositivos

- En el portal, el repositorio `android-tv` debe apuntar a
  `scammarano/2camdigitaltvweb` y continuar autorizando el certificado P5.
- El sincronizador del portal importa el sobre RSA. Las nuevas versiones
  entran a **Pruebas** por defecto; la promoción a Estable requiere SUPERADMIN.
- La APK emparejada revisa su canal de actualizaciones y ofrece la instalación
  sin perder emparejamiento ni datos, al coincidir el certificado.
- Android/Fire OS puede exigir confirmación del sistema o habilitar instalar
  desde fuentes desconocidas. El objetivo NO es eludir ese control.
- Publicar en GitHub no instala la web en cPanel, no actualiza pantallas por sí
  solo y no sustituye la prueba física sobre Fire Stick.

El criterio de aceptación incluye prueba física de `0.4.22 → 0.4.23`,
emparejamiento persistente, PIN, playlists y reinicio del dispositivo.
