# Tareas — 01-formularios-publicos

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Spec:** [SPEC.md](../SPEC.md)
**Estado:** auditada

## Listado

- [x] T1 — Implementar y probar: visitante con formulario protegido (CA-01; SPEC.md y CONCILIACION.md).
  Impacto: runtime, configuración, persistencia, seguridad y documentación según el criterio.
  Verificar: nivel `sistema`; reproducir los estímulos y casos negativos de CA-01 mediante la suite focalizada; oráculo: resultado observable descrito en el criterio. Para nivel ambiental, registrar host y perfil; Linux no disponible queda pendiente.

- [x] T2 — Implementar y probar: token ausente, manipulado, de otra sesión u origen inválido (CA-02; SPEC.md y CONCILIACION.md).
  Impacto: runtime, configuración, persistencia, seguridad y documentación según el criterio.
  Verificar: nivel `sistema`; reproducir los estímulos y casos negativos de CA-02 mediante la suite focalizada; oráculo: resultado observable descrito en el criterio. Para nivel ambiental, registrar host y perfil; Linux no disponible queda pendiente.

- [x] T3 — Implementar y probar: autenticar, caducar sesión o usar varias pestañas (CA-03; SPEC.md y CONCILIACION.md).
  Impacto: runtime, configuración, persistencia, seguridad y documentación según el criterio.
  Verificar: nivel `sistema`; reproducir los estímulos y casos negativos de CA-03 mediante la suite focalizada; oráculo: resultado observable descrito en el criterio. Para nivel ambiental, registrar host y perfil; Linux no disponible queda pendiente.

- [x] T4 — Implementar y probar: rutas `noSession()` y páginas con caché pública (CA-04; SPEC.md y CONCILIACION.md).
  Impacto: runtime, configuración, persistencia, seguridad y documentación según el criterio.
  Verificar: nivel `sistema`; reproducir los estímulos y casos negativos de CA-04 mediante la suite focalizada; oráculo: resultado observable descrito en el criterio. Para nivel ambiental, registrar host y perfil; Linux no disponible queda pendiente.

- [x] T5 — Implementar y probar: formularios y rutas públicos distribuidos por el framework (CA-05; SPEC.md y CONCILIACION.md).
  Impacto: runtime, configuración, persistencia, seguridad y documentación según el criterio.
  Verificar: nivel `sistema`; reproducir los estímulos y casos negativos de CA-05 mediante la suite focalizada; oráculo: resultado observable descrito en el criterio. Para nivel ambiental, registrar host y perfil; Linux no disponible queda pendiente.

## Trazabilidad

| Criterio | Tareas | Verificación prevista |
|---|---|---|
| CA-01 | T1 | Prueba y evidencia del criterio; anotar límites ambientales |
| CA-02 | T2 | Prueba y evidencia del criterio; anotar límites ambientales |
| CA-03 | T3 | Prueba y evidencia del criterio; anotar límites ambientales |
| CA-04 | T4 | Prueba y evidencia del criterio; anotar límites ambientales |
| CA-05 | T5 | Prueba y evidencia del criterio; anotar límites ambientales |

## Verificación global

- [x] Tests y lints relevantes.
- [x] Documentación, configuración y migraciones actualizadas.
- [x] Sin cambios fuera del alcance aprobado.

## Fuera de este listado

Las exclusiones de CONCILIACION.md siguen vigentes. No publicar, desplegar ni modificar sitios consumidores.

### Notas de implementación (no auditadas)

Skills canónicas: gframe-orchestrator, gframe-background-jobs, gframe-core-architecture, gframe-framework-maintenance; auth-access, backend, orm-models y frontend-public para sus superficies. Preservar cambios previos de medios y CHANGELOG.

## Evidencia final de implementación — 2026-10-15

Suite completa final: 491 pruebas, 4701 aserciones, 11 omitidas por requisitos de entorno, sin fallos. MySQL focalizado: 15 pruebas/126 aserciones, sin omisiones. Verificación focalizada de actualización/migraciones/HTTP/CSRF: 32 pruebas/877 aserciones, 6 omisiones MySQL cubiertas por la ejecución MySQL separada. Sintaxis: 431 archivos PHP válidos y lint posterior de MigrationRunner válido. Skills: 14 válidas y 79 enlaces comprobados. Markdown: 25 archivos con 0 enlaces locales ausentes. Revisión editorial: 23 textos y 0 errores críticos antes de las notas finales; revisión manual de estas notas.

Comandos y cobertura: [VERIFICACION.md](../VERIFICACION.md). No constituye auditoría ni cierre de etapa.

## Cierre auditado — 2026-10-19

[AUDIT.md](AUDIT.md), pasada 2 coincide; [delta 01](TAREAS-delta-01.md) implementado. [Ledger común](../../cron-configurable/CI-20261019.md), E-10/E-12. Las notas anteriores conservan el estado histórico de implementación; sin trabajo activo.
