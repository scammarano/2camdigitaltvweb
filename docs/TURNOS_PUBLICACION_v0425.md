# Turnos0.2.0-local-test en el catálogo — web0.4.25

La APK de Turnos ya es una aplicación Android independiente publicada en scammarano/2cam-turnos, tag v0.2.0-local-test. Fuente76176d5b40f137b35aa6388980cc7ac777d06d4b, CI38973490928 correcto (núcleo, build/lint, instrumentación API22 y29). Paquete services.twocam.turnos, versionCode200, minSdk22. Mismo APK en Fire/TV como autoridad local y en teléfono/tablet Android como control. No iOS.

El publicador de una rama aislada release/portal-envelope-v020 agrega solamente el sobre2CAM_APK_turnos-control_v0.2.0-local-test_RSA.zip a la release existente. No cambia main ni las funciones, APK, certificado, notas u otros assets del otro hilo. apksigner/aapt y hash del asset publicado se verifican otra vez antes de subirlo. La autoridad RSA del portal firma el manifiesto offline; ninguna clave privada se agrega a GitHub.

APK SHA256:6b3c90eaa0591c02d6e20fed0ba44fbd6125807ba57133e308a4fbc6ad318d49. Certificado ANDROID SHA256:431172fe64dbeb2536f3f08fba96650c5ca6db3fe4cd1f098b216af30638f69c. Son huellas diferentes. El certificado es DEBUG original DE PRUEBAS; no se declara comercial/permanente ni se aprueba actualizar estable con él.

Web0.4.25 corrige el lector AndroidManifest para aceptar sufijos como local-test (múltiples guiones) manteniendo longitud40, componentes numéricos y caracteres de nombre de archivo seguros. Precarga paquete/certificado y modo público solo en el marcador original vacío/desactivado de Turnos que apunta al repo esperado. No reemplaza configuraciones personalizadas ni activa la conexión. Se usa el término certificado autorizado para no confundir la autorización explícita de una firma de pruebas con una clave comercial permanente.

Activación: instalar web0.4.25 por GitHub/ZIP firmado, abrir Repositorios y publicaciones APK → Turnos → Configurar → Activar → Guardar → Buscar ahora. No introducir hashes inventados ni subir manualmente el APK. Se importa a Pruebas; la consulta periódica necesita el Cron previamente indicado. Conservar los datos al actualizar y no desinstalar por una discrepancia de firma sin revisar.

Pruebas de importación pública real en CI: firma RSA, paquete/certificado esperado, metadatos reales de la APK, versión local-test aceptada, archivo público SHA exacto, repetición sin duplicados, sin promoción automática y preservación de datos personalizados.

Límites: Turnos0.2.0 aún no integra tickets/publicidad con Digital Signage ni tiene buscador de actualizaciones dentro de la app. Publicación en catálogo no prueba la integración de visualización. Prueba en Fire físico, multicast y voz pendientes. Futuras versiones requieren sobres firmados compatibles y certificado autorizado; no está configurada la firma RSA automática en CI. No se ha instalado web/APK en el hosting/dispositivos del usuario desde esta sesión.
