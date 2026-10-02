# Registro de cambios

## 1.0.0 — 2 de octubre de 2026

Primera versión del paquete completo, con PHP 8.1 como entorno probado. Incluye la migración descrita en las notas de preparación anteriores.

- Solo se crean carpetas de personalización para las capas presentes en cada módulo.
- El fragmento Nginx pasa de `config/server/nginx.conf` a `deployment/nginx.conf` y conserva la protección de archivos internos.
- `storage/gframe-installed.json` unifica la lista de módulos instalada: arranque, actualización y Campañas usan la misma fuente. No se genera ni se consulta `config/modules.php`.
- La estructura inicial requiere `gframe/framework:^1.0`. `ProjectConfigWriter::write()` deja de recibir el array de módulos y devuelve únicamente las rutas de configuración y entorno.

Consulte [alcance, comprobaciones y migración de 1.0.0](docs/release-1.0.0.md).

## Preparación de 0.9.0 (sin publicar)

- Módulos MVC con originales en el paquete y personalizaciones por herencia PHP en `app`, sin copiar controladores ni vistas al instalar.
- Instalador por pasos, módulos obligatorios por perfil y opcionales agrupados; actualización antes y después de instalar, con bloqueo automático del instalador.
- Autenticación, administración de usuarios, multimedia, notificaciones y campañas integradas con contratos, colas, migraciones MySQL/SQLite y documentación propia.
- Recursos visuales conectados a las variables del framework, plantillas de correo homogéneas y configuraciones de Apache/Nginx separadas del servidor del proyecto.
- Cuenta y seguridad detecta Campañas mediante el runtime activo, sin depender de vistas publicadas antiguas.

La verificación y las limitaciones previas a publicación se registran en [preparación de la versión](docs/preparacion-0.9.0.md). Las notas siguientes conservan el historial de desarrollo; no representan versiones publicadas independientes.

- Los filtros de Notificaciones mantienen el mismo color de texto al pasar el puntero, enfocar o seleccionar; solo cambia el fondo del seleccionado. Los iconos de importancia quedan sin borde.

- Notificaciones: filtros con fondo suave y texto contrastado, iconos de importancia más visibles y retirada de «Marcar todas» de la campana y del listado.

- El contador de la campana aumenta ligeramente de tamaño y usa el rojo de `--bs-danger`, manteniendo su posición junto al icono.

- Notificaciones: filas clicables con resumen, icono de importancia y punto para no leídas. Vista completa protegida y marcado por POST con CSRF al abrir. Menú solo para cambiar lectura, sin borrar ni archivar; filtros en campana y listado. Se reutilizan título, espaciado y paginación del admin, únicamente cuando hay varias páginas. `NotificationRepository` añade consulta individual y marcado como no leída; los adaptadores deben implementar ambas operaciones.

- Las convenciones de vistas admin y sus comprobaciones se mantienen exclusivamente en la skill canónica, sin duplicarlas en `AGENTS.md`. La skill instalada se sincroniza con el repositorio. Canales pasa al final de los campos de entrega en crear y editar campañas.

- Campañas automáticas: «Enviar ahora» es directo, con audiencia determinada por la regla en el backend y protección contra duplicados. Se añade historial inmutable por ejecución y destinatario, enlazado a la cola para mostrar el estado real de entrega. Incluye migración MySQL/SQLite y rutas protegidas; se documenta la retirada de `user_ids[]` y `reason` del envío directo.

- Creación y edición de Campañas comparten formulario completo, con bloques de contenido y entrega, fecha junto a frecuencia y botones auxiliares diferenciados. Una campaña pendiente permite editar también audiencia, canales y programación de forma transaccional. Las recurrentes editan la próxima ejecución sin alterar trabajos anteriores. El botón indica «Enviar ahora» o «Programar campaña» según la fecha.

- Notificaciones: contador anclado a la campana, desplegable bajo la cabecera y desplazamiento interno con título y enlace final visibles. Se corrige en el módulo admin la interferencia de los estilos del menú móvil público, sin cambiar la navegación pública. Comprobados escritorio, móvil y temas.

- Completada la política de desactivación voluntaria: 60 días de retención y aviso previo de 72 horas configurables, correo inmediato y borrado condicionado a aviso enviado, estado, protección del superadministrador e integridad transaccional. Mi cuenta muestra la política. Incluye migraciones sin asignar fechas a cuentas antiguas.

