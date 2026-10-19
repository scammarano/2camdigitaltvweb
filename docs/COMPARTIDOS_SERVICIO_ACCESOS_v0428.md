# Web 0.4.28 y APK 0.4.18-debug

## Compartir sin copias

El permiso «Compartir medios y listas con otras empresas» habilita el checkbox «Disponible para otras empresas». Solo SUPERADMIN lo tiene automáticamente. Puede delegarlo mediante un rol; un administrador no puede conceder permisos que él no posee. El contenido conserva empresa propietaria, registros y archivo físico: las otras empresas lo consumen por referencia, sin copiar bytes. Los cambios del original se reflejan en los manifiestos posteriores.

La publicación es para todas las empresas autenticadas. Compartir una lista concede lectura de sus medios y sublistas alcanzables, sin abrir archivos ajenos a ese árbol. Las demás empresas tienen lectura y selección, sin edición, eliminación, transferencia ni republicación del original. Continúan los filtros de orientación/formato. El selector, la vista previa, las miniaturas, los horarios, las asignaciones y la descarga autenticada respetan este contrato.

Retirar el acceso se bloquea si existen referencias de otras empresas en listas, pantallas, programaciones o configuración deseada. No se revocan retrospectivamente bytes ya descargados en dispositivos offline. Duplicar sigue disponible para crear copias independientes; una copia nueva no hereda la publicación global del original.

## Servicio de una pantalla

«Pantallas → Suspender servicio / Reactivar servicio» requiere el permiso independiente «Suspender o reactivar el servicio de pantallas». El administrador base de empresa recibe este permiso una sola vez al migrar, si conserva edición de pantallas. No modifica sus medios ni revoca el emparejamiento.

Cada solicitud tiene revisión, motivo, UTC y usuario solicitante. Guardar queda PENDIENTE; solo una confirmación explícita del reproductor para la misma revisión/estado registra lo reportado. El servidor entrega manifiesto vacío y rechaza descargas de medios para pantallas suspendidas, conservando su canal de control.

APK 0.4.18 consulta el estado aproximadamente cada 15 s, sujeto a conexión y cola de red. Guarda la suspensión y la mantiene al reiniciar/offline para el mismo emparejamiento. No se exporta ni cambia mediante ajustes locales/LAN. Detiene el deck y las ventanas Overlay antes de informar la aplicación del bloqueo. La reactivación vuelve a cargar el contenido. El reproductor web también consulta el estado.

Una pantalla desconectada no recibe nuevas órdenes: la suspensión permanece pendiente hasta reconectar. Las APK antiguas no tienen este bloqueo persistente ni su confirmación; pueden continuar con caché offline. Es necesario actualizar a 0.4.18 para el control completo. Este ACK de servicio es específico; el ACK formal de configuración v2 sigue pendiente.

## Usuarios

En «Usuarios → Editar», el rol define los permisos de partida y los checkboxes «Bloquear» restringen módulos/acciones para ese usuario. Nunca amplían el rol. «Usuario activo» permite desactivar por completo: se valida al iniciar sesión y en las solicitudes posteriores, invalidando sesiones ya abiertas. No se permite bloquear al SUPERADMIN, desactivarse a uno mismo ni quitarse la administración de usuarios.

## Validación

CI: PHP/MySQL, referencias sin copias, lectura compartida y aislamiento de archivos privados, propiedad de escritura, restricciones y sesiones, revisión de servicio solicitada/confirmada y HTTP autenticado de suspensión/reactivación, manifiesto vacío y descarga 403.

Android: pruebas API22 de persistencia de la suspensión, rechazo de revisiones antiguas/conflictivas, imposibilidad de anularla desde ajustes y bloqueo/reanudación de ventanas Overlay. Conserva API19+, Sports LAN, cuatro modos, HUD, firma permanente y décimas de segundo. La prueba física sigue pendiente. No se despliega automáticamente en hosting ni dispositivos.
