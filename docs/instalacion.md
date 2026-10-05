# Instalación

## Requisitos

- PHP 8.1 o superior; la versión 1.x se ha probado en PHP 8.1.
- Composer 2 y las extensiones DOM, JSON, Mbstring y OpenSSL.
- Apache con `mod_rewrite` o Nginx con PHP-FPM.
- MySQL o SQLite si el proyecto utiliza base de datos.

## Crear el proyecto

Descarga el repositorio y genera un proyecto nuevo:

```bash
git clone https://github.com/gorvet/gframe-framework.git
cd gframe-framework
composer install
composer new -- ../mi-proyecto
cd ../mi-proyecto
composer install
```

`composer new` es un script del repositorio de GFrame, no un comando nativo de Composer. El destino debe ser nuevo o estar vacío. El proyecto generado descarga `gorvet/gframe` mediante Composer; no depende de la copia del repositorio utilizada para crearlo.

Apunta la raíz del servidor al directorio `mi-proyecto`, no a `public/`. Configura [Apache o Nginx](servidores-web.md) y abre la URL del proyecto. Antes de instalar, las peticiones de aplicación redirigen al asistente `install.php`.

## Elegir el tipo de proyecto

| Perfil | Base de datos | Cuenta administrativa | Uso |
| --- | --- | --- | --- |
| `static` | No | No | Sitio público con contenido en archivos o fuentes externas. |
| `managed` | Sí | Sí | Sitio público con administración global. |
| `intranet` | Sí | Sí | Aplicación privada sin portada pública ni indexación. |
| `saas` | Sí | Sí | Aplicación con usuarios y datos separados por tenant. |

El perfil determina los requisitos iniciales; no crea el contenido ni las reglas de negocio. En SaaS se instala la tabla `tenants`, pero crear tenants y asignar sus primeras membresías corresponde a la aplicación. Consulta [roles y permisos](permisos.md).

## Completar el asistente

1. **Proyecto:** indica el nombre y el perfil. Los perfiles con autenticación piden el correo y una contraseña para el primer superadministrador.
2. **Base de datos:** elige MySQL o SQLite y comprueba la conexión. Este paso no aparece en `static`.
3. **Módulos opcionales:** selecciona las herramientas que necesites. Los obligatorios y las dependencias se incluyen automáticamente y no aparecen como opciones.
4. **Confirmación:** revisa e instala.

Puedes dejar todos los opcionales sin seleccionar e [instalarlos más adelante](actualizaciones.md#añadir-módulos-después-de-instalar). El catálogo distingue [módulos propios y bibliotecas de terceros](modulos-opcionales.md).

### Conexión a la base de datos

En MySQL, servidor, usuario y contraseña deben permitir conectarse al servidor. El nombre de una base que todavía no existe no es un error de credenciales:

- Si no existe, la instalación final intenta crearla; el usuario necesita permiso `CREATE DATABASE`.
- Si existe y está vacía, crea las tablas del perfil y los módulos.
- Si contiene tablas, rechaza la instalación. No borra ni reemplaza datos.
- Si falla la conexión o el acceso, muestra el error y permite corregir los campos.

«Comprobar conexión» no crea bases ni tablas. La instalación vuelve a comprobar el destino al confirmar. Si un fallo dejó tablas creadas, revisa ese destino antes de reintentar o utiliza otra base vacía.

## Resultado de la instalación

```text
mi-proyecto/
├── app/                Controladores, modelos, servicios y vistas del proyecto
├── config/             Configuración, rutas, permisos y metadatos
├── packages/           Dependencias descargadas por Composer
├── public/             CSS, JavaScript, imágenes y recursos públicos
├── storage/            Registro de instalación y datos de ejecución
├── .env                Valores del entorno y secretos
├── .htaccess           Reglas de Apache
├── nginx.conf          Fragmento de reglas de Nginx
├── index.php           Punto de entrada de la aplicación
└── install.php         Asistente bloqueado después de instalar
```

`storage/gframe-installed.json` registra los módulos, migraciones y archivos administrados. También impide reinstalar: las visitas posteriores a `install.php` redirigen a la aplicación. No necesitas borrar el asistente ni bloquearlo manualmente.

Los módulos MVC conservan sus originales dentro del paquete. En `app` se crean carpetas vacías para las capas que permiten personalización; no se duplican los originales. Consulta [estructura de módulos](modulos-runtime.md).

## Comprobar la instalación

En un perfil administrado, abre `/login` y accede con la cuenta inicial. Comprueba también una URL inexistente: debe responder con estado HTTP 404 y mostrar la página del framework.

Antes de publicar, configura la URL pública, el correo y el modo de producción en [la configuración del entorno](configuracion.md). Las campañas y colas requieren además los trabajadores descritos en sus guías.

Para una versión nueva, utiliza [el actualizador](actualizaciones.md), no vuelvas a ejecutar el instalador.
