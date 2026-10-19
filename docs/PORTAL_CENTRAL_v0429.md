# Portal central por empresa · Web 0.4.29

## Decisión de arquitectura

Una sola aplicación de pantalla: Digital Signage. Presenta Cartelera, Overlay, Signage + Turnos o Signage + Sports. Una pantalla no se enlaza simultáneamente a dos autoridades. Turnos conserva el motor de colas/tickets y Sports el motor de encuentros/reglamentos/puntos en sus repositorios. Los controles son clientes de esas autoridades. No se duplica ninguno de esos motores en PHP Signage.

Orden acordado: configurar en portal → conectar la APK de pantalla → conectar controles/kioscos/operadores. Esta versión entrega el primer paso. Los APK de Turnos y Sports publicados anteriormente **todavía no consumen** este nuevo catálogo.

## Disponible en el código de esta entrega

- Módulos Configurar Turnos y Configurar Sports, por empresa y con permisos propios en los mismos roles del portal.
- Turnos: salas, servicios, ventanillas, usuarios operadores, controles, tablets emisoras y pantallas vinculadas. Sin límite fijo de cuatro ventanillas o tres controles en el catálogo.
- Sports: sedes/clubes, pistas, torneos, equipos, encuentros, administradores, controles y pantallas. La referencia de reglamento pertenece a Sports; no se implementan reglas de puntuación en Signage.
- Identidad por empresa/aplicación, logo de biblioteca y preferencias de solicitud/aviso WhatsApp para Turnos. El aviso se muestra como **no conectado**, no como enviado.
- Una pantalla del catálogo Signage solo se vincula a una aplicación; las referencias de sala, sede, usuario, pantalla, equipo y torneo se validan en servidor.
- Revisiones de configuración con bloqueo transaccional y rechazo de formularios desactualizados. Guardar no significa que un APK lo haya recibido/aplicado.
- Claves de lectura API por empresa y aplicación, guardadas como hash, visibles una sola vez y revocables. La suspensión o pérdida de permisos del creador invalida su acceso. No van en URLs ni exportaciones.
- Usuarios y roles en contexto global o empresa seleccionada. SUPERADMIN crea usuarios en cualquier empresa con roles de esa misma empresa. Eliminación de usuarios salvo cuenta propia/SUPERADMIN; referencias activas bloquean su eliminación. Se pueden eliminar roles sin usuarios asignados.
- Grid compacto con miniaturas, iconos SVG para duplicar/eliminar, edición y selección múltiple conservadas.
- Modos de uso explícitos en creación/edición de listas: Cartelera, Overlay, Turnos y Marcador deportivo. Una misma lista puede habilitarse para más de un modo. Las listas anteriores conservan compatibilidad con los cuatro para evitar romper asignaciones. No se deduce el modo por el nombre del archivo.
- Asignación remota valida modo, orientación, formato y contenido; Overlay mantiene la restricción actual de imágenes. Quitar un modo de una lista asignada requiere reasignarla primero.
- Generador automático: selección múltiple, agrupación por orientación/formato, nombre con empresa/modos/formato/orientación, duración y transición comunes, separadores opcionales blancos/negros de al menos 0,1 s. No copia los archivos. Todas las listas del lote se guardan o se rechazan juntas.
- Biblioteca con cantidad de usos, filtro Nunca usados y mínimo/máximo. Cuenta renglones × repeticiones en el ámbito de empresa visible; no cuenta reproducciones reales ni multiplicaciones por listas padre.
- Separadores PNG globales sin empresa propietaria: blanco y negro en los diez formatos actuales; color hexadecimal bajo demanda, reutilizado por formato/dimensiones/color. No se pueden trasladar, reclasificar ni editar por empresa. Los archivos de medios existentes se conservan.
- Incluye los cambios previos 0.4.28: medios/listas compartidos según rol, restricciones por usuario y suspensión de servicio de pantallas con revisión solicitada/reportada separadas. La política de suspensión requiere APK Signage 0.4.18; un dispositivo desconectado no recibe una orden nueva hasta reconectar.

## API de configuración v1

`GET /api-application-config.php?app=turnos` o `app=sports` con `Authorization: Bearer ...`.

Dos identidades admitidas por este **servidor Signage**:

1. Clave de conexión de lectura creada en el módulo: empresa/aplicación fijadas en servidor, nunca por parámetro del cliente. Requiere que el creador siga activo y conserve los permisos de administración de esa aplicación y conexiones.
2. Token de una pantalla Signage: devuelve solo la sala/pista vinculada y sus datos públicos necesarios. No incluye operadores, directorio de usuarios, controles, kioscos, contraseñas ni claves. Rechaza pantallas suspendidas o sin vínculo activo. Esto no autoriza usar un token Signage arbitrariamente contra un futuro servidor Turnos/Sports: aquel intercambio de confianza sigue pendiente.

Respuesta: `schema_version`, `tenant_id`, `application`, `revision`, `updated_at` (UTC), `settings`, `entities` y `runtime` con estado explícito de conexiones pendientes. Los IDs del catálogo central son referencias; cada autoridad debe mapearlos al aplicar configuración.

Solo GET. No existen todavía aquí operaciones de emitir tickets, llamar, anotar puntos ni enviar WhatsApp. No se presentan los controles manuales antiguos de live-panels como motor multiempresa de producción.

## Pendiente de las fases siguientes

- Consumir revisiones y confirmar RECEIVED / APPLIED / EFFECTIVE en pantalla y controles; ACK formal de configuración v2.
- Adaptadores autorizados y autenticación entre portal y autoridades Turnos/Sports. Eventos idempotentes y replay del contrato de Turnos; eventos deportivos y oportunidades publicitarias deduplicadas. Objetivo 1–2 s sujeto a medición; no se ha medido en esta entrega.
- Operación offline completa con autoridad LAN, roles de emisor/operador, reconciliación y caché de configuración. Guardar un catálogo PHP no acredita ese funcionamiento.
- WhatsApp: proveedor, credenciales servidor, consentimiento, evento de llamado, resultado/reintentos; estas preferencias no hacen envíos.
- Programa Overlay que alterna franjas/lateral/completo con transiciones. El selector actual de formato por modo permanece; no se declara una secuencia multiformato ejecutable por las APK existentes.
- Mostrar IP y separar puerto administrativo 8787 de enlace Sports; investigar descubrimiento WiFi en dispositivos físicos.
- Corrección visual y prueba física de proporciones, números grandes, arranque/Overlay, control remoto y HUD en Fire OS/API22. No hubo acceso a un televisor físico.

## Actualización y aceptación

ZIP RSA acumulativo desde web 0.4.16, con migración 0.4.29 encadenada a anteriores. Instalar desde Actualizar desde GitHub o actualizador ZIP del SUPERADMIN; respaldo SQL/archivos y rechazo de firma/hash/versión incorrectos siguen obligatorios. Publicación GitHub no acredita instalación en hosting.

Probar primero con una empresa de ensayo: crear usuarios y roles de otra empresa; crear sala/servicios/ventanillas y pista/torneo/equipos/encuentro; vincular pantalla a una sola aplicación; exportar API con clave propia y comprobar que otra aplicación/empresa no es visible; revocar clave. Generar listas con varios formatos, tiempos decimales y separadores; comprobar miniaturas, modos y filtros de uso. No dar por realizada una llamada o punto en TV hasta completar la conexión y su prueba física.
