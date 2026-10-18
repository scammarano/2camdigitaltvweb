# Portal 0.4.26 — listas, medios y empresa de trabajo

- Las listas aparecen colapsadas. Abrir una lista permite ver el contenido; «Modificar contenido» habilita duración en segundos, repeticiones, efecto y duración propia del efecto en el grid. Guardar valida todos los renglones dentro de una transacción, comprueba empresa y rechaza una edición obsoleta. Cancelar restaura los valores. Se conservan flechas de orden y se ocultan los índices internos 10/20/30.
- Duración: 1–86400 s; repeticiones: 1–30; duración de efecto: 0–3000 ms (límite vigente del portal); vacío hereda de la lista. Las duraciones de video y las sublistas mantienen su semántica de reproducción existente. No se incluyen controles de zoom adicionales en este cambio.
- Selector con grupos explícitos de medios y listas compatibles, contador de inclusiones directas en la lista actual y permiso de repetir elementos. Las listas vacías se muestran deshabilitadas; las circulares se excluyen. Para compartir publicidad hay que crear y poblar una sublista por empresa/orientación/formato compatible. No se reutiliza contenido de otras empresas por referencia: se puede duplicar una lista a otra empresa.
- Separadores blancos: PNG opaco real, generado sin GD con dimensiones centrales del formato. Se puede crear en biblioteca o añadir a una lista con duración inicial de un segundo; se reutiliza el medio idéntico dentro de esa empresa. Se valida permiso de medios, empresa y formato. El reproductor lo trata como cualquier imagen, también offline.
- Biblioteca: tamaño real y tamaño del formato visibles; alerta si la proporción no coincide. Asistente: medidas en cada opción de presentación y diagrama con dimensiones reales/de formato en revisión del lote. No se reduce el tamaño general del asistente.
- Empresa: «Trabajar con esta empresa» se aplica por defecto a listas, pantallas, biblioteca, programación de contenido/modos y demás pantallas que usan company_scope. SUPERADMIN puede pulsar «Ver todas» en cabecera o Empresas para quitar el contexto. Es una operación POST con CSRF. Un filtro explícito por página sigue disponible; los usuarios de empresa no pueden salir de su ámbito.
- Menú lateral ocultable tanto en escritorio como en móvil; preferencia de escritorio guardada localmente.
- Actualización acumulativa desde 0.4.16. Conserva configuración, medios, importadores de APK y firma RSA; respaldo mediante el actualizador existente. No cambia la APK ni instala automáticamente en hosting. ACK formal v2 e integración completa Turnos siguen fuera del alcance de esta versión.

## Comprobación en instalación

1. Elegir empresa A; visitar listas, medios, pantallas y ambas programaciones sin parámetros: solo A. Cambiar a B y repetir. Pulsar Ver todas y comprobar el conjunto global (solo SUPERADMIN).
2. Abrir una lista, Modificar contenido, cambiar los cuatro campos en varios renglones, guardar y recargar. Cancelar debe restaurar. Dos pestañas: guardar en una y luego en otra debe rechazar la segunda edición obsoleta.
3. Añadir dos veces un mismo medio y una sublista compatible poblada; comprobar contadores, orden y reproducción expandida. Comprobar ausencia de listas de otra empresa/formato y rechazo de ciclos.
4. Añadir separador blanco, ajustar segundos y previsualizar. Validar tamaños horizontal/vertical de los cinco formatos, con los porcentajes centrales actuales.
5. Importar varios archivos: cada opción muestra medidas y la revisión incluye pantalla, zona y dimensiones. Biblioteca conserva miniatura y muestra dimensiones reales y de formato.
6. Ocultar/mostrar menú en escritorio y móvil, comprobar teclado y que el grid permite desplazamiento horizontal si no cabe.
