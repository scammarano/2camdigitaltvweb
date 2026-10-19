# Web 0.4.27 / APK 0.4.17-debug

- Cada renglón incluye miniatura de imagen, previsualización de video cargada al abrir la lista, o acceso a la sublista. «Duplicar» aparece junto a «Eliminar» y copia todos los ajustes inmediatamente después del original.
- Selección por renglón y seleccionar todos. Duplicar/eliminar seleccionados se valida y confirma en servidor, dentro de una transacción, con comprobación de empresa, lista y revisión de los renglones. Se conserva el orden y no se borran los archivos de la biblioteca.
- «Editar seleccionados» habilita solo esos renglones y ofrece valores comunes de duración, repeticiones, efecto y duración del efecto. Vacío/Sin cambiar conserva los datos. «Aplicar valores» prepara la edición y «Guardar contenido» la persiste de forma atómica.
- El separador blanco está en el combo de medios, sin botón adicional en la lista. Al seleccionarlo propone 0,1 s y corte inmediato. Se puede ajustar a cualquier duración válida antes de añadirlo. El medio generado se reutiliza dentro de la empresa y formato.
- Duración en pasos de 0,1 s, mínimo 0,1 y máximo 86400. La columna SQL pasa a DECIMAL(11,1), conservando los enteros antiguos. Manifiestos, duplicación, vista previa y reproductor web conservan las décimas.
- APK 0.4.17 interpreta los segundos como decimal, convierte a milisegundos y limita la transición a la mitad de la exposición. Overlay respeta la duración del medio como límite adicional a la duración de aparición configurada; usa temporizador preciso y acorta la entrada en medios breves. La periodicidad de Overlay conserva su configuración independiente. Los videos mantienen su reproducción natural.
- La reproducción de décimas requiere actualizar la APK: versiones previas las redondean. Conserva API19+, Sports LAN, HUD, cuatro modos, controles remotos y certificado Android permanente utilizado en las APK 0.4.14–0.4.16 entregadas. No garantiza instalación silenciosa ni sustituye pruebas físicas en Fire Stick.
- ZIP acumulativo desde web 0.4.16, firmado RSA y publicado mediante el flujo existente. No se modifica automáticamente el hosting ni se instala en dispositivos. El ACK formal v2 sigue pendiente.

## Validación

CI PHP/MySQL: migración idempotente, décimas en SQL y manifiesto, duplicar/eliminar por selección, conservación de campos y orden, rechazo de referencias ajenas y ediciones obsoletas, rollback, POST reales de renglones/separadores, contexto de empresa y actualizador firmado.

Navegador: miniaturas autenticadas, selección múltiple, valores comunes de 0,1 s, serialización del grid, selector de separadores, menú y biblioteca.

Android: compilación CI y pruebas en emulador API22 con temporizador real de imagen de 100 ms y ventana Overlay breve, además de las comprobaciones existentes de Sports, permisos y arranque. La prueba física de TV queda por ejecutar después de instalar.
