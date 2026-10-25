# Registro de cambios

## 4.4.1 — 2026-10-25

- La publicación programada rechaza modelos incompletos antes de consultar contenido y devuelve el error de configuración correspondiente.
- El cifrado comprueba que la etiqueta de autenticación generada tiene los 16 bytes requeridos antes de guardar el contenido.

## 4.4.0 — 2026-10-24

- ORM: `transaction()` garantiza rollback ante excepciones y respeta transacciones externas; `reconnect()` rechaza conexiones con transacción activa, incluidas las abiertas desde `DatabaseManager`. El historial SQLite reintenta las transacciones ante conflictos de bloqueo, conservando el control de revisión.
- Registro: niveles configurables, contexto y redacción de secretos; checklist de despliegue y documentación actualizados.

- Seguridad: reservas atómicas por IP/identidad en autenticación, hash bcrypt de relleno, proxies de confianza y conservación de políticas de cookie existentes.
- Instalación: comprobación MySQL con un plazo integral de cinco segundos mediante PHP CLI y `proc_open`.
- HTTP: solo protocolos HTTP/HTTPS, incluidas redirecciones; cabeceras conservadoras configurables y rechazo de canales de ruta incoherentes antes de ejecutarlos (`system` conserva web/AJAX).
- Correo de acceso: cola existente cuando está instalada, con descarte de tokens caducados/sustituidos; alternativa SMTP síncrona sin procesos por mensaje para proyectos sin el módulo de cola.

- Seguridad: el JSON-LD escapa los caracteres que permiten cerrar su bloque script desde contenido dinámico. Los enlaces de verificación y recuperación exigen una URL pública configurada y no utilizan el Host de la petición; configura APP_URL antes de actualizar aplicaciones que dependían de la URL automática.
- Seguridad: el instalador rechaza GET y POST, incluido el preflight, cuando existe configuración aunque falte el lock. La recuperación mantiene una respuesta genérica si el correo no puede lanzarse y elimina el contenedor de datos vacío que distinguía cuentas existentes.
- Seguridad (ORM): `where`, `orWhere`, `whereIn`, `whereNull`/`whereNotNull`, `whereBetween`, `whereLike`/`whereAnyLike`, `having`, `join` y `orderBy` validan sus identificadores de columna/tabla (identificador seguro, un punto opcional) y el operador contra una lista blanca; un argumento no literal lanza excepción en lugar de concatenarse al SQL. Las expresiones siguen en `select`, `groupBy`, `orderByExpr` y `whereRaw`, que requieren entrada propia del código. Cambio de contrato: resuelve las columnas de ordenación/filtrado dinámicas con una lista blanca antes de pasarlas al ORM.
- Seguridad (sesión): `SessionRuntime` fija las banderas de la cookie antes de iniciar la sesión con valores seguros por defecto (`HttpOnly`, `SameSite=Lax`, `Secure` automático bajo HTTPS) y `use_only_cookies`, configurables con las nuevas claves `session.cookie_*`. Antes dependían del `php.ini` del despliegue.
- Seguridad (redirección): `Router::pick()` normaliza las barras invertidas antes de validar el destino de redirección, cerrando el rodeo `/\host` que algunos navegadores interpretan como protocolo-relativo hacia un sitio externo.
- Seguridad (CSRF): caducidad opcional del token para sesiones de invitado (formularios públicos) mediante `security.csrf_guest_max_age` (segundos; `0` lo desactiva). Para usuarios autenticados el token sigue caducando con la sesión (logout o inactividad), sin temporizador propio que fuerce un 403 a mitad de una operación. Un token de invitado caducado se rota y devuelve `to_reload`.

- Skills: manifiesto de complemento en la raíz y distribución con sus metadatos. Instalación local opcional en Codex y sincronización después de actualizaciones con Git, con conservación de hooks, versiones por contenido y comprobación de hashes; sin tareas periódicas.

## 4.3.3 — 2026-10-22

- Cron: `CronScheduler` interpreta `scheduled_at` explícitamente en UTC al calcular la recurrencia y `CronTaskService` valida las fechas con la misma zona. Conserva la zona del proyecto y evita los aplazamientos adicionales de cuatro o cinco horas con `America/Havana`. Sin migraciones; las tareas ya aplazadas requieren reprogramación selectiva.
## 4.3.2 — 2026-10-20

- La ayuda ilustrada pertenece íntegramente al paquete: guías Markdown, imágenes, esquemas y pies con enlaces relativos en `docs/assets/`, inventario canónico y herramientas de reproducción en `maintenance/help/`. El sitio documental consume la misma fuente para texto y recursos, sin copias independientes.
- Ampliados los recorridos de instalación y personalización, ORM, sesiones y permisos, API y HTTP, búsqueda, multimedia, correo, campañas, cron, SEO y metas. Incluye 31 recursos visuales utilizados en 28 guías y distingue las capacidades nativas de las integraciones de ejemplo. Sin cambios de API ni migraciones.