- Campañas añade recurrencia diaria/semanal siguiendo la programación UTC de Base Confías, audiencia dinámica y ocurrencias sin duplicados, vista previa, prueba exclusiva para la cuenta conectada, enlace de acción, importancia y caducidad. Se documentan campos y contratos nuevos. Pausa y cancelación respetan el ámbito y las ocurrencias.

- Corregida la entrega inbox: consumidor transaccional, registro cron y actualización de la campana al abrirla. El calendario adopta variables de tipografía y tema; destinatarios manuales preceden al mensaje y las acciones de formularios y modales se alinean a la derecha con Cancelar antes del principal.

- Campañas automáticas pasa a listado con edición y envío manual en modales. Añade revisión horaria de suspendidos, bloqueados y cuentas sin verificar. Automático, evento inmediato y manual comparten un registro transaccional por ámbito/regla/usuario y un plazo configurable sin repetir (7 días iniciales). La verificación reutiliza AuthModel y revierte la renovación del token si falla la cola; un aviso omitido no cambia el token. Desactivación sigue pendiente de una política real de eliminación. Se añaden migraciones para el plazo y el registro.

- Campañas automáticas añade vista, enlace protegido, reglas editables por ámbito y migraciones MySQL/SQLite. Recupera el patrón de reglas de Base Confías. Gestión de usuarios conecta el aviso de suspensión a la cola de correo, con comprobación de estado y deduplicación. Se documentan las integraciones aún necesarias para bloqueo, enlaces de verificación y recordatorios sujetos a una política real de eliminación. La cola de correcciones acordada queda en `docs/cola-correcciones-campanas.md`.

- Barra lateral ordenada por áreas: Escritorio, módulos del usuario (Multimedia y Notificaciones), Administración (Campañas y Usuarios) y Mi cuenta al final. Los fragmentos personales declaran `$menuSection = 'user'`; los fragmentos existentes sin declaración mantienen su ubicación administrativa. El encabezado Administración solo aparece si hay enlaces autorizados en esa área.

- Destinatarios queda junto al título de la campaña, sin la nota de exclusiones. Flatpickr recupera los estilos de `bebots/public/css/app/admin/b/business.css` en `gframe-flatpickr.css`; el selector de tema cambia de `data-gf-theme` a `data-bs-theme`, utilizado en GFrame. El CSS se carga después del proveedor y no modifica sus archivos originales.

- El desplegable Administrar de Campañas utiliza posicionamiento fijo de Popper para evitar el recorte dentro de la tabla responsive, conservando el desplazamiento horizontal de la tabla.

- Campañas recupera las ocho variables comunes de Base Confías y Dane, con inserción en título o mensaje y sustitución por destinatario antes de encolar. Añade audiencias de activos, administradores activos y selección manual con GFSelect; excluye cuentas sin verificar, desactivadas y suspendidas, y vuelve a comprobar su elegibilidad al procesar. El cambio de campos del formulario y los adaptadores personalizados se documentan en `docs/notification-campaigns.md`.

- Cada módulo administrativo aporta su encabezado de sección. Campañas añade el enlace Nueva campaña, unifica el nombre interno con el título, acorta el subtítulo y utiliza Flatpickr para fecha y hora. Permite editar contenido antes de procesar destinatarios y reciclar mediante una nueva campaña sin modificar el envío original.

- Campañas copia el patrón de título, subtítulo y botón contiguo de Bots en Bebots; se añade separación entre la tarjeta de filtros y la de resultados mediante `mb-4`, sin modificar el CSS común.

- Campañas usa el título y las tarjetas comunes de administración. Se separa el listado de la vista de creación/edición; «Nueva campaña» deja de desplegar un formulario incrustado. La paginación solo se muestra con varias páginas; el filtro de estado es automático. Corregida la carga de los metadatos de las vistas anidadas y de los recursos del módulo. La edición de contenido comprueba permiso, tenant, estado pausado y destinatarios pendientes; conserva audiencia y programación. Sin cambios en el template ni en el CSS común del admin.

- Creada la lista de mejoras pendientes no urgentes en `docs/mejoras-pendientes.md`. Añadir usuarios desde el admin queda registrado como mejora opcional de baja prioridad, sin implementar y sin desplazar el trabajo actual de Campañas.

