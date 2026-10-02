# Instalación

GFrame ofrece cuatro perfiles iniciales:

- `static`: sitio sin base de datos ni autenticación;
- `managed`: aplicación administrada global;
- `intranet`: aplicación administrada sin vista pública;
- `saas`: aplicación multitenant con base de datos compartida.

`intranet` usa la misma autorización global que `managed`, pero desactiva la vista pública y SEO.

## Instalador visual

La instalación completada escribe `storage/gframe-installed.json` y bloquea automáticamente el asistente. La respuesta de éxito permite abrir la aplicación; las peticiones posteriores a `install.php`, tanto GET como POST, redirigen a la raíz del proyecto con HTTP 303 sin procesar datos de instalación. No es necesario borrar el archivo ni añadir un bloqueo manual para impedir una reinstalación.

Las bibliotecas opcionales muestran su nombre real (AOS, Flatpickr, Swiper, Chart.js, etc.) y explican su función en la descripción. No se renombran sus identificadores internos. Cuando se selecciona un módulo funcional, sus dependencias dejan de ofrecerse como opciones separadas: Campañas incluye Notificaciones, cron-runner y Flatpickr; Notificaciones incluye cron-runner; el editor incluye TinyMCE. Al deseleccionar el módulo principal reaparecen los opcionales que vuelven a ser independientes. Los requisitos del perfil y de la base común nunca aparecen.

El perfil `static` significa sin base de datos ni autenticación, no exclusivamente archivos HTML. Markdown puede convertir contenido almacenado en archivos y el buscador léxico puede filtrar contenido ya cargado mediante JavaScript. Ambos siguen siendo opcionales: no crean un editor, un índice ni un buscador completo por sí solos. WordPress headless puede obtener contenido de otro servidor sin base de datos local. Ninguna de estas herramientas es obligatoria para una portada fija. El editor enriquecido, las notificaciones, las campañas y el planificador con tablas no se ofrecen en este perfil. Las restricciones de perfil se comprueban también en las dependencias transitivas.

Antes de instalar, `composer gframe:update` actualiza únicamente el asistente y sus archivos de arranque; no necesita `config/app.php`, no conecta a la base de datos ni escribe el bloqueo de instalación. `--dry-run` permite revisar esos cambios. Las peticiones de aplicación, incluida una URL inexistente, redirigen a `install.php` antes de arrancar el router. Se conserva la ruta base si el proyecto está en una subcarpeta. Los recursos públicos del asistente siguen siendo accesibles. Un proyecto con configuración heredada se considera configurado; si existe el registro de instalación pero falta la configuración, se mantiene el error para no abrir una reinstalación accidental.

El esqueleto del proyecto incluye `install.php` en la raíz. El primer paso reúne nombre y tipo de proyecto, correo y una sola contraseña cuando hay autenticación. Después se comprueba la base de datos, únicamente si el perfil la necesita. Los módulos opcionales ocupan otra pantalla y el resumen permite confirmar la instalación. `static` omite cuenta y base de datos. Permite volver y valida antes de continuar. No escribe configuraciones ni instala módulos de aplicación hasta confirmar; solo publica los activos públicos de SweetAlert, jQuery y password-utils para el asistente. Zona horaria, caducidad de contraseñas y medición se configuran después.

La presentación conserva el logo exterior, las proporciones del contenedor original y los campos compactos con ayuda lateral en escritorio, apilados en móvil. No hay bienvenida, información anticipada de otros pasos ni barra decorativa. La contraseña permite mostrar u ocultar su valor y usa el medidor original. Los fallos de conexión se presentan mediante SweetAlert, sin abandonar el formulario ni perder los datos introducidos. El instalador envía `Cache-Control: no-store` y sus recursos CSS/JS llevan una versión derivada del contenido.

Hay tres niveles: base común obligatoria en todos los perfiles, módulos obligatorios del perfil y módulos opcionales. GFSelect y GFTable forman parte de la base común; no aparecen como opciones desconectables. Las dependencias se resuelven automáticamente. La pantalla de opcionales permite continuar sin seleccionar ninguno. Se ocultan los incompatibles con el perfil; el editor enriquecido y TinyMCE no se ofrecen en `static`. SEO, sitemap, robots y llms funcionan automáticamente mediante sus rutas y configuración; el instalador no pide activarlos ni genera archivos. `intranet` conserva la política privada.

