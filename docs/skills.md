# Skills oficiales de GFrame

Los skills de `skills/` se versionan junto con el framework y son la fuente oficial para Codex y Claude Code.

## Skills incluidos

- `gframe-core-architecture`: arranque, configuración, rutas, middleware y conexión entre framework y aplicación.
- `gframe-backend`: controladores, servicios, respuestas HTTP y permisos.
- `gframe-orm-models`: modelos, consultas, conexiones, dialectos y transacciones.
- `gframe-auth-access`: autenticación, sesiones, roles, permisos y cuenta propia.
- `gframe-frontend-admin`: vistas administrativas, meta, formularios, validación HTML5, AJAX y feedback.
- `gframe-frontend-public`: vistas públicas, recursos, metadatos, SEO y contenido.
- `gframe-ui-design-clean`: organización de pantallas y patrones Bootstrap adaptados al proyecto.
- `gframe-framework-maintenance`: mantenimiento del paquete, pruebas, documentación y publicación.
- `gframe-media-module`: biblioteca multimedia, ámbitos, cargas, selectores y relaciones.

Los skills guían al asistente de desarrollo; no son módulos PHP ni recursos que se carguen al ejecutar la aplicación.

## Instalación

Desde PowerShell, situado en la raíz del repositorio del framework o en `packages/gorvet/gframe` de un proyecto instalado:

```powershell
.\bin\install-skills.ps1 -Target Codex
.\bin\install-skills.ps1 -Target Claude
.\bin\install-skills.ps1 -Target All
```

El instalador actualiza los skills del usuario sin borrar otros skills instalados.

El skill `gframe-ui-design-clean` incluye criterios de UX y patrones Bootstrap para formularios, filtros, resultados, tablas, vistas con lateral, estados y responsive. Debe adaptarse a la identidad visual de cada aplicación; no funciona como una plantilla estética cerrada.

## Validación

```bash
composer skills:check
```

`composer check` también valida los skills. Toda modificación de rutas, contratos, configuración, permisos, componentes o flujos documentados debe revisar el skill correspondiente.
