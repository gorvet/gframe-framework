# Qué instala cada perfil

Los perfiles de instalación no son plantillas de negocio. Definen **capacidades base**, si existe base de datos, autenticación, tenancy y exposición pública. Después, el proyecto sigue siendo responsable de sus entidades y reglas de negocio.

La fuente actual de perfiles es `resources/install/profiles.php`.

## Resumen

| Perfil | Base de datos | Auth | Tenancy | Público | Módulos exigidos por el perfil |
| --- | --- | --- | --- | --- | --- |
| `static` | no | no | no | sí | ninguno |
| `managed` | sí | sí | no | sí | `auth-ui`, `self-account`, `admin-panel`, `user-admin`, `media-library` |
| `intranet` | sí | sí | no | no | `auth-ui`, `self-account`, `admin-panel`, `user-admin`, `media-library` |
| `saas` | sí | sí | sí | sí | `auth-ui`, `self-account`, `admin-panel`, `user-admin`, `media-library`, `notifications`, `cron-runner` |

A esos módulos se suman siempre los módulos marcados como `default` en el catálogo y las dependencias que resuelva `ModuleCatalog`.

Por tanto, la columna anterior no pretende enumerar cada librería técnica publicada: enumera las **capacidades funcionales obligatorias del perfil**.

## Base común

`ProjectInstaller` construye la selección final así:

```text
módulos default del catálogo
+ módulos exigidos por el perfil
+ módulos opcionales elegidos por el usuario
+ dependencias transitivas
```

Después `ModuleCatalog::resolve()` ordena y completa dependencias.

Eso significa que no debes mantener manualmente una segunda lista de dependencias técnicas del perfil.

La base visual predeterminada se describe en [Catálogo y dependencias](modulos-opcionales.md).

## `static`: sitio sin base de datos

Configuración del perfil:

```text
database = false
auth = false
tenancy = false
public = true
modules = []
```

Es apropiado para:

- landing pages;
- documentación;
- sitio público servido desde archivos;
- frontend que consume una fuente externa;
- proyecto que no necesita persistencia propia de GFrame.

### Qué no instala

No instala automáticamente:

- usuarios;
- roles;
- sesiones administradas por base de datos;
- panel administrativo;
- biblioteca multimedia que requiera esquema;
- tenants.

Los módulos opcionales cuyo árbol de dependencias necesita esquema no se ofrecen para un perfil sin base de datos.

`InstallationProfileCatalog::optionalModules()` realiza esa exclusión comprobando `schemas` y `requires_schema` en el módulo y sus dependencias.

### Sesión

Al no existir configuración de base de datos, `ProjectConfigWriter` genera `SESSION_DRIVER=native`.

Eso no convierte el perfil en una aplicación autenticada: simplemente define el almacenamiento de sesión por defecto si alguna parte del proyecto utiliza sesión PHP.

## `managed`: aplicación administrada global

Configuración:

```text
database = true
auth = true
tenancy = false
public = true
```

Módulos exigidos:

```text
auth-ui
self-account
admin-panel
user-admin
media-library
```

Es la base para una aplicación o sitio con administración **global**, sin aislamiento de autorización por tenant.

### Esquema Auth

Con `auth=true`, `SchemaInstaller` ejecuta el esquema de autenticación. En MySQL el esquema actual crea como base:

```text
roles
users
tenant_memberships
gframe_sessions
```

Aunque `tenant_memberships` forme parte del esquema Auth compartido, `managed` no activa tenancy como modelo de aplicación. No crees membresías artificiales si tu autorización es realmente global.

Los roles base creados por el esquema son:

```text
superadministrator
registered
```

Las plantillas adicionales del proyecto se sincronizan después mediante `PermissionTemplateSynchronizer`.

### Primer usuario

El instalador exige credenciales para el primer superadministrador y, después de preparar esquema/configuración/módulos, crea esa cuenta mediante `SuperadministratorInstaller`.

Registrar usuarios normales posteriormente no los convierte en administradores.

### Multimedia

`media-library` es obligatoria en este perfil y añade su propio esquema/migraciones y recursos.

El ámbito predeterminado de media se genera como `global` cuando el proyecto no tiene tenancy, salvo que la instalación indique explícitamente otro scope compatible.

## `intranet`: misma base funcional, aplicación no pública

`intranet` exige los mismos módulos que `managed`:

```text
auth-ui
self-account
admin-panel
user-admin
media-library
```

La diferencia estructural del perfil es:

```text
public = false
```

No existe otro conjunto oculto de módulos «de intranet».

### Efecto durante la instalación

Cuando `public=false`, `ProjectInstaller` fuerza:

```text
seo_enabled = false
seo_allow_indexing = false
seo_sitemap = false
seo_robots = true
seo_llms = false
metricool_enabled = false
metricool_hash = ''
```

