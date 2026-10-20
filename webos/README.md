# 2Cam Digital Signage — LG webOS TV (v0.1.0)

Aplicación **hosted web app** oficial webOS: `com.twocam.signage`. Compatible
para prueba con el televisor LG 75UR7800PSB / webOS 25. No es una APK.

## Arquitectura

- `app/index.html` redirige únicamente a
  `https://www.2cam.services/digitaltv/portal/public/player.php?client=webos`
  (mismo origen de `api-device.php` y `device-media.php`).
- Usa la autenticación por código temporal de seis dígitos existente; ni tokens
  de pantalla ni contraseñas viajan dentro del IPK.
- En el cliente alojado se guarda el token del dispositivo en almacenamiento
  local **solo de la aplicación webOS** y se retoma la sesión tras reinicio.
  El usuario puede desvincular desde el menú con botón Atrás.
- Reutiliza playlists, orientación, formato de medio, franjas, calendario,
  streaming de imágenes/vídeos, estado suspendido y heartbeat del portal.
- Requiere conexión a internet para iniciar y solicitar medios. La caché
  de blobs de la sesión es de memoria; **offline persistente NO implementado**.
- Superposición sobre otras apps, inicio automático al encender LG doméstico,
  instalación silenciosa y control nativo Turnos/Sports no forman parte de v0.1.0.

## Empaquetado oficial

En GitHub Actions se usa `@webos-tools/cli` y `ares-package`, no zip renombrado.

El ícono de la APK original se lee desde
`scammarano/digital-signage-android/branding/ic_2cam_signage.png.b64`
y se redimensiona para `icon.png` (80px) y `largeicon.png` (130px).
El icono binario no requiere copiarse al repositorio web.

## Instalar en LG 75UR7800PSB

1. En TV: LG Content Store → **Developer Mode**, instalar e iniciar sesión
   con cuenta de LG Developer. Activar Dev Mode Status y reiniciar.
2. Activar Key Server en Developer Mode y anotar IP y passphrase **sin enviarlos**.
3. En PC de la misma red: instalar Node y `npm install -g @webos-tools/cli`.
4. `ares-setup-device --add lg-tv` y configurar host IP,
   puerto **9922**, usuario **prisoner**.
5. `ares-novacom --device lg-tv --getkey` y escribir la passphrase
   directamente en el equipo.
6. Descargar de GitHub Releases el archivo
   `com.twocam.signage_0.1.0_all.ipk`, luego ejecutar:
   `ares-install --device lg-tv com.twocam.signage_0.1.0_all.ipk`
   y `ares-launch --device lg-tv com.twocam.signage`.
7. En el portal, crear/seleccionar una pantalla de orientación horizontal,
   asignar una playlist y generar un código de seis dígitos. Introducirlo
   con el control remoto de LG para iniciar reproducción.

La instalación por Developer Mode es temporal, según los términos de LG.
La distribución general requiere proceso de aprobación de LG Store, o
soluciones comerciales para equipos profesionales LG Signage.

## Entrega y publicación

CI empaca IPK con CLI de LG, valida su estructura y SHA-256, y entrega el
artifact `lg-webos-ipk`. Después de integrar a la rama web,
el release `webos-v0.1.0` se publica como **prerelease de pruebas**.
No instala automáticamente la web PHP del hosting: publicar PHP requiere
su propio ZIP RSA firmado por la autoridad de confianza actual.
