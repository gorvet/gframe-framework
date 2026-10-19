# Verificación de implementación

2026-10-19; Windows/PHP 8.3.35, SQLite y MySQL local con esquemas temporales eliminados al terminar.

- `php packages/phpunit/phpunit/phpunit --filter ContentTrash --log-junit .phpunit.cache/trash-implementation.xml`: 2 pruebas, 45 aserciones, sin omisiones; exit 0.
- Mismo comando con `GFRAME_TRASH_TEST_ENGINE=mysql`, log `trash-mysql-implementation.xml`: 2 pruebas, 45 aserciones, sin omisiones; exit 0. Lifecycle/rollback en MySQL; la competencia entre procesos usa SQLite en ambas ejecuciones.
- `php packages/phpunit/phpunit/phpunit --filter 'ContentTrash|ContentRevisions|ContentEditingTest' --log-junit .phpunit.cache/trash-regression.xml`: 9 pruebas, 187 aserciones, sin omisiones; exit 0.

Los casos incluyen revisión con mayúscula/espacio diferente, estado incorrecto, tenant ajeno, escrituras ordinarias rechazadas mediante scope activo, relaciones preservadas, fallo de limpieza después de borrar una relación, rollback de revisión, transacción anidada, OR pendiente, conexión nombrada y compatibilidad con historial.

No se han modificado consumidores ni publicado una versión. La capacidad es optativa y no añade filtros globales ni tablas; las consultas públicas y la política de retención de relaciones siguen perteneciendo al proyecto.
