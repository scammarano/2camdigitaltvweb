# Formatos centrales de medios — web 0.4.20 / APK 0.4.15

Los porcentajes de franja inferior y lateral pertenecen a los formatos de medios de la instalación. Se administran en **Medios → Formatos de medios**, exclusivamente por SUPERADMIN. Todas las empresas y pantallas utilizan esa definición; no hay cambios locales desde la TV o LAN.

Valores iniciales: inferior 20% del alto, lateral 40% del ancho. Se conserva así el formato de los artes existentes, no los antiguos valores independientes 25%/30% de la APK. Media pantalla permanece al 50%; pantalla completa al 100%.

La base contiene una fila central con revisión. Cada manifiesto autenticado incluye `media_formats`, que la APK conserva en su caché offline. Cambiar esos valores cambia la definición de reproducción y provoca la actualización en caliente. Requiere instalar APK 0.4.15 o posterior; una APK anterior conserva su comportamiento antiguo.

Los seis campos de porcentajes por modo se retiran del contrato de pantalla. Los tres editores usan el mismo esquema de 43 campos; al leer respaldos/configuración anterior se descartan esos seis valores. No se borran medios ni se redimensionan archivos.

Antes de cambiar un formato se comprueban las dimensiones de todos los medios asociados, en todas las empresas. Se rechaza el cambio si algún medio no es proporcional o sus dimensiones son desconocidas. Las cargas vuelven a comprobar el formato dentro de la transacción. Los editores muestran las mismas medidas centrales y las vistas previas derivan su geometría de esa definición.

El paquete web es acumulativo desde 0.4.16 e incluye los accesos del catálogo 0.4.17 y del actualizador GitHub 0.4.18. Configuración privada, tokens, APK publicadas y medios del servidor se conservan. La migración es aditiva y reutiliza la migración 0.4.19 para limpiar ajustes heredados.

La publicación de una release permite descargar el ZIP desde el portal; no instala automáticamente en hosting ni en el Fire Stick. La prueba física de reproducción y reinicio en Fire OS sigue pendiente del equipo real. El ACK formal v2 sigue pendiente y no se presenta como concluido.
