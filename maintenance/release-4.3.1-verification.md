# Verificación de GFrame 4.3.1

Entrega autorizada: publicar H-01/H-02, actualizar el sitio documental y añadir capturas reales. No incluye una auditoría completa de vulnerabilidades explotables del core, que queda pendiente. No se altera la etiqueta 4.3.0 ni aplicaciones de producción.

## Cambios

- Restauración con ScheduledContent: conserva estado/fecha actuales y snapshot completo; mantiene CAS, actor/tenant, conexión y transacción. Columnas y valores personalizados cubiertos. Un contenido ya publicado sigue publicado al restaurar su texto.
- Documentación de historial/publicación y skill ORM alineadas. Guía de skills: distingue `^4.3` del origen histórico del instalador en 2.0.1. Sin migraciones ni cambio de scripts de consumidores.
- Se conserva el informe original de auditoría y su reproducción; el registro de correcciones describe la comprobación posterior. La herramienta de capturas documentales solo genera una aplicación temporal y se registra como utilidad interna.

## Comprobaciones

- Suite PHP completa posterior al arreglo: 531 pruebas, 5344 aserciones, cero errores/fallos, 14 omisiones documentadas; `.phpunit.cache/release-4.3.1-php.log` y `.xml`.
- MySQL específico de restauración/programación/historial/papelera: 5 pruebas, 144 aserciones, sin omisiones/fallos; `.phpunit.cache/release-4.3.1-mysql.xml`.
- Lint: 494 PHP válidos. JavaScript: 98 casos, 81 aprobados, 17 omisiones de navegador, cero fallos.
- Skills: 14 entradas y 79 enlaces válidos. Composer: validate --strict correcto y audit sin avisos.
- La regresión combinada falló antes del cambio (`published` en vez de `scheduled`) y pasó después en SQLite y MySQL; incluye rollback por trigger que hace fallar INSERT, scope/revisión obsoletos, columnas personalizadas y conservación de estado publicado.
- La suite completa de esta entrega incluye y supera la carrera de escritores que había fallado intermitentemente en ejecuciones agrupadas previas. No se cambia su implementación ni se afirma haber diagnosticado aquella intermitencia.

Las omisiones de PHP y navegador conservan los límites del informe de alineación: no se certifica infraestructura Apache/Nginx/FPM, Redis o despliegue de producción por estos resultados. Las capturas de ayuda ejercitan Chrome y una aplicación temporal, pero no sustituyen las 17 pruebas de navegador omitidas.

## Sitio documental

Las cuatro capturas y la ilustración original se versionan en el repositorio del sitio, no en el paquete. El inventario `docs/INVENTARIO-IMAGENES.md` de ese repositorio identifica archivo, guía, origen, uso y pendientes. El fondo de Base Confías no se utiliza. El sitio debe fijar el lock a esta etiqueta tras su publicación, ejecutar la actualización conservando personalizaciones y validar páginas/recursos antes del push.
