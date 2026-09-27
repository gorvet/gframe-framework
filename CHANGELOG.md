# Registro de cambios

## [0.9.0] - Sin publicar

### Núcleo

- Creación del paquete independiente de GFrame.
- Extracción inicial del núcleo compartido.
- Carga de aplicaciones mediante `GFrame\Foundation\Bootstrap`.
- Módulo reutilizable para procesar y lanzar en segundo plano colas de notificaciones.
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
- Registro separado de Owl Carousel, Swiper y las demás dependencias frontend reutilizables.
- Perfiles de instalación `static`, `managed` y `saas`, con esquemas MySQL y SQLite.
- Esqueleto de aplicación con portada inicial propia de GFrame e instalador visual.
- Generador integrado de proyectos mediante `composer new`, con una sola fuente dentro de GFrame.
- Biblioteca multimedia con ámbitos global, tenant y usuario, más relaciones reutilizables con contenidos.
- Cola de notificaciones persistente con transporte de correo.

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
