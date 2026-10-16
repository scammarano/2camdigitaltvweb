# Interfaz de playlists — transiciones y actualización en caliente

**Pendiente de implementar cuando se importe la fuente PHP/MySQL publicada.** No copiar vistas antiguas sobre producción.

## Ayuda contextual en formulario Nuevo/Editar renglón
- Corte: cambia sin animación; 0 ms.
- Disolución (fade): mezcla visual; sugerir 500–1200 ms.
- Deslizamiento a izquierda/derecha: entrada lateral; sugerir 500–1000 ms.
- Zoom: movimiento sobre imágenes durante la exposición; añadir tres parámetros: `zoom_percent` (0–60, default 20), `zoom_direction` (in/out, default in) y `zoom_duration_ms` (300–20000, default 4000). Mantener `transition_ms` para disolución de entrada.
- El formulario debe indicar unidades y presentar un *preview real* sin guardar ni programar anuncios.
- Si `zoom_duration_ms` supera la exposición en segundos `duration * 1000`, avisar y mostrar duración efectiva; no prolongar imágenes a escondidas.
- Presets: zoom suave 20 %, in, 7 s; dinámico 28 %, out, 4.8 s.
- Permitir editar transición por renglón y en lote, con sobrescrituras explícitas.

## Flujo de publicación
Cuando el usuario guarda playlists o reglas de programación:
1. Guardar definición con control optimista de edición/versiones.
2. Resolver dispositivos/grupos afectados en ámbito de la empresa.
3. Incrementar la revisión de sincronización deseada para esos dispositivos.
4. Mostrar `Enviado`, `Recibido`, `Aplicado`, `En ejecución` por dispositivo con hora y errores. No afirmar `En ejecución` solo por ACK de config.
5. Botón `Sincronizar pantallas ahora` para reenviar la revisión, idempotente y auditable.

La APK experimental `feat/player-playlists-remote-v0412` compara el manifiesto seleccionado incluyendo transiciones y puede consultar cada ~15 s en DEBUG. Los parámetros del zoom son opcionales para compatibilidad; sin `zoom_duration_ms`, estima 4× `transition_ms` con mínimo de 1800 ms.

## UX
Conservar listados CRUD a ancho completo, formularios en modal/vista dedicada; validar duplicados antes de guardar.
