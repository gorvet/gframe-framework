# Tareas — Protección de escritura

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Comparación independiente de contenido y aviso de salida (CA-01).
  Verificar: sistema; navegador real muestra beforeunload, cancelar conserva texto; saveAll y envío prevenido no lo desactivan; contenido idéntico no avisa.
- [x] T2 — Copias locales, recuperación y fallos (CA-02, CA-03).
  Verificar: sistema; recarga con storage real, recuperación/deshacer, descarte/cancelación, otra identidad y dos pestañas; entradas inválidas/caducadas y fallo de cuota; no restaurar automáticamente.
- [x] T3 — Snapshot confirmado, carreras y ciclo de vida (CA-04).
  Verificar: sistema; editar durante petición, confirmar snapshot y conservar cambios posteriores; respuesta tardía tras destroy/init rechazada; sin escritura desde instancia retirada.
- [x] T4 — Concentración, esquema y regresión (CA-05).
  Verificar: sistema y estructural; teclado/navegación, HTML sin IDs nuevos, escritorio/móvil y pruebas previas; docs, skill, PHP, JS y enlaces.

## Nota de trabajo

Aplicar skills canónicas orchestrator, frontend-admin, ui-design-clean y framework-maintenance y receta content-editor-search. Fuentes del módulo, UI/copy PHP y controles nativos TinyMCE. No usar plugin autosave predeterminado con clave implícita ni tocar vendor. Se conserva pendiente de publicación y el trabajo previo. Por qué ahora: protección priorizada por el usuario.
