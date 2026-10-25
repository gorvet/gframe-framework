# Delta 02 — Textos en las vistas del módulo

**Principal:** no
**Cierra:** AUDIT.md pase 2, no coincide; CA-05, E-04/E-05 y revisión frontend-admin/backend.
**Estado:** auditada

- [x] D02-T1 — Pasar los nuevos textos de estado a las vistas PHP del selector y del progreso; JS solo consume esos datos y coordina comportamiento.
  Verificar: `sistema`; misma prueba de navegador, estados visibles y guardado sin cambios; PHP y validador de skills/enlaces.

Se conservan textos y APIs preexistentes, sin una reescritura general del módulo.

Verificación de implementación: test de recorrido real aprobado, sin omisiones; DocumentationLinksTest 4/121, skills 14/79, sintaxis PHP/JS correcta. Los estados nuevos proceden de mediaPickerModal.php y _uploadProgress.php; los payloads del backend conservan prioridad.
