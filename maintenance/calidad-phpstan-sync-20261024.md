# PHPStan y sincronización del complemento

Baseline de trabajo: main después de integrar PR #2, ad9df0086d770908c998f9eead840d986950135d. Alcance autorizado: resolver el análisis estático informativo y el hook que apunta a una versión retirada de Codex Desktop. Skills canónicas: orchestrator, framework-maintenance y core-architecture. Se conservan los contratos de runtime y los cambios existentes.

El hook debe guardar el nombre estable del CLI y resolverlo al ejecutarse, conservando comandos personalizados. Se verifican vista previa, idempotencia, preservación del hook original y sincronización real con hashes. La corrección no instala otras skills ni modifica aplicaciones consumidoras.

PHPStan debe reconocer constantes configurables por entorno y métodos de estado que cambian tras iniciar una transacción. Se separan los falsos positivos de configuración de los diagnósticos heredados; cualquier baseline debe generarse con la herramienta real, registrar mensajes/rutas/recuentos y conservar la detección de errores nuevos y entradas obsoletas. No se rebaja el nivel ni se excluyen carpetas para pasar CI.

## Verificación y deuda conservada

- PHPStan 2.3.1 fijado en Composer y lock; sin actualizaciones de dependencias de runtime.
- Baseline generado por la herramienta real en GitHub Actions, job 120788898127: 68 diagnósticos heredados, delimitados por mensaje, identificador, ruta y recuento. Siguen pendientes de corregir; no son 68 correcciones de código.
- CI ejecuta el análisis sin `continue-on-error` ni regeneración del baseline. Comprueba además que un retorno incompatible nuevo produce `return.type`; las entradas obsoletas también fallan.
- Hook Codex: 13 comprobaciones aisladas aprobadas en el checkout anterior, incluida preservación del hook original e idempotencia; la ejecución real del hook también pasó. Su código no cambia en esta continuación.
- En el worktree aislado, `composer validate --strict`, lint del bootstrap y validación de enlaces de skills aprobados. La instalación local de dependencias no pudo completarse por fallo de red de Git; el análisis final queda pendiente de CI, no se presenta como aprobado localmente.

Coordinación: Codex trabaja en `gframe-codex-calidad`, rama `codex/phpstan-followup`; Claude conserva la CLI y hooks de reservas en su worktree. Canal común externo y comprobación manual antes de editar.
