# Registro de cambios

## [0.9.0] - Sin publicar

### Núcleo

- Creación del paquete independiente de GFrame.
- Extracción inicial del núcleo compartido.
- Carga de aplicaciones mediante `GFrame\Foundation\Bootstrap`.
- Módulo reutilizable para procesar y lanzar en segundo plano colas de notificaciones.
- Servicios propios de cifrado autenticado y clasificación tolerante de texto.
- Autenticación reutilizable mediante contratos de usuario, política de contraseñas, tokens y sesiones seguras.
- Gestión de la cuenta propia con perfil, cambio de contraseña, desactivación y políticas de protección configurables.

### Configuración

- Incorporación de configuración por entorno mediante `.env` y `config/app.php`.
- Valores internos protegidos en `config/defaults.php`.
- Acceso mediante `config()`, `env()`, `env_bool()` y `env_int()`.
- Compatibilidad temporal con las constantes históricas.
- Soporte opcional para permisos globales o por tenant.

### Dependencias

- Gestión mediante Composer de PHPMailer, Opis Closure, PHP-SSE, PHP Stemmer y PHP dotenv.

### Calidad

- Pruebas de carga, dependencias, configuración y tareas asíncronas.
- Documentación de arquitectura, configuración, versionado y migración.
- Skills oficiales y versionados para Codex y Claude Code, con instalación y validación automatizadas.