- Corregido el error 500 al cargar Campañas: `CampaignModel` y `CampaignRepository` usan `findCampaign` y `paginateCampaigns` para evitar colisiones con los métodos heredados del ORM. Controlador, servicio y pruebas actualizados; los adaptadores personalizados deben renombrar esos dos métodos, según docs/notification-campaigns.md. No se modifica el ORM.

- Biblioteca multimedia, Notificaciones y Campañas publican accesos en el menú administrativo. Notificaciones conserva también la campana de la barra superior. Los enlaces respetan la autenticación y los permisos de sus rutas; las tres pantallas usan la plantilla `admin`, sin cambiar sus URLs.

- Modal de usuarios ordenado: selector de rol a ancho completo, estado y acción de acceso separados por un borde, y pie con eliminación secundaria a la izquierda y Cerrar/Guardar rol a la derecha. Conserva los contratos y operaciones existentes, sin cambios de backend.

- Administración de usuarios agrupa las acciones en el modal Administrar: cambiar rol, verificar, suspender/restablecer y eliminar con confirmación. Desactivar queda reservado a Mi cuenta. Se añade UserModerationRepository sin modificar el contrato previo de repositorios. Las nuevas operaciones protegen la cuenta propia, al superadministrador y la jerarquía administrativa; la eliminación de la cuenta, membresías y sesiones es transaccional y revierte ante relaciones que impidan el borrado. Transiciones, parámetros y compatibilidad documentados en docs/user-admin.md.

- Listado de usuarios conserva el padding normal de card-body. Los filtros buscan automáticamente mediante AJAX (300 ms al escribir y cambios inmediatos en selectores), sin botón Filtrar; Limpiar usa gicon-close y solo aparece con filtros activos. El controlador devuelve el parcial HTML con el contrato de lista existente, conserva los filtros en la URL y cancela solicitudes anteriores.

- ORM delega la expresión `LIKE ... ESCAPE` en MySqlDialect y SqliteDialect mediante `likeExpression()`. Se conserva el escape original de MySQL y SQLite recibe una sola barra, evitando el error 500 de los filtros. La API de búsqueda y los parámetros enlazados no cambian. Los dialectos personalizados deben implementar el nuevo método de DatabaseDialectInterface.

- Gestión de usuarios recupera la estructura de vistas de Bebots: título, tarjeta de filtros con etiquetas y botón Limpiar, y parcial `_userList.php` con tabla y estados legibles. Se mantienen las rutas y operaciones actuales, sin añadir campos exclusivos de Bebots o Dane. Se bloquean en la vista los controles sobre la cuenta propia y el superadministrador y se inicializa el valor anterior del selector de rol para restaurarlo ante errores.

- Orden del menú administrativo corregido: Escritorio fuera de Administración, Gestión de usuarios dentro de Administración y Mi cuenta al final, después de los módulos, como en Bebots y Dane.

- El footer administrativo recupera la alineación izquierda y el desplazamiento de la barra lateral de Bebots, sin heredar el centrado público. Mi cuenta utiliza la plantilla administrativa y la estructura de tarjetas de Base Confías, conservando los campos, identificadores y operaciones compatibles con el contrato actual. Gestión de usuarios tiene un apartado propio y deja de depender de Mi cuenta. `common.css` utiliza `data-bs-theme`, como Bootstrap y el selector de tema, y colores semánticos para navegación, iconos y superficies oscuras. La barra saluda con «Hola, nombre de usuario».

- Home conserva su portada y mensaje, con tipografía y navegación refinadas y animación opcional de entrada. Header, contenido y footer comparten el alto de la ventana mediante flexbox; el footer usa el espaciado común. El actualizador publica también los archivos del home.

- Espaciado del footer centralizado en `common.css` para home, Auth y errores, sin duplicarlo por vista. Se restaura la explicación breve de la 404 y se usa el mismo logo y tamaño de Auth. El logout voluntario redirige a `login` sin `rd`; la expiración conserva el destino de retorno.

- La 404 predeterminada queda en código, título y botón de retorno, sin explicación ni contacto añadidos. Los errores 404/500 solo muestran ayuda si se proporciona expresamente. El footer de errores elimina el padding vertical duplicado de los fragmentos de copyright y créditos, igual que Auth.

