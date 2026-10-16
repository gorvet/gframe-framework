# Tareas — etapa 02 Gestión

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Normalizar orden/vista y renderizar lista PHP; conservar predeterminados y estado URL (CA-01).
  Verificar: conductual y sistema; SQLite real con filtros, ámbitos, desempates, orden inválido; navegador cambia vista/orden y recupera URL.
- [x] T2 — Navegación y atributos en detalles con protección de estado y errores (CA-02, CA-03).
  Verificar: sistema; navegador cruza páginas, alcanza extremos, guarda/descarta, recibe error y respuesta tardía sin contaminar otro archivo.
- [x] T3 — Reproductores y acceso al archivo (CA-03).
  Verificar: sistema; navegador carga audio/vídeo local reproducible, comprueba ausencia de autoplay y libera src al cambiar/cerrar.
- [x] T4 — Compatibilidad, revisión responsive y documentación (CA-04).
  Verificar: sistema y estructural; regresión PHP/JS de medios, capturas de escritorio/móvil, teclado, lista vacía/una/múltiples páginas; lint, enlaces, skills y diff.

## Nota de trabajo

Skills canónicas: orchestrator, media-module, frontend-admin, backend, orm-models y framework-maintenance. Reutilizar Bootstrap, listado PHP, tokens y rutas; referencia de listas notification-campaigns. Baseline de trabajo: HEAD 4f901dc más etapa 01 y corrección previa del toast; conservarlos. Evidencia de implementación se registra antes de auditoría separada. No hay delegación ni cambios en instalaciones.

Evidencia: [VERIFICACION.md](VERIFICACION.md). Cierre: [AUDIT.md](AUDIT.md), pasada 1 coincide; 36 pruebas PHP/1136 aserciones y seis pruebas JS/navegador, sin omisiones.
