# Persistencia de datos de los módulos

Desde GFrame 5.0.0, los módulos del servidor guardan sus datos y estados en una base de datos real. No utilizan archivos JSON, TXT, CSV ni hojas de cálculo como almacén, ni cambian a archivos si falla la base de datos.

| Datos | Almacenamiento |
| --- | --- |
| Usuarios, permisos, contenido, notificaciones, campañas, cron, autoguardado remoto | Tablas SQL existentes |
| Sesiones | SQL por defecto o Redis; `native` se rechaza |
| Contadores de autenticación y correo | Tablas del módulo `rate-limits` |
| Autorizaciones de descarga, tareas Async y marcas de diagnóstico de cron | Tablas del módulo `runtime-storage` |
| Logs | Pueden seguir en archivos |
| Imágenes, documentos y sus temporales de procesamiento | Exclusivamente mediante `media-library`; originales y variantes en su almacenamiento, registro y metadatos en SQL |
| Opciones y credenciales | Configuración PHP y `.env` |

Ningún módulo debe crear su propio almacén de imágenes o documentos. Las subidas, importaciones y archivos generados que deban conservarse se incorporan mediante `MediaLibraryService`, con ámbito, autoría y visibilidad resueltos en el servidor. El módulo consumidor conserva el ID del medio. Las utilidades de entrega privada sirven esos recursos; no son una alternativa para guardar archivos por fuera de Media. Los temporales propios de la carga y del procesamiento pertenecen al flujo de Media.

SQLite es una base de datos SQL aunque su almacenamiento físico sea un archivo `.sqlite`. El archivo auxiliar del bloqueo de cron también es una base SQLite real, no un registro de texto. No debe eliminarse mientras hay workers en ejecución. MySQL/MariaDB permite compartir el estado entre servidores.

Los manifiestos de módulos, la configuración de instalación y las huellas de archivos administrados son metadatos de despliegue. El registro `storage/gframe-installed.json` no almacena usuarios, tareas, contadores ni datos de negocio. No se cambia su formato bajo esta política.

## Incorporación a una aplicación

1. Actualiza el paquete mediante Composer cuando se publique la versión y ejecuta el actualizador del proyecto, primero con `--dry-run --preserve-custom`.
2. Conserva la lista completa de módulos. `auth-ui` incorpora `rate-limits`, que depende de `runtime-storage`; `cron-runner` también incorpora `runtime-storage`. Para usar Async o descargas sin esos módulos, añade `runtime-storage` expresamente a la lista completa.
3. Aplica `202611030001_rate_limits.sql` y `202611030002_runtime_storage.sql` según los módulos utilizados. El actualizador ejecuta las migraciones registradas; no uses `--no-database` cuando falte su esquema.
4. Configura `storage.connection` para Async y autorizaciones de descarga, `session.connection` para sesiones y las conexiones de [los contadores](rate-limits.md). Null selecciona la conexión predeterminada. Si son conexiones distintas de la del actualizador, ejecuta sus migraciones también en ellas.
5. Usa `SESSION_DRIVER=database` o `redis`. Un sitio estático sin base de datos usa `disabled`; no puede ejecutar funciones que necesiten sesiones o almacenar tareas sin configurar antes la base de datos.

No parches el paquete instalado. Detén los disparadores y deja terminar los workers antiguos antes de actualizar. No se importan automáticamente contadores, tareas temporales ni autorizaciones antiguas de archivos; los enlaces externos antiguos deben volver a emitirse. Las sesiones nativas antiguas tampoco se recuperan y será necesario volver a iniciar sesión. Conserva los archivos antiguos hasta verificar la transición; el framework no los borra.

## Caducidad y mantenimiento

Los contadores se limpian con sus propias operaciones y API de mantenimiento. Async registra cada tarea durante una hora como máximo hasta que el worker la consume. Las autorizaciones usan la caducidad elegida al emitir el enlace; `ttl_seconds=0` conserva autorizaciones sin caducidad automática. Las tareas nuevas y las autorizaciones nuevas limpian registros vencidos de su conexión.

Para limpiar también durante períodos sin actividad, el mantenimiento programado puede llamar a `GFrame\Storage\DatabaseStateStore::configured()->prune()`. Solo elimina registros de ejecución caducados, no documentos ni contenido publicado. SQL indisponible implica un fallo de la operación protegida; los logs siguen disponibles para diagnosticarlo.

## Almacenamiento del navegador

Esta política del servidor no convierte automáticamente todo uso del navegador en peticiones a la base de datos. Cada caso debe evaluarse por su finalidad:

- El editor puede guardar una copia local del HTML sin guardar, su contenido inicial y fecha, si se configura `draft_key`. Es recuperación local ante cierre, recarga o falta de conexión; límite de 1 MiB y revisión de caducidad de 7 días. No es publicación ni el autoguardado remoto.
- `content-autosave` guarda por separado una copia privada en `content_autosaves`, asociada al usuario y al contenido, mediante una ruta autorizada.
- Tema claro/oscuro y menú contraído son preferencias visuales del dispositivo.
- Heartbeat y cierre de sesión usan señales entre pestañas para coordinación; no contienen el registro de usuario ni sustituyen la sesión del servidor.

Estos mecanismos del navegador se conservan. Su retirada o sustitución requiere revisar cada caso y sus efectos, especialmente la recuperación sin conexión.

Para los borradores se sigue el patrón de autoguardado en el servidor más recuperación local usado por [WordPress](https://wordpress.org/documentation/article/revisions/#autosaves): una copia privada por usuario y contenido, actualizada sin publicar, y una copia del navegador para recuperar cambios ante fallos de conexión o cierre. En GFrame esas capacidades ya existen en `content-autosave` y en la recuperación local del editor; la aplicación debe integrar ambas para el recurso autorizado. La copia local no confirma un guardado del servidor, y el autoguardado no sustituye Guardar/Publicar. No se adopta por ello el esquema de tablas de WordPress ni se presupone que GFrame conozca las tablas de contenido de cada aplicación.