## 4.3.1 — 2026-10-20

- La restauración del historial con `ScheduledContent` conserva el estado y la fecha actuales: no publica ni recupera programaciones antiguas desde un snapshot. Incluye regresión combinada y actualización de contratos y skills; se corrige también la restricción del generador indicada en la guía de skills.

## 4.3.0 — 2026-10-19

- El generador de proyectos solicita GFrame `^4.3`.

- Publicación programada optativa con `ScheduledContent` y `ScheduledPublicationCronHandler`: fechas UTC, reprogramación, cancelación y escritura condicional por estado/fecha/revisión, compatible con historial y papelera. Reutiliza cron recurrente y autorización del proyecto.

- Papelera optativa para modelos versionados mediante `ContentTrash`: consultas de activos/eliminados, restauración por revisión y borrado definitivo confirmado con limpieza SQL transaccional. La aplicación conserva tablas, permisos, relaciones e integración de lectores y escritores.

- Vista previa privada optativa con la plantilla pública, reutilizando autoguardado y actualización automática por versión confirmada. El botón propio del editor sustituye la preview TinyMCE predeterminada; sin URL de integración no se muestra. Barra pública administrativa aplazada.

- Historial optativo `content-revisions`: 10 revisiones por defecto, límite configurable, comparación escapada, restauración con revisión nueva y vaciado confirmado por contenido sin borrar el estado actual. Guardado, snapshot y retención comparten transacción mediante modelos ORM por herencia.

- Guardado optativo por revisión mediante modelos ORM por herencia y actualización condicional por registro/ámbito. Módulo `content-editing` para avisar de otras sesiones activas, con caducidad, MySQL/SQLite y componente nativo. La aplicación conserva sus tablas, migraciones y permisos; no se activa en sitios existentes automáticamente.

## 4.2.0 — 2026-10-16

- Header: restauradas las etiquetas dinámicas Open Graph y Twitter Card, autor, palabras clave y permisos de vista previa. Conserva los scripts `hjs` antes del CSS, la política de indexación y la persistencia del panel. Incluye pruebas de renderizado de header y footer.

- Correo: adjuntos locales o generados en memoria para HTML y plantillas, tanto síncronos como Async, con validación, límites y copia de bytes antes del lanzamiento asíncrono.

## 4.1.0 — 2026-10-15

- Autoguardado privado optativo en servidor: módulo content-autosave con MySQL/SQLite, control de versión, recuperación explícita y coordinación con publicación; no crea artículos ni cambia contenido público automáticamente.
- Escritura: protección de salida independiente del estado dirty, borradores locales optativos por ámbito con recuperación explícita y aislamiento entre pestañas, snapshots para confirmar guardados sin perder cambios posteriores, concentración y esquema por títulos. Documentados límites del navegador e integración del guardado.

- Escritura: formatos de texto contextuales, menú «/» para párrafos, títulos, listas, citas y separadores, contador de palabras, herramientas secundarias agrupadas, acciones contextuales de imágenes/galerías y acceso opcional al botón de guardar del formulario mediante toolbar y Ctrl/Cmd+S. Conserva validación, permisos y flujo de envío de la aplicación; quitar del texto no borra medios de la biblioteca.

- Escritura: TinyMCE mantiene una altura inicial estable y desplaza los artículos largos dentro del editor, sin expandir todo el formulario ni arrastrar su scroll con la rueda en los extremos. Conserva redimensionado manual y personalización por instancia; `autoresize` pasa a ser una elección explícita.

- Biblioteca en escritura: integración opcional de TinyMCE para insertar/sustituir imágenes y componer/reeditar galerías ordenadas mediante el selector existente. Fragmentos PHP estrictos por ámbito y tipo, conservación del cursor y undo/redo, y marcadores HTML acotados en el sanitizador. Sin cambios de esquema ni almacenamiento de artículos.

- Biblioteca multimedia: vista de lista, ordenación con filtros y URL, navegación de detalles entre páginas, nombre visible y texto alternativo separados, y reproductores nativos de audio/vídeo sin reproducción automática. Sin cambios de esquema.

- Biblioteca multimedia: progreso por archivo, selección de nuevas subidas, atributos dentro del selector y composición ordenada mediante fragmentos PHP. Conserva permisos, ámbitos y modos de campos existentes; descarta resultados antiguos de consultas y reaperturas.

- Ejecutor HTTP autenticado para cron real o virtual, seleccionable mediante GFRAME_CRON_EXECUTOR; el modo real ya no registra callbacks por visitas. Publicación del endpoint sin sesión y transporte virtual sin exec.

