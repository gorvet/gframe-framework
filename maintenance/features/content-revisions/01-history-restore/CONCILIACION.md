# Etapa 01 — Historial, comparación y restauración

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md).

## Resultado observable

Consultar versiones confirmadas de un contenido, comparar sus campos, restaurar una versión anterior sin sobrescribir una edición concurrente y vaciar su historial sin borrar el contenido actual.

## Dependencias

- Guardado por revisión: etapa 01 de content-editing cerrada y subida en `ee23253`. Se conserva su contrato.

## Entra

- Historial optativo con autor, fecha UTC y campos permitidos por el modelo.
- Guardado e historial atómicos en la conexión del modelo; rollback conjunto.
- Lectura y comparación autorizadas por contenido y tenant, con salida escapada.
- Restauración explícita con revisión esperada y nueva versión, sin reemplazo automático del formulario.
- Retención de 10 revisiones por defecto, configurable a más o menos (entero positivo). Vaciado explícito de todas las revisiones del recurso autorizado, sin borrar el contenido actual.
- Instalación MySQL/SQLite, documentación, skills y verificación de concurrencia/rollback/aislamiento.

## No entra

Otras prioridades de la hoja de ruta, aplicaciones consumidoras, releases ni reconstrucción de historial previo inexistente.

## Aclaraciones

- 2026-10-19: el usuario autoriza continuar con historial, comparación y restauración y exige alineación con filosofía/skills de GFrame.
- Las decisiones técnicas del SPEC son propuestas de implementación, no preferencias detalladas atribuidas al usuario.
- 2026-10-19: decisión del usuario «10 revisiones, configurable a más o a menos» y posibilidad de borrar todas para ahorrar espacio. Cierra la pregunta de retención; no autoriza purgas de sitios instalados.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** guardar con historial → contenido y snapshot de campos permitidos se confirman juntos; fallo del historial revierte contenido; revisión obsoleta no añade entrada. Evidencia sistema.
- **CA-02 — invariante acumulado:** listar/consultar/comparar → solo revisiones del recurso y tenant autorizado, autor/fecha preservados y HTML mostrado como datos escapados, sin ejecutar contenido. Evidencia sistema y estructural.
- **CA-03 — invariante acumulado:** restaurar una entrada con revisión actual esperada → nueva revisión y nueva entrada; revisión antigua o entrada ajena no sobrescribe ni revela datos. Evidencia sistema.
- **CA-04 — invariante acumulado:** guardar más del límite → conserva las últimas 10 por defecto; límite mayor/menor configurable y válido; vaciado confirmado elimina todas las entradas de ese recurso sin cambiar contenido/revisión actuales ni historial ajeno. Evidencia sistema.
- **CA-05 — condición de salida:** módulo, migraciones MySQL/SQLite, recetas y skills → coherentes con ORM/herencia, permisos/CSRF y transacciones; pruebas relevantes pasan. Evidencia sistema y estructural.

## Riesgos y compatibilidad

El historial puede conservar datos que luego se retiraron del contenido. La retención determina espacio y posibilidad de recuperación; borrar versiones por límite no es reversible. La aplicación debe elegir campos adecuados y autorizar cada lectura/restauración.

## Preguntas abiertas

Ninguna.

## Criterio para cerrar conciliación

Retención resuelta por el usuario; se conserva el alcance reusable y se implementan los criterios anteriores.