- Las páginas de error usan el fondo del login (`var(--bs-gray-100)`), sin imagen de fondo; footer y marca conservan contraste sobre ese fondo claro.

- El actualizador publica el metadato global, header/footer y CSS compartidos del esqueleto, antes omitidos en proyectos existentes. Respeta la vista previa y `--preserve-custom`; evita páginas de error sin Bootstrap, variables ni cargas comunes de JavaScript.

- Vistas, plantilla, fragmento, metadato y CSS de errores copiados de Base Confías sin rediseño. El CSS se publica en su ubicación original `public/css/404/404.css`. El router conserva el enlace de retorno, la ayuda y el contexto al convertir respuestas de error web.

- El logout voluntario muestra solo la confirmación previa y redirige al acceso sin un segundo aviso. Las otras pestañas conservan el aviso de sesión cerrada.

- Auth vuelve al flujo MVC de Base Confías y Bebots: `AuthController → AuthModel → ORM`. Se elimina `AuthService`, se restauran los nombres originales de las operaciones y se retira de `UserModel` la persistencia exclusiva del registro y los tokens de Auth. Se conservan los contratos y las protecciones actuales; la creación de tenants y membresías permanece en cada aplicación. Migración documentada en `docs/autenticacion.md`.

## [0.9.0] - Sin publicar

### Núcleo

- Auth unifica los datos de sus operaciones bajo `data` y actualiza controladores y consumidores; los tokens internos se eliminan antes de responder al navegador. Heartbeat incorpora `heartbeat_dispatched` en la respuesta general y un `code` por canal, conservando los códigos específicos declarados por cada handler.

- El canal AJAX conserva JSON ante errores de controlador o middleware, incluidos `invalid_token` y `expired`; el frontend decide cómo mostrar la respuesta. El footer declara `site_url` e `is_protected` antes de cargar los JS y conserva `#toastBox` y el aviso de sesión expirada entre pestañas.

- Se unifica `redirect` en las respuestas como ruta relativa a la aplicación, sin barra inicial ni protocolo. Auth devuelve `admin`, `account` o `login`; los JS de Auth y Mi cuenta añaden `site_url` una sola vez. Las aplicaciones existentes deben actualizar conjuntamente productores y consumidores del campo.

- Auth recupera las rutas AJAX originales de Base Confías (`verifyacount`, `validateacount`, `lostpassword`, `resetpassword`) y sus campos de formulario; las rutas renombradas permanecen como alias. Regla de migración: cualquier cambio de nombre de una ruta o parámetro debe documentarse, actualizar todas las llamadas JS y vistas, y probar el flujo completo antes de publicarse.

- La portada inicial recupera el mensaje, la estructura y los logotipos del proyecto GFrame original; el instalador publica también favicon e icono táctil.
- `auth-ui` recupera las rutas `/login/lostpassword` y `/login/resetpassword?rp=...`, la composición visual de las vistas originales y la redirección al panel administrativo.
- `admin-panel` publica una ruta `/admin` y un escritorio inicial para que el acceso tenga destino funcional.

- El generador de proyectos reconoce `-h` y `--help` sin crear un directorio accidental.
- El esqueleto incluye reglas de Apache para las rutas de la aplicación y la protección de archivos internos.

- Multimedia recupera campo y selector separados, selección múltiple JSON, vista previa segura renderizada por PHP, actualización del listado por fragmentos, carga desde el selector, registro seguro de enlaces HTTPS y navegación, vista previa y copia de URL en el modal de detalles. La URL externa se guarda separada de la ruta local.

- El instalador valida el primer superadministrador y los conflictos de configuración antes de publicar archivos; tras fallos de publicación limpia su configuración y admite reintentos. El primer usuario se crea al final. Se añadieron pruebas HTTP para los cuatro perfiles y una prueba MySQL aislada. `robots.txt` permanece disponible con `Disallow: /` cuando se desactiva la indexación.

