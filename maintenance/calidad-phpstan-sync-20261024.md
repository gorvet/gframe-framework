# PHPStan y sincronización del complemento

Baseline de trabajo: main después de integrar PR #2, ad9df0086d770908c998f9eead840d986950135d. Alcance autorizado: resolver el análisis estático informativo y el hook que apunta a una versión retirada de Codex Desktop. Skills canónicas: orchestrator, framework-maintenance y core-architecture. Se conservan los contratos de runtime y los cambios existentes.

El hook debe guardar el nombre estable del CLI y resolverlo al ejecutarse, conservando comandos personalizados. Se verifican vista previa, idempotencia, preservación del hook original y sincronización real con hashes. La corrección no instala otras skills ni modifica aplicaciones consumidoras.

PHPStan debe reconocer constantes configurables por entorno y métodos de estado que cambian tras iniciar una transacción. Se separan los falsos positivos de configuración de los diagnósticos heredados; cualquier baseline debe generarse con la herramienta real, registrar mensajes/rutas/recuentos y conservar la detección de errores nuevos y entradas obsoletas. No se rebaja el nivel ni se excluyen carpetas para pasar CI.
