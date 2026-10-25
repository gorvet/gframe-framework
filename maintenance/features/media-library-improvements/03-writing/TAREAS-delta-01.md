# Delta 01 — Cambios pendientes del editor

**Principal:** no
**Cierra:** AUDIT.md pasada 1; CA-02; E-02
**Hueco:** `editor.save()` sincroniza el textarea y limpia isDirty, aunque el artículo todavía no se ha guardado.
**Estado:** auditada

- [x] D01-T1 — Sincronizar contenido sin limpiar la marca de cambios pendientes (CA-02).
  Verificar: sistema; repetir media-writing.browser.test.cjs con isDirty verdadero después de insertar, conservando undo/redo, recarga y controles de otras instancias; ejecutar regresiones JS del principal.

## Fuera de este delta

Cambiar saveAll() o el guardado del artículo de la aplicación, publicación, edición de imágenes y metadatos nuevos.

Evidencia: `editor.save({set_dirty: false})`; la aserción isDirty pasa en Chrome. Regresión principal JS: seis pruebas pasan, exit 0, 40,725 s. Nueva fixture visual sintética de 360×240 para comprobar galerías, con una nueva ejecución de escritura de 29,801 s, exit 0. No cambia el comportamiento de la fixture original sin la variable de prueba. Listo para nueva auditoría, sin veredicto de cierre en este delta.
