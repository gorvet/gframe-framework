# Etapa 01 — Formularios públicos

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; [AUDIT.md](AUDIT.md), pasada 2, incorpora el delta 01 y completa las verificaciones acordadas. La nota de auditoría pendiente al implementar es histórica.
**Spec:** [SPEC.md](../SPEC.md) § Alcance propuesto R-01–R-06.

## Resultado observable

Un visitante puede enviar formularios públicos con CSRF validado sin iniciar sesión como usuario.

## Dependencias

- Ninguna dependencia del cron configurable.

## Entra

- Generación de tokens anónimos, helper de formulario/AJAX y validación.
- Revisión de exclusiones en rutas públicas del framework, rotación y caducidad.
- Compatibilidad con caché y rutas sin sesión; pruebas y guía de actualización.

## No entra

- Cron, cambios en aplicaciones consumidoras o eliminación de antispam.

## Aclaraciones

- **Baseline:** propuesta del 2026-10-15; no hay diseño técnico cerrado.
- El usuario planteó proteger formularios de visitantes; la selección real/virtual del cron no modifica esta necesidad independiente.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** visitante con formulario protegido → obtiene token de su sesión y puede enviar una acción válida; evidencia `sistema`.
- **CA-02 — invariante acumulado:** token ausente, manipulado, de otra sesión u origen inválido → se rechaza la acción sin confundir anonimato con falta de autorización de login; evidencia `sistema`.
- **CA-03 — invariante acumulado:** autenticar, caducar sesión o usar varias pestañas → se cumple la política de rotación y recuperación acordada sin invalidación en cada visita; evidencia `sistema`.
- **CA-04 — invariante acumulado:** rutas `noSession()` y páginas con caché pública → se respeta la ausencia de sesión y no se comparte el token de otro visitante; evidencia `sistema`.
- **CA-05 — invariante acumulado:** formularios y rutas públicos distribuidos por el framework → generación, envío y validación coinciden con sus exclusiones documentadas; evidencia `sistema`.

## Riesgos y compatibilidad

- Cambiar exclusiones de CSRF puede romper formularios personalizados; requiere guía explícita.
- La caché de HTML con tokens exige un contrato deliberado.

## Bloqueos

- Ninguno para continuar el diseño.

## Preguntas abiertas

- Ninguna de producto. Las elecciones técnicas y limitaciones de validación se registran a continuación.

## Criterio para cerrar conciliación

Acordar esas decisiones y los criterios antes de generar tareas o implementar.

## Cierre y decisiones de implementación — 2026-10-15

El usuario autorizó expresamente implementar ambas features, actualizar documentación y crear el commit. Esta autorización cierra el alcance propuesto; no constituye evidencia de ejecución ni de auditoría.

Se genera token al iniciar una sesión normal mediante SessionRuntime, sin iniciar sesiones desde el helper. Se mantienen nombres csrfToken/csrfTimestamp, se renuevan al autenticar y se utiliza comparación constante. El helper se incluye en formularios públicos originales y el puente general; se retiran exclusiones CSRF de auth-ui. Las páginas con sesión/token usan Cache-Control privado/no-store; las rutas noSession no generan tokens. Los formularios personalizados necesitan adoptar el helper y excluirse de cachés externas.

Dependencias: excepción explícita de secuencia autorizada para implementar el ciclo completo en esta petición; la etapa anterior debe tener sus verificaciones locales registradas antes de avanzar. La auditoría separada y los perfiles no disponibles siguen pendientes, sin declarar etapas cerradas.