- Auth recupera `already_logged`, calcula el respaldo del saludo con la parte local del correo sin persistir nombre, comprueba escrituras y rota tokens al suspender o desactivar. Recuperación, verificación y restablecimiento bloquean cuentas suspendidas o desactivadas.
- Sesiones PHP administradas mediante drivers de base de datos y Redis: suspensión y desactivación revocan todos los dispositivos, bloquean nuevos inicios hasta la reactivación y evitan consultas al estado del usuario desde heartbeat.
- El driver de base de datos crea la sesión autenticada solo durante el login y luego actualiza exclusivamente filas existentes; la revocación no puede reinsertarse al terminar una petición concurrente. Se elimina `gframe_session_users` del esquema nuevo y el estado de cuenta se comprueba desde `users`. Redis aplica la misma regla de existencia y deja de usar generaciones.
- Los permisos efectivos se conservan en sesión con versión de seguridad por rol; los cambios refrescan cada dispositivo en su siguiente operación sin consultar permisos en cada middleware ni cerrar la sesión.
- Los roles son plantillas y admiten excepciones individuales `allow`/`deny`, globales o por tenant, controladas mediante una versión de autorización por usuario.
- Se elimina `MiddlewareDataProvider`: `can:*` usa la autorización normalizada de sesión y las credenciales y orígenes de API permanecen aislados en `ApiCredentialProvider`.
- `config/Permissions.php` se sincroniza con el JSON de cada rol durante instalación y `gframe:update`; una fila por membresía conserva el rol y las excepciones locales.
- La gestión de membresías tenant permite al dueño asignar y retirar gestores y al gestor abandonar el tenant; protege la membresía del dueño. Auth sigue creando usuarios sin membresías, que corresponden al módulo creador del tenant.

- Navegación pública extraída de Bebots al esqueleto, con menú móvil, anclas, enlace activo y retorno arriba. Incluye documentación, cierre con Escape, estado accesible, movimiento reducido y comprobación de consulta para anclas locales.

- Auth restaura conexiones de password-utils, validación jQuery, reenvío de verificación con Mail, retorno `rd` validado y cierre único entre pestañas. CSS basado en Bebots con variables compartidas, sin paleta paralela.

- Plantillas comunes `mailTemplate` y `contactTemplate` extraídas al esqueleto y conectadas con Mail; el tema lee valores globales y claros sin mezclar reglas oscuras y actualiza la lectura por correo. Los marcadores se sustituyen en una sola pasada y admiten espacios.

- El panel usa `data-bs-theme` como selector único, conserva `GFTheme` y su persistencia, y personaliza las superficies oscuras sobre Bootstrap sin modificar la librería.
- La personalización del tema se concentra en `public/css/variables.css`, sin un archivo `theme.css` adicional.
- Visor `colores.html` junto a `variables.css`, con catálogo dinámico, filtros, modos claro/oscuro y muestras de componentes Bootstrap.

- GFTable se separa de `frontend-core` como componente opcional `gf-table`, sin cambios de comportamiento en esta fase.
- GFTable corrige ordenación de fechas, dirección tras filtrado, reinicio, detección de tablas dinámicas y lectura de números locales.

- Markdown se agrupa en un componente PHP/JS independiente de `frontend-core`; su conversión JavaScript escapa HTML de entrada.

