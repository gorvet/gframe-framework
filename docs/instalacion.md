# Instalación

GFrame ofrece cuatro perfiles iniciales:

- `static`: sitio sin base de datos ni autenticación;
- `managed`: aplicación administrada global;
- `intranet`: aplicación administrada sin vista pública;
- `saas`: aplicación multitenant con base de datos compartida.

`intranet` usa la misma autorización global que `managed`, pero desactiva la vista pública y SEO.

## Instalador visual

El esqueleto del proyecto incluye `install.php` en la raíz. La pantalla permite elegir el perfil, MySQL o SQLite, módulos opcionales, configuración SEO, Metricool, expiración de contraseñas y la primera cuenta superadministradora.

Antes de configurar el negocio, la aplicación muestra la portada original de GFrame con el mensaje «Algo maravilloso se construye aquí.», sus logotipos y sus iconos. El proyecto puede reemplazarla sin modificar el framework.

El instalador:

1. comprueba la raíz del proyecto, el bloqueo de instalación, el perfil, las dependencias de los módulos y, cuando hay autenticación, los datos del primer superadministrador;
2. copia el esqueleto de aplicación;
3. conecta la base de datos, si el perfil la requiere, y crea las tablas de autenticación y de los módulos; en SaaS también instala `tenants` con la clave `tenant_id`;
4. sincroniza los roles definidos en `config/Permissions.php`;
5. escribe `config/app.php`, `.env` y `config/modules.php`, publica los recursos y registra las migraciones iniciales;
6. crea el primer superadministrador y registra `storage/gframe-installed.json` para impedir una segunda ejecución.

Al terminar debe bloquearse `install.php` en producción.

El instalador no crea un tenant ni asigna membresías a usuarios. En `managed` e `intranet` los permisos se resuelven por rol global; en `saas`, cada módulo que crea un tenant debe crear también la membresía inicial de su dueño. El procedimiento completo, incluida la comprobación de propiedad, está en [roles, permisos y membresías](permisos.md).

El esqueleto versionado en `resources/skeleton` es la fuente única para crear proyectos. Conserva `install.php` en la raíz y `public/css/home/home.css`, como en los proyectos de referencia.
Incluye `.htaccess` para enrutar las URL de Apache hacia `index.php` y bloquear el acceso directo a los archivos internos.

## Uso y extensión de los perfiles

Los perfiles se definen en `resources/install/profiles.php`. Cada uno fija si hay base de datos, autenticación, tenancy y sitio público, además de los módulos iniciales. El instalador añade los módulos predeterminados y resuelve las dependencias de los módulos elegidos. Un módulo que exige tablas no se puede instalar en `static`. Para ampliar una aplicación ya instalada, se usa el mecanismo de actualización y migraciones; volver a ejecutar el instalador no es el procedimiento de ampliación.

`managed` e `intranet` instalan autenticación y administración globales; `saas` agrega `tenants`, notificaciones y cron. La tabla `tenant_memberships` pertenece al esquema de autenticación y registra la relación usuario-tenant-rol; no representa el plan o la suscripción de un tenant. Los datos propios de cada tenant, por ejemplo un bot o una tienda, los define la aplicación. En `static` no se crea usuario ni base de datos.

`config/Permissions.php` sirve como plantilla inicial de roles y capacidades. El instalador la sincroniza tras crear el esquema de autenticación. Los valores de entorno y secretos se escriben en `.env`; `config/app.php` conserva la estructura estable y `config/modules.php` registra los módulos instalados. El bloqueo de instalación se guarda en `storage/gframe-installed.json`.

## Estado de verificación

Al 29 de septiembre de 2026, las pruebas automatizadas cubren la instalación de los cuatro perfiles, tablas SQLite y MySQL, primer superadministrador, recursos principales y rechazo de un módulo con base de datos en `static`. La prueba MySQL crea y elimina exclusivamente una base temporal con el prefijo `gframe_install_test_` y se activa con `GFRAME_TEST_MYSQL=1`. Una prueba HTTP levanta cada perfil temporalmente y comprueba portada o redirección privada, login donde corresponde, `robots.txt`, Bootstrap y bloqueo del instalador tras completar la instalación. En `static` también comprueba `Disallow: /` con la indexación desactivada. Las operaciones propias de Auth, Multimedia, Notificaciones y otros módulos se prueban en sus suites; no forman parte del contrato del instalador. PHP 8.4 no está instalado en este entorno y sigue sin prueba de ejecución.

La validación inicial de la cuenta administrativa y la comprobación de conflictos de configuración ocurren antes de publicar archivos. Si falla la publicación o un paso posterior, se retiran los tres archivos de configuración recién creados; el proyecto puede reintentar la instalación sin sobrescribir archivos anteriores. La cuenta administrativa se crea al final y se retira si falla la escritura del bloqueo. Los esquemas MySQL no admiten una reversión global: las tablas creadas pueden permanecer tras un fallo, pero sus sentencias de creación son idempotentes y el reintento las reutiliza. No se deben borrar manualmente bases de datos existentes para reintentar.

Para crear un proyecto:

```bash
composer new -- ../mi-proyecto
```

Use `composer new -- --help` para consultar la ayuda sin crear archivos.

El directorio debe estar vacío. Después se instalan las dependencias dentro del proyecto y se abre `install.php`. El asistente ofrece los perfiles `static`, `managed`, `intranet` y `saas`; no se mantienen plantillas independientes para cada uno.

SQLite es una alternativa completa para proyectos que no necesitan MySQL. Autenticación, roles, multimedia y notificaciones incluyen esquemas para ambos motores.

La configuración generada incluye `session.idle_timeout`, con 1800 segundos de forma predeterminada, y `media.scope`. El ámbito multimedia inicial es `global` en aplicaciones administradas y `tenant` en SaaS; también puede cambiarse a `user`.
