# Complemento local de GFrame

Alcance: manifiesto en la raíz, distribución de las catorce skills canónicas e instalación local en Codex con actualización después de operaciones de Git. El usuario confirmó esta opción y rechazó comprobaciones por minuto. Sin cambios del runtime ni de aplicaciones instaladas.

Instrucciones aplicadas: `skills/gframe-orchestrator/SKILL.md`, `skills/gframe-framework-maintenance/SKILL.md` y sus referencias de mantenimiento y distribución. Plugin Creator, modo de complemento local.

Verificación realizada:

- `tests/BuildSkillsPluginTest.ps1`: 225 comprobaciones correctas de empaquetado, referencias, hashes, reproducibilidad y rechazos.
- `tests/SyncCodexPluginTest.ps1`: 12 comprobaciones correctas con el CLI real y configuración temporal, incluidos actualización por hook, código de salida y conservación del hook anterior, idempotencia y rechazo de referencias rotas.
- `php bin/validate-skills.php`: 14 skills y 79 enlaces locales válidos.
- Instalación real con `bin/sync-codex-plugin.ps1 -RegisterGitHooks`; hashes verificados contra los archivos de la caché generada por el CLI, sin edición manual de esa caché.
- `codex plugin list --marketplace gframe-local --json`: `gframe-skills@gframe-local` instalado y habilitado.
- Hooks `post-merge`, `post-checkout` y `post-rewrite` registrados en `.git/hooks` del checkout. Sin tareas programadas.

La validación YAML opcional no se ejecutó: el Python disponible no tiene PyYAML. No se modificó frontmatter ni metadata YAML. La carga en este chat no se puede renovar desde el script; debe comprobarse al abrir otro chat o reiniciar Codex.
