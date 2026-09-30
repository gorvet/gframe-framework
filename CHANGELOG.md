# Registro de cambios

## [0.9.0] - Sin publicar

### Núcleo

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