- El aviso de subida de medios utiliza texto sin etiquetas HTML, conforme al contrato de `alertToast`, para no mostrar `<strong>` literalmente.

## 4.0.0 — 15 de octubre de 2026

- Cron real o virtual configurable desde `.env`, disparador web/AJAX mediante Async, coordinación compartida con el CLI, recuperación tras caídas y diagnóstico `--status`. Incluye migraciones MySQL/SQLite y guía de actualización.
- CSRF para sesiones anónimas y formularios públicos de autenticación, con helper reutilizable, comparación constante y respuestas privadas sin caché compartida. Las vistas y rutas personalizadas requieren revisar el envío de tokens.

## 3.0.0 — 14 de octubre de 2026

- Retirada la tabla auxiliar `media_relations` y los métodos públicos `attach()`, `detach()` y `related()` de medios. Cada CRUD conserva el ID de imagen o el JSON de IDs de galería en su propia tabla. Migraciones MySQL y SQLite eliminan únicamente la tabla auxiliar. Consulte [la transición a 3.0.0](docs/release-3.0.0.md).
- Actualizadas la documentación, la skill de medios y las pruebas de instalación y ámbito.

## 2.0.3 — 12 de octubre de 2026

- El campo de medios coloca las miniaturas encima de Elegir imagen/archivo y Quitar, con controles Bootstrap. Las miniaturas muestran el puntero de mano y abren el selector también mediante Enter o espacio; las galerías conservan la retirada individual.

- Auth hereda el color y la tipografía comunes de los labels, elimina el borde de la tarjeta y utiliza “Ir al inicio” con los colores primary y primary-hover. Auth y errores adaptan su altura al viewport dinámico y mantienen el fondo de la página visible detrás del footer.

## 2.0.2 — 12 de octubre de 2026

- Rutas públicas GET con `noSession()`: el router puede omitir el arranque de sesión para recursos sin middleware, evitando que una respuesta pública concurrente reemplace la cookie del login. El punto de entrada estándar delega el arranque al router; los personalizados requieren adaptar esa llamada.

- Apache y Nginx permiten rutas virtuales bajo `/app`, conservando el bloqueo de scripts internos y sin publicar los archivos físicos de esa carpeta.

- El login de `auth-ui` admite Enter y el botón Acceder mediante el envío del formulario, valida antes de enviar y evita solicitudes simultáneas.

## 2.0.1 — 12 de octubre de 2026

- GFSelect reutiliza los estilos nativos `form-select` y `form-control`, incluidas las variantes de tamaño del select, para mantener la apariencia de los campos de GFrame.

- El ORM convierte los parámetros booleanos a `0` y `1` antes de enviarlos a PDO, evitando el rechazo de `force_password_change = false` por MySQL estricto.

- Skills GFrame por proyecto: activación automática desde Composer en `.agents/skills` y `.claude/skills`, usando la versión instalada o las fuentes del repositorio del framework. Comando PHP portable con vista previa, hashes y conservación de personalizaciones; se retira el modo global de PowerShell.

- Compatibilidad de herramientas: `bin/install-skills.ps1` requiere ahora `-ProjectPath`; las invocaciones globales anteriores se rechazan. Utiliza el modo por proyecto o los comandos Composer documentados.

## 2.0.0 — 6 de octubre de 2026

Actualización mayor por el cambio de comportamiento de listas vacías del ORM. Consulte [la transición a 2.0.0](docs/release-2.0.0.md) antes de actualizar aplicaciones.

- Aviso administrativo opcional al crear una cuenta: destinatarios con autorización global comprobada, campana o correo en segundo plano; desactivado por defecto y sin alterar el registro ante fallos del aviso.

- Comando `bin/gframe-context.php` para obtener metadata del paquete, rutas de skills, módulos registrados y PHP CLI en JSON, sin arrancar la aplicación ni leer `.env`; funciona también sin extensiones adicionales mediante `php -n`.

- Reservas de notificaciones con vencimiento configurable, recuperación por canal y confirmaciones protegidas por generación; se conservan repositorios personalizados mediante una extensión opcional. Finalice workers anteriores antes de actualizar; los transportes externos pueden duplicar envíos tras una caída.
- `whereIn([])` y `orWhereIn([])` agregan una condición falsa respetando AND/OR. Las aplicaciones que utilizaban listas vacías para omitir filtros deben omitir explícitamente esa llamada.
- Cron registra como fallidas las tareas cuyos handlers devuelven `error` o `failed`, sin reprogramarlas; resultados sin estado conservan la compatibilidad anterior.
- MySQL registra avances por sentencia y bloquea ejecución concurrente. Reanuda sentencias confirmadas y exige comprobar resultados inciertos antes de repetir; SQLite conserva transacciones por migración.
- Biblioteca multimedia y selector separan los IDs de sus filtros, archivos y paginación; los controles actúan sobre su propia instancia cuando ambos listados están presentes.

