# Instalación

GFrame ofrece tres perfiles iniciales:

- `static`: sitio sin base de datos ni autenticación;
- `managed`: aplicación administrada global;
- `saas`: aplicación multitenant con base de datos compartida.

Una intranet no requiere otro perfil. Es una aplicación administrada con acceso privado y SEO desactivado.

## Instalador visual

El esqueleto del proyecto incluye `public/install/index.php`. La pantalla permite elegir el perfil, MySQL o SQLite, módulos opcionales, configuración SEO, Metricool, expiración de contraseñas y la primera cuenta superadministradora.

Antes de configurar el negocio, la aplicación muestra una portada inicial de GFrame con el mensaje «La base está preparada. Lo próximo lo construyes tú.» y señala la primera vista que debe editarse. El proyecto puede reemplazarla sin modificar el framework.

El instalador:

1. comprueba la conexión;
2. crea las tablas de autenticación y de los módulos; en SaaS también instala `tenants` con la clave estándar `tenant_id`;
3. crea el primer superadministrador;
4. escribe `config/app.php`, `.env` y `config/modules.php`;
5. publica los recursos de los módulos;
6. crea `storage/gframe-installed.json` para impedir una segunda ejecución.

Al terminar debe eliminarse o bloquearse `public/install` en producción.

El esqueleto versionado en `resources/skeleton` será la fuente del paquete `gframe/app` utilizado por `composer create-project`.

Para generar localmente el repositorio distribuible desde esa fuente:

```bash
composer app:export -- ../gframe-app
```

El exportador no sobrescribe archivos existentes. Así, `resources/skeleton` continúa siendo la fuente única de la estructura inicial y `gframe/app` funciona como paquete de distribución.

SQLite es una alternativa completa para proyectos que no necesitan MySQL. Autenticación, roles, multimedia y notificaciones incluyen esquemas para ambos motores.
