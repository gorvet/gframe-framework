# Correcciones de H-01 y H-02

Autorización: «corrige esos dos problemas». Se conservan el informe y el reproductor originales del corte 4.3.0; no se reescribe el dictamen histórico. Especialistas aplicados: orquestador, mantenimiento y ORM del repositorio. No se actualizan consumidores ni se publica una etiqueta.

## H-01

`RevisionedContentModel` ofrece `restoreHistoryFields(fields, current)` como extensión protegida del paso de restauración. El comportamiento sin extensión de publicación se conserva. `ScheduledContent` preserva los valores actuales de sus columnas de estado/fecha, incluidos nombres personalizados. El snapshot completo permanece disponible para comparación. La escritura mantiene la revisión esperada, autor, tenant, conexión y transacción habituales.

Una restauración ya no transforma un contenido programado en publicado, ni recupera una programación cancelada. Un texto que ya está publicado continúa publicado; su edición/restauración exige el permiso apropiado. No se introduce una operación implícita alternativa para restaurar estados. Los cambios de publicación continúan utilizando sus métodos autorizados. Se alinean las guías de historial/publicación y la referencia ORM.

Prueba añadida: `ScheduledContentTest::testHistoryRestorePreservesCurrentPublicationStateAndDate`. Antes del arreglo falló con `scheduled` esperado y `published` obtenido. Después verifica texto restaurado, revisión nueva, conflictos por revisión/tenant, conservación de fecha/estado, cancelación sin reactivación, snapshot nuevo coherente, rollback mediante fallo de INSERT inyectado con trigger y nombres/estados personalizados, incluido contenido que ya está publicado.

## H-02

`docs/skills.md` distingue ahora la restricción actual `^4.3` de la disponibilidad histórica del instalador PHP desde 2.0.1. No se cambia el generador ni las versiones históricas.

## Evidencia posterior al cambio

- SQLite: historial, programación y papelera, 7 pruebas/156 aserciones, sin fallos ni omisiones; `.phpunit.cache/restore-fix-sqlite.xml`.
- MySQL: restauración combinada, programación/historial, retención y papelera, 5 pruebas/144 aserciones, sin fallos ni omisiones; `.phpunit.cache/restore-fix-mysql.xml`. Se utilizan bases temporales eliminadas al finalizar.
- La prueba de dos escritores de historial sobre SQLite pasó en la suite SQLite y, por separado, con las variables MySQL activadas: 1 prueba/6 aserciones. Dos ejecuciones agrupadas anteriores terminaron intermitentemente con salida 255 de un hijo; el mensaje de esa prueba no conservó stdout y no permitió determinar la causa. No se presenta esa intermitencia como resuelta ni como fallo demostrado del motor MySQL: esa prueba de carrera utiliza SQLite.
- Lint de los dos archivos runtime y del archivo de pruebas: correcto. Skills: 14 entradas/79 enlaces válidos. `git diff --check`: correcto.
- Comprobación adyacente de preview/edición: 8 casos, 112 aserciones, cero fallos y una omisión por configuración de la integración MySQL de edición; `.phpunit.cache/restore-fix-adjacent.xml`.
- No se repite la suite general de publicación ni el navegador porque no se publica una versión y el cambio está concentrado en restauración ORM. Las omisiones ambientales del informe anterior conservan su estado.

Pendiente separado: investigar el diagnóstico de la prueba intermitente de carrera si vuelve a aparecer. Una siguiente publicación requiere sus comprobaciones de entrega y una revisión posterior del corte corregido; no se modifica la etiqueta 4.3.0.