- Generador del complemento de catorce skills propias con versión explícita, manifiestos portable/Claude, copia intacta, procedencia, hashes y comprobación de referencias.
- Instalación de skills por proyecto con vista previa, fuente Composer instalada, registro de hashes y preservación de personalizaciones; se conserva el modo global anterior.
- Corregidos la discrepancia entre el tenant autorizado y el ámbito multimedia, la aceptación de etiquetas GCM truncadas y la falta de revocación de sesiones gestionadas tras recuperar la contraseña.
- Migraciones con transacciones únicamente en SQLite y reconocimiento de columnas compatibles en actualizaciones históricas, sin modificar los SQL publicados.
- WordPress normaliza los objetos JSON anidados del transporte y Router escapa los caracteres literales de las rutas.
- Webhook conserva el secreto predeterminado cuando el contexto no configura otra credencial verificable; SSE rechaza `require_token` sin secreto esperado ni verificador.

## 1.1.0 — 6 de octubre de 2026

- Unificado el identificador `gf-select` con el formato de `gf-table`; `gfselect` y sus rutas públicas se conservan como compatibilidad para proyectos existentes.
- El actualizador conserva todos los destinos cuando un mismo archivo de origen se publica en varias rutas; esto permite actualizar las rutas nuevas y de compatibilidad de GF Select.
- Documentación reorganizada para preparar Apache/Nginx antes de la instalación, reunir header/footer/menús y explicar la persistencia del panel y las variables CSS. Versiones distribuidas registradas para AOS, Coloris, Luxon y jQuery UI.

- Async actualiza Opis Closure a la rama 4, conserva la lectura de tareas de Opis 3 y elimina la sustitución local por Laravel Serializable Closure. PHP 8.1 requiere al menos 8.1.9 por el fallo corregido de referencias en WeakMap. Antes de actualizar código usado por tareas activas, deben finalizar esas tareas.
- Mail incorpora protección antispam opcional para formularios públicos mediante `rate_limit`, con límites configurables, ventana móvil y bloqueo para envíos concurrentes. El correo interno conserva su comportamiento al omitir esa opción.
- Router conserva el patrón declarado en `routeParams.uri`. HTML, Sitemap y LLMS comparten la exclusión de rutas privadas, protegidas y de error, manteniendo los controles individuales y los contratos anteriores de los índices.
- El preset FAQ complementa otros tipos de JSON-LD sin sustituirlos por WebPage.

## 1.0.2 — 2 de octubre de 2026

- El fragmento Nginx conserva el manejador PHP del panel: solo define enrutamiento, protección y destinos de errores. El manejador existente comunica el código mediante `GFRAME_SERVER_ERROR`.
- El fragmento Nginx se publica como `nginx.conf` en la raíz del proyecto, junto a `.htaccess`, y queda protegido contra acceso web. Las copias anteriores se conservan; cambia el `include` del servidor a la nueva ruta.

## 1.0.1 — 2 de octubre de 2026

- El paquete Composer pasa de `gframe/framework` a `gorvet/gframe`, porque el proveedor `gframe` de Packagist pertenece a un tercero. La marca, los namespaces PHP, los módulos y el instalador siguen llamándose GFrame.
- Los proyectos nuevos requieren `gorvet/gframe` y ejecutan el actualizador desde `packages/gorvet/gframe/bin/gframe-update`. Los proyectos existentes deben cambiar el requisito y el script antes de actualizar Composer.
- La documentación del panel administrativo describe su uso y personalización, sin informes internos de extracción.
- Los manifiestos de bibliotecas externas identifican sus fuentes oficiales. La ayuda pública separa integración, uso y personalización de los informes históricos de desarrollo.

## 1.0.0 — 2 de octubre de 2026

Primera versión del paquete completo, con PHP 8.1 como entorno probado. Incluye la migración descrita en las notas de preparación anteriores.

- Solo se crean carpetas de personalización para las capas presentes en cada módulo.
- El fragmento Nginx pasa de `config/server/nginx.conf` a `deployment/nginx.conf` y conserva la protección de archivos internos.
- `storage/gframe-installed.json` unifica la lista de módulos instalada: arranque, actualización y Campañas usan la misma fuente. No se genera ni se consulta `config/modules.php`.
- La estructura inicial de esta versión requería `gframe/framework:^1.0`; desde 1.0.1 usa `gorvet/gframe`. `ProjectConfigWriter::write()` deja de recibir el array de módulos y devuelve únicamente las rutas de configuración y entorno.

Consulte [alcance, comprobaciones y migración de 1.0.0](docs/release-1.0.0.md).