- Creación del paquete independiente de GFrame.
- Extracción inicial del núcleo compartido.
- Carga de aplicaciones mediante `GFrame\Foundation\Bootstrap`.
- Módulo reutilizable para procesar y lanzar en segundo plano colas de notificaciones.
- Planificador cron con API de tareas, recurrencia, reserva concurrente, recuperación de bloqueos y control de estado.
- Soporte Mail único con SMTP exclusivo desde `.env`, plantillas temáticas y envío asíncrono; `notifications-email` queda como adaptador para colas y campañas, sin fachada heredada.
- Módulo `notification-campaigns` para envíos masivos inmediatos o programados mediante cron, cola común y transportes desacoplados.
- Servicio opcional de cifrado autenticado.
- Autenticación reutilizable mediante servicios, modelos ORM estándar, política de contraseñas, tokens y sesiones seguras.
- Validación estricta del estado activo antes de autenticar y contrato normalizado en `$_SESSION['auth']`.
- Apartado «Mi cuenta» con consulta de datos base, cambio de contraseña y desactivación protegida del superadministrador.
- Roles y permisos normalizados mediante `users.role_id`, `roles`, `permissions` y `role_permissions`.
- Rol de sistema único `superadministrator`, sin indicador booleano duplicado en el esquema normalizado.
- Middleware `role:*`; `admin` basado en `admin.access` y `can:*` basado en permisos del rol.
- Esquemas de autenticación y acceso para MySQL y SQLite.
- Modelo de usuarios estándar y servicio de instalación que asigna el rol protegido a la primera cuenta.
- Cierre explícito de conexiones mediante `ORM::disconnect()` para pruebas, procesos largos y bases SQLite.
- Catálogo de módulos opcionales con resolución automática de dependencias.
- Publicador seguro de recursos que conserva los archivos existentes por defecto.
- Incorporación de SweetAlert2 y del módulo propio `alerts` con `alertToast` y `swalAlert`.
- Incorporación de `gfselect`, `password-utils`, `gframe-icons`, `frontend-core`, `heartbeat-client` y `rich-text-editor`.
- GFSelect actualizado con selección múltiple, opciones dinámicas, validación nativa y publicación exclusiva de JS/CSS.
- Password Utils alinea la aceptación en navegador con `PasswordPolicy` (8–72 bytes UTF-8) y desactiva la generación cuando falta la API criptográfica.
- `PasswordPolicy` se agrupa con el módulo `password-utils` sin cambiar su nombre de clase; `auth-ui` declara la dependencia.
- Los estilos de WordPress pasan a `wordpress-headless`; se conserva la ruta pública de la hoja base y se añade una capa temática mediante variables CSS.
- La fuente `gframe-icons` deja de aplicar sus reglas a todos los elementos `<i>` y se limita a los que usan clases `gicon-*`.
- Registro separado de Owl Carousel, Swiper y las demás dependencias frontend reutilizables.
- Perfiles de instalación `static`, `managed`, `intranet` y `saas`, con esquemas MySQL y SQLite.
- Esqueleto de aplicación con portada inicial propia de GFrame e instalador visual.
- Generador integrado de proyectos mediante `composer new`, con una sola fuente dentro de GFrame.
- Módulos instalables completos para autenticación, Mi cuenta, páginas de error, administración de usuarios y heartbeat.
- Las páginas de error pasan a formar parte obligatoria de toda instalación.
- Heartbeat con cierre por inactividad configurable y sincronización de sesión entre pestañas, sin renovar actividad desde su propia petición.
- Biblioteca multimedia con ámbitos global, tenant y usuario, relaciones reutilizables, administración y selector de campos.
- Cola de notificaciones persistente con transporte de correo e interfaz de administración.
- Módulo de tareas programadas con esquemas MySQL/SQLite y punto de entrada CLI instalable.
- Actualizador de proyectos con migraciones incrementales, registro de versiones y protección de archivos personalizados.
- Acceso Bearer para API entrante con CORS, preflight, consumidores por ruta y proveedor reemplazable para credenciales por cliente o tenant.
- Rutas SEO iniciales instalables para sitemap, robots y llms, con documentación de Meta y generación automática desde rutas públicas.
- Cliente WordPress headless instalable desde `.env`, con TLS obligatorio, endpoints BridgeFrame normalizados y contrato de actualización documentado.
- Footer compuesto por plantillas PHP opcionales para contenido, copyright y créditos, con sustitución por grupo o vista; el HTML deja de almacenarse en Meta.
- Panel administrativo instalable con plantilla compartida, metadatos por plantilla, extensiones de módulos, navegación personalizable, tema y sidebar persistentes.

### Configuración

- Incorporación de configuración por entorno mediante `.env` y `config/app.php`.
- Valores internos protegidos en `config/defaults.php`.
- Acceso mediante `config()`, `env()`, `env_bool()` y `env_int()`.
- Compatibilidad temporal con las constantes históricas.
- Soporte opcional para permisos globales o por tenant.

### Dependencias

- Gestión mediante Composer de PHPMailer, Opis Closure, PHP-SSE y PHP dotenv.
- Identidad Composer establecida como `gframe/framework`.
- Dependencias PHP organizadas bajo `packages/`, con autoload en `packages/autoload.php`.
- Exclusión de dependencias, pruebas y archivos de desarrollo de los paquetes distribuibles.

### Calidad

- Pruebas de carga, dependencias, configuración, identidad normalizada y tareas asíncronas.
- Documentación de arquitectura, configuración, versionado y migración.
- Skills oficiales y versionados para Codex y Claude Code, con instalación y validación automatizadas.
- Matriz de integración continua para PHP 8.1, 8.2, 8.3 y 8.4.
