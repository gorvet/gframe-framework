# Contadores en base de datos

## Ampliación solicitada

El usuario exige que ningún módulo persista datos de ejecución fuera de base de datos. Se eliminan las alternativas de archivos, incluidas las heredadas. Los logs pueden seguir en archivos (confirmación explícita del usuario). La configuración y los archivos de contenido conservan su naturaleza de archivos; los JSON dentro de columnas SQL no son archivos de persistencia.

El usuario confirmó después que los usos actuales de localStorage deben conservarse. No se han modificado los borradores locales del editor, las preferencias visuales ni la coordinación de pestañas. La regla de persistencia en base de datos se aplica a los datos de ejecución del servidor, con las excepciones anteriores.

Aclaración posterior del usuario: imágenes y documentos siempre mediante el módulo Media, sin almacenes paralelos; logs en archivos permitidos; borradores con un patrón similar a WordPress. Se contrastó la documentación oficial de WordPress y el contrato existente: content-autosave ya ofrece una copia privada SQL por usuario/tenant/recurso, y el editor una recuperación local explícita. No se cambian las etapas 06/07 cerradas ni se introduce otra implementación. Se precisaron la política de persistencia, la guía de Media y sus skills. El inventario de escrituras del framework sitúa las escrituras de contenido en MediaLibraryService/MediaProcessor; las restantes corresponden a logs, configuración o distribución. Las utilidades de descarga no escriben los bytes del contenido.

Verificación de esta aclaración: MediaDocumentationTest, ContentAutosaveTest y MediaWritingTest, 5 pruebas y 72 aserciones sin fallos; skills 14/14 y 80 enlaces locales; git diff --check sin errores. No se alteró el JavaScript del editor ni se activaron endpoints de aplicaciones instaladas.

Superficies adicionales: sesiones, autorizaciones de descarga y payloads Async. Skills canónicas adicionales: skills/gframe-background-jobs y skills/gframe-core-architecture. Verificar transacciones y concurrencia, revocación y caducidad, ejecución del worker, sesiones anónimas y autenticadas, migraciones e instalador. Los metadatos de distribución/instalación y configuración no son almacenamiento de datos de módulos. La base auxiliar SQLite de cron es una base SQL real, no JSON ni TXT.

Trabajo solicitado: persistencia nativa de AuthRateLimiter y MailRateLimiter, sin archivos en la configuración de producción. No se modifican aplicaciones instaladas.

Skills canónicas aplicadas: skills/gframe-orchestrator, skills/gframe-framework-maintenance, skills/gframe-auth-access, skills/gframe-notifications-mail y skills/gframe-orm-models. No hay una etapa activa para este cambio.

Comprobaciones previstas: ventanas y retry_after, reserva conjunta IP/identidad, concurrencia real MySQL, devolución de reservas de correo, limpieza, errores de almacenamiento, migración repetible e instalación nueva. Las pruebas del almacenamiento anterior se adaptaron a SQL y se comprueba que los drivers de archivos sean rechazados.

Versión prevista: 5.0.0, sin etiqueta ni publicación. El nuevo almacenamiento predeterminado exige esquema SQL y constituye un cambio de comportamiento para consumidores anteriores.

## Evidencia de verificación

Resultado final de la ampliación: suite completa de 633 pruebas, 6222 aserciones, sin errores ni fallos, 8 omitidas, en PHP 8.3.35 con SQLite y MariaDB 10.4.24. Se ejecutó con una base MySQL temporal seleccionada y eliminada al terminar. Las 54 pruebas dirigidas anteriores también pasaron (456 aserciones, una omitida). Incluyen estado SQL, tareas grandes en otro proceso, descargas concurrentes, sesiones públicas y autenticadas, CSRF, cron e instalador.

Lint completo final: 539 archivos válidos. Skills: 14 válidas, 80 enlaces locales. git diff --check sin errores de whitespace. La guía de persistencia no presenta errores editoriales críticos. Se verificó mediante git diff que no hay cambios en los JS de localStorage del editor, panel o heartbeat. No hubo publicación ni modificaciones de aplicaciones instaladas.

### Comprobaciones anteriores a la ampliación

- PHP 8.3.35 y MariaDB 10.4.24 local. Las pruebas MySQL crean y eliminan esquemas de prueba aislados; SQLite usa memoria para el backend nuevo.
- Suite dirigida después de incorporar el timeout SQL: 40 pruebas, 329 aserciones, sin fallos. Incluye ventanas, reservas concurrentes en ocho procesos, migración e integración MailService. Comprobación final del backend SQL: 12 pruebas, 156 aserciones, sin fallos, incluida contención con timeout, rechazo seguro y restauración de la configuración de espera de la conexión.
- Lint completo: 535 archivos PHP válidos. Lint individual posterior del almacenamiento SQL y las pruebas nuevas, válido.
- Skills: 14 válidas y 80 enlaces locales comprobados. git diff --check sin errores. Revisión editorial de la guía sin errores críticos; avisos correspondientes a la sintaxis de los ejemplos PHP.
- Suite completa: 626 pruebas, 6119 aserciones, 8 omitidas y 2 advertencias; una incidencia de DSN sin base seleccionada en OrmBooleanBindingsTest y un fallo de cabecera en VirtualCronHttpTest. OrmBooleanBindingsTest pasó después con un esquema temporal seleccionado (2 pruebas, 30 aserciones); VirtualCronHttpTest pasó al repetirlo aisladamente (1 prueba, 22 aserciones). No se afirma una ejecución completa limpia.
- PHPStan no ejecutado: en este checkout no están disponibles packages/bin/phpstan ni su configuración. No se alteraron dependencias para instalarlo.
- Sin envíos SMTP reales, publicación, etiqueta ni modificaciones de proyectos instalados.