Los opcionales se agrupan por Comunicación, Contenido y búsqueda, Formularios, Presentación y multimedia, Herramientas e Integraciones. `resources/install/optional-modules.php` define las opciones del asistente, no todas las dependencias del catálogo. `InstallationProfileCatalog::optionalModules()` excluye la base común y los requisitos transitivos de cada perfil, comprueba las necesidades de base de datos y respeta `install_profiles` si un manifiesto restringe sus perfiles. Se ocultan los grupos vacíos. Por ejemplo, notificaciones no se ofrece en `static`, es opcional en `managed` e `intranet` y ya está incluida en `saas`. Las dependencias técnicas, como TinyMCE para el editor, no exigen otra decisión. El servidor valida la selección; modificar el HTML no permite añadir un opcional incompatible.

Los errores de comprobación conservan los campos, incluidas las contraseñas en memoria del formulario. Tras un envío final fallido se restauran solo los datos no sensibles y las contraseñas deben introducirse de nuevo. No se guardan contraseñas en almacenamiento del navegador ni en el resumen. El asistente requiere JavaScript y el servidor valida nuevamente la instalación.

El JS anterior del instalador original llamaba a controladores y archivos de configuración retirados. Se conserva su flujo por pasos, no esas llamadas. El asistente comprueba la base mediante una petición protegida por CSRF a `install.php` antes de continuar: distingue conexión fallida, acceso denegado, base inexistente, vacía y ocupada. La comprobación no crea bases ni tablas. Si no existe, la instalación final intenta crearla automáticamente; necesita permisos `CREATE DATABASE`. Si existe, el asistente exige que esté vacía y repite la comprobación antes de instalar. No borra tablas ni datos. Un reintento tras un fallo que haya dejado tablas necesita revisar ese destino o usar una base vacía; la API de instalación conserva su comportamiento de reintento. El CSS parte del archivo original de GFrame, con los ajustes necesarios para las variables actuales y el arranque previo a la publicación de Bootstrap.

`tests/js/installer.test.cjs` prueba el asistente en un navegador real e instala los cuatro perfiles en directorios temporales. Requiere Playwright disponible y `GFRAME_TEST_BROWSER` con la ruta del navegador; `GFRAME_TEST_PHP` permite indicar el ejecutable PHP. Si esas dependencias no están disponibles, se omite esta prueba opcional, sin omitir las suites PHP.

Antes de configurar el negocio, la aplicación muestra la portada original de GFrame con el mensaje «Algo maravilloso se construye aquí.», sus logotipos y sus iconos. El proyecto puede reemplazarla sin modificar el framework.

El instalador:

1. comprueba la raíz del proyecto, el bloqueo de instalación, el perfil, las dependencias de los módulos y, cuando hay autenticación, los datos del primer superadministrador;
2. copia el esqueleto de aplicación;
3. conecta la base de datos, si el perfil la requiere, y crea las tablas de autenticación y de los módulos; en SaaS también instala `tenants` con la clave `tenant_id`;
4. sincroniza los roles definidos en `config/Permissions.php`;
5. escribe `config/app.php` y `.env`, publica los recursos y registra las migraciones iniciales;
6. crea el primer superadministrador y registra `storage/gframe-installed.json` para impedir una segunda ejecución.

Al terminar debe bloquearse `install.php` en producción.

El instalador no crea un tenant ni asigna membresías a usuarios. En `managed` e `intranet` los permisos se resuelven por rol global; en `saas`, cada módulo que crea un tenant debe crear también la membresía inicial de su dueño. El procedimiento completo, incluida la comprobación de propiedad, está en [roles, permisos y membresías](permisos.md).

