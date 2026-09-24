# Skills oficiales de GFrame

Los skills de `skills/` se versionan junto con el framework y son la fuente oficial para Codex y Claude Code.

## Skills incluidos

- `gframe-core-architecture`
- `gframe-backend`
- `gframe-orm-models`
- `gframe-auth-access`
- `gframe-frontend-admin`
- `gframe-frontend-public`
- `gframe-ui-design-clean`
- `gframe-framework-maintenance`

El antiguo skill de medios no se incluye todavía porque el módulo continúa en las aplicaciones y aún no forma parte del paquete GFrame.

## Instalación

Desde PowerShell:

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
