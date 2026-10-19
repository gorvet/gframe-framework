# Etapa 01 — Contrato de papelera

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md).

## Entra

Contrato ORM optativo para modelos versionados, compatible con herencia de historial. Marcado recuperable, restauración y borrado definitivo confirmado; igualdad de revisión, ámbito explícito y conexión del modelo. Consultas explícitas de activos/papelera, relaciones conservadas hasta el borrado definitivo y limpieza SQL del proyecto dentro de la transacción.

## No entra

Cambios en instalaciones, UI universal, tablas de artículos, vaciado masivo, purga automática por días, archivos físicos ni publicación programada. La autorización HTTP y el filtrado de rutas públicas son del módulo consumidor.

## Dependencias

Guardado versionado e historial cerrados. La nueva capacidad no modifica sus contratos ni activa filtros globales de ORM.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** papelera y restauración conservan contenido/relaciones; requieren estado anterior, revisión exacta y ámbito; rotan revisión; llamadas repetidas o antiguas no modifican. Evidencia sistema.
- **CA-02 — invariante acumulado:** borrado definitivo exige confirmación y registro en papelera; limpieza de relaciones SQL y borrado atómicos; falla la limpieza → rollback completo. No afecta otros registros. Evidencia sistema.
- **CA-03 — condición de salida:** consultas explícitas separan activos/papelera con scope; no heredan OR pendiente; conexión nombrada preservada. Integración documentada con lectores/escritores, auth/can y POST/CSRF; sin nuevos endpoints universales. Evidencia sistema y estructural.
- **CA-04 — condición de salida:** pruebas de lifecycle, conflictos y rollback en SQLite/MySQL, concurrencia en procesos, regresión de historial, sintaxis y skills correctas. Evidencia sistema y estructural.

## Aclaración y riesgos

2026-10-19: «siguiente paso» continúa la prioridad 4 del itinerario. Las elecciones técnicas son del framework; no se presume autorización para modificar consumidores. El proyecto debe excluir la papelera de TODOS sus lectores públicos y añadir `deleted_at => null` a sus guardados; ORM genérico sigue teniendo acceso explícito a todos los registros. No se promete atomicidad para archivos ni sistemas externos.

## Preguntas abiertas

Ninguna para el contrato reutilizable descrito; la integración visual pertenece a cada módulo.