El esqueleto versionado en `resources/skeleton` es la fuente única para crear proyectos. Conserva `install.php` en la raíz y `public/css/home/home.css`, como en los proyectos de referencia.
Incluye `.htaccess` para enrutar las URL de Apache hacia `index.php` y bloquear el acceso directo a los archivos internos.
También publica `deployment/nginx.conf`, un fragmento independiente de dominio, SSL y panel. Es una ayuda de despliegue, no configuración cargada por PHP. Su integración y el envío de errores a las vistas del framework se describen en [servidores web](servidores-web.md).

## Uso y extensión de los perfiles

Los perfiles se definen en `resources/install/profiles.php`. Cada uno fija si hay base de datos, autenticación, tenancy y sitio público, además de los módulos iniciales. El instalador añade los módulos predeterminados y resuelve las dependencias de los módulos elegidos. Un módulo que exige tablas no se puede instalar en `static`. Para ampliar una aplicación ya instalada, se usa el mecanismo de actualización y migraciones; volver a ejecutar el instalador no es el procedimiento de ampliación.

`managed` e `intranet` instalan autenticación y administración globales; `saas` agrega `tenants`, notificaciones y cron. La tabla `tenant_memberships` pertenece al esquema de autenticación y registra la relación usuario-tenant-rol; no representa el plan o la suscripción de un tenant. Los datos propios de cada tenant, por ejemplo un bot o una tienda, los define la aplicación. En `static` no se crea usuario ni base de datos.

`config/Permissions.php` sirve como plantilla inicial de roles y capacidades. El instalador la sincroniza tras crear el esquema de autenticación. Los valores de entorno y secretos se escriben en `.env`; `config/app.php` conserva la estructura estable. `storage/gframe-installed.json` es la única fuente de módulos instalados y conserva además el bloqueo y los hashes usados por el actualizador. Ya no se genera `config/modules.php`.

## Estado de verificación

Al 2 de octubre de 2026, las pruebas automatizadas en PHP 8.1 cubren la instalación de los cuatro perfiles, tablas SQLite y MySQL, primer superadministrador, recursos principales y rechazo de un módulo con base de datos en `static`. La prueba MySQL crea y elimina exclusivamente una base temporal con el prefijo `gframe_install_test_` y se activa con `GFRAME_TEST_MYSQL=1`. Una prueba HTTP levanta cada perfil temporalmente y comprueba portada o redirección privada, login donde corresponde, `robots.txt`, Bootstrap y bloqueo del instalador tras completar la instalación. En `static` también comprueba `Disallow: /` con la indexación desactivada. Las operaciones propias de Auth, Multimedia, Notificaciones y otros módulos se prueban en sus suites; no forman parte del contrato del instalador. La ejecución con PHP 8.4 portátil encontró restricciones del entorno para conexiones MySQL y servidores HTTP; no se considera aprobada esa integración. Los resultados completos y la limitación del recorrido en navegador están en [preparación de 0.9.0](preparacion-0.9.0.md).

La validación inicial de la cuenta administrativa y la comprobación de conflictos de configuración ocurren antes de publicar archivos. Si falla la publicación o un paso posterior, se retiran los tres archivos de configuración recién creados; el proyecto puede reintentar la instalación sin sobrescribir archivos anteriores. La cuenta administrativa se crea al final y se retira si falla la escritura del bloqueo. Los esquemas MySQL no admiten una reversión global: las tablas creadas pueden permanecer tras un fallo, pero sus sentencias de creación son idempotentes y el reintento las reutiliza. No se deben borrar manualmente bases de datos existentes para reintentar.

Para crear un proyecto:

```bash
composer new -- ../mi-proyecto
```

Use `composer new -- --help` para consultar la ayuda sin crear archivos.

El directorio debe estar vacío. Después se instalan las dependencias dentro del proyecto y se abre `install.php`. El asistente ofrece los perfiles `static`, `managed`, `intranet` y `saas`; no se mantienen plantillas independientes para cada uno.

SQLite es una alternativa completa para proyectos que no necesitan MySQL. Autenticación, roles, multimedia y notificaciones incluyen esquemas para ambos motores.

La configuración generada incluye `session.idle_timeout`, con 1800 segundos de forma predeterminada, y `media.scope`. El ámbito multimedia inicial es `global` en aplicaciones administradas y `tenant` en SaaS; también puede cambiarse a `user`.