Por tanto, `intranet` no es solo una etiqueta visual. También genera configuración para no tratar la aplicación como sitio público/indexable.

La autenticación sigue siendo la misma infraestructura que en `managed`.

## `saas`: aplicación multitenant

Configuración:

```text
database = true
auth = true
tenancy = true
public = true
```

Módulos exigidos:

```text
auth-ui
self-account
admin-panel
user-admin
media-library
notifications
cron-runner
```

Además del esquema Auth, `SchemaInstaller` ejecuta primero el esquema de tenancy.

### Tabla genérica `tenants`

El esquema actual crea:

```text
tenants
  tenant_id
  name
  slug
  status
  created_at
  updated_at
```

No contiene una columna genérica `owner_id`.

### Lo que SaaS NO hace automáticamente

Elegir `saas` no crea por sí solo:

- un tenant al registrar cada usuario;
- la primera membresía owner;
- planes;
- suscripciones;
- facturación;
- límites comerciales;
- propiedad de las entidades de tu negocio.

El framework instala la **infraestructura de autorización multitenant**. El proyecto define qué entidad representa el tenant y cuándo se crea.

Consulta [Identidad, autenticación, permisos y sesiones](identidad-autorizacion.md) y [Roles, permisos y membresías](permisos.md).

### Multimedia

Como `tenancy=true`, el scope de media generado por defecto es `tenant`, salvo selección explícita compatible.

### Notifications y Cron

`notifications` y `cron-runner` forman parte obligatoria de `saas` según el catálogo de perfiles actual.

Eso deja disponible infraestructura para inbox/colas y tareas programadas, pero no significa que cada acción del negocio genere automáticamente notificaciones o cron jobs.

## Qué hace realmente `ProjectInstaller`

La instalación sigue, de forma simplificada, este recorrido:

```text
valida raíz del proyecto
  ↓
rechaza si ya existe storage/gframe-installed.json
  ↓
carga perfil
  ↓
resuelve defaults + módulos del perfil + opcionales + dependencias
  ↓
comprueba compatibilidad con base de datos
  ↓
valida superadmin si auth=true
  ↓
publica scaffolding base
  ↓
conecta DB si corresponde
  ↓
instala tenancy/auth/esquemas de módulos
  ↓
sincroniza plantillas de permisos
  ↓
escribe config/app.php y .env
  ↓
publica assets/rutas/archivos declarados por módulos
  ↓
registra baseline de migraciones
  ↓
crea superadministrador si corresponde
  ↓
escribe storage/gframe-installed.json
```

Ese lock registra, entre otros:

- fecha;
- perfil;
- módulos realmente resueltos;
- versión del framework;
- hashes de archivos administrados.

## Configuración generada

`ProjectConfigWriter` escribe:

```text
config/app.php
.env
```

La configuración estructurada incluye actualmente:

```text
app
database
session
auth
tenancy
media
seo
analytics
```

`.env` recibe valores como:

```text
APP_NAME
APP_ENV
APP_DEBUG
APP_URL
APP_KEY
SESSION_DRIVER
DB_* cuando corresponde
variables requeridas por módulos
```

`APP_KEY` se genera con 32 bytes aleatorios y se almacena en formato `base64:...`.

## Módulos opcionales

El asistente agrupa actualmente opciones como:

```text
Comunicación
Contenido y búsqueda
Formularios
Presentación y multimedia
Herramientas
Integraciones
```

El usuario no elige dependencias técnicas individuales: el catálogo las resuelve.

Además, se eliminan de la lista:

- módulos ya requeridos por defaults/perfil;
- módulos que necesiten base de datos en `static`;
- módulos o dependencias incompatibles con el perfil mediante `install_profiles`.

Consulta [Catálogo y dependencias](modulos-opcionales.md).

## Elegir perfil

### Elige `static` si

Tu proyecto no necesita base de datos ni Auth de GFrame.

### Elige `managed` si

Existe una administración global y los permisos no dependen de empresas/clientes/espacios separados.

### Elige `intranet` si

Necesitas la misma infraestructura administrada, pero la aplicación completa debe tratarse como privada/no pública.

### Elige `saas` si

Una misma instalación necesita autorización aislada por tenant y una cuenta puede pertenecer a distintos tenants con roles diferentes.

No elijas `saas` solo porque el producto se cobre por suscripción. **SaaS comercial y autorización multitenant no son la misma decisión técnica.**

## Después de instalar

El perfil no queda «congelado» como única fuente de capacidades. Los módulos opcionales pueden añadirse posteriormente mediante:

```bash
composer gframe:update -- --modules=LISTA_COMPLETA --dry-run
composer gframe:update -- --modules=LISTA_COMPLETA
```

Consulta [Actualizaciones](actualizaciones.md) y [Usar módulos en una aplicación](modulos-en-aplicacion.md).
