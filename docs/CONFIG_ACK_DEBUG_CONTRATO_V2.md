# Contrato configuración / ACK / DEBUG — Digital Signage

*Fecha: 15-10-2026. Trabajo preparatorio; no es un despliegue web.*

El código Android v0.4.10 está en `digital-signage-android:feat/sports-direct-lan`.
La instrumentación experimental está en `feat/config-ack-debug-v0411`.

## Auditoría
- En Android, ajustes APK y página LAN comparten `SettingsStore.publicSnapshot()/validateSettings()`.
- El PHP histórico 0.4.8 manejaba 23 campos. **No se ha verificado el código web vigente** porque este repositorio solo contiene documentos y su README cita la fuente 0.4.17 pendiente de importar.
- La API actual `api-device-config.php` conoce `applied`. No distingue bien recepción, persistencia y ejecución del modo.

## Esquema único (propuesta v2)
Conservar las 23 claves heredadas y agregar `debug_hud_enabled` (bool, false),
`debug_message_seconds` (int 2–15, default 5) y `debug_verbosity`
(`basic` o `verbose`). **No enviarlas a la API v1**.

## Estados
`SENT` (portal publicó revisión) → `RECEIVED` (APK la descargó) →
`APPLIED` (APK validó y guardó) → `EFFECTIVE` (componente ejecuta modo).
Alternativas: `REJECTED`, `BLOCKED`, `DEGRADED`, `PENDING`.
Añadir tabla inmutable de eventos con tenant_id, device_id, event_id,
revision, desired_hash, actual_hash, app_version, active_mode, state,
timestamps UTC y error. Idempotencia por device_id+event_id.
No exponer credenciales ni PIN.

## Pantalla de administración
Lista a ancho completo con Empresa/Pantalla, APK, modo deseado/efectivo,
revisión deseada/recibida/aplicada/efectiva, último estado, latencia,
última conexión, detalles y logs exportables. Botones de prueba:
Enviar configuración, Ping, Sincronizar, activar DEBUG, Ver historial.
Auditar permisos tenant y token de dispositivo en todos los endpoints.

## Sincronización
La rama Android experimental consulta configuración cada 5 s solo en DEBUG,
genera mensajes cortos y deja eventos en los logs del portal existente.
Para baja latencia de Turnos/Sports usar eventos LAN/WebSocket, no el poll.

## Bloqueo de despliegue
Antes de emitir ZIP de producción, importar el PHP/MySQL vigente al repositorio
y comprobar con pruebas que las tres UI usan exactamente el mismo schema.
El modelo de pruebas debe mostrar falta de confirmación como PENDIENTE.
