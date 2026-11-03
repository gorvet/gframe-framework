# Almacenamiento de ejecución

El módulo técnico `runtime-storage`, disponible desde GFrame 5.0.0, guarda en SQL los payloads de [Async](async.md), las autorizaciones de [descarga privada](archivos-privados.md) y las marcas temporales de diagnóstico de cron. Incluye el esquema de sesiones SQL para formularios públicos sin tablas de usuarios.

Es dependencia de `rate-limits` y `cron-runner`. Para usarlo por separado, añádelo a la lista completa de módulos mediante el actualizador del proyecto. Su migración es `202611030002_runtime_storage.sql`, con versiones MySQL/MariaDB y SQLite. La conexión se configura mediante `storage.connection`; null selecciona la predeterminada.

No contiene archivos de imágenes ni documentos: esos recursos pertenecen a [Media](media-library.md). No utiliza archivos JSON o TXT ni vuelve a ellos cuando falla SQL.

Consulta [Persistencia de datos de los módulos](persistencia.md) para instalación, compatibilidad, caducidad y limpieza, y [Sesiones](sesiones.md) para configurar su conexión y driver.
