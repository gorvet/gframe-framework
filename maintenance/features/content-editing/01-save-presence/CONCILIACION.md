# Etapa 01 — Guardado por revisión y presencia

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md), R-01–R-06.

## Resultado observable

Una edición antigua se rechaza sin perder texto y el formulario puede advertir de otras sesiones activas, que desaparecen tras desconectarse.

## Dependencias

- Ninguna nueva. Se preservan ORM, auth/tenant y recuperación/autoguardado ya disponibles.

## Entra

- Modelo reusable por herencia, actualización condicional PK/revisión/ámbito mediante ORM y nueva revisión opaca solo al ganar.
- Módulo optativo content-editing con esquema/migración MySQL/SQLite y presencia temporal por sesión; no crea tablas de negocio.
- Controlador auxiliar delegado después de autorizar el registro; identidad y tenant de sesión normalizada.
- Componente PHP/meta/JS pequeño para aviso accesible, tokens, una petición activa, pausa al ocultarse y destrucción segura.
- Documentación de migración optativa de revision y de adopción por todos los caminos de escritura, conflictos y snapshots.
- Pruebas de procesos, aislamiento, caducidad y frontend; docs y skills actualizadas.

## No entra

Bloqueo exclusivo, edición colaborativa, fusión automática, historial ni otras prioridades de la hoja de ruta; aplicaciones reales, releases o migraciones automáticas de tablas desconocidas.

## Aclaraciones

- 2026-10-19: el usuario autoriza continuar con definición e implementación de la prioridad 1 y exige encaje con la filosofía y skills del framework. Se conserva la autorización por contenido/tenant de la aplicación.
- Las decisiones de diseño del SPEC son técnicas; no se atribuyen al usuario como preferencias detalladas. La presencia es aviso temporal conforme a la hoja de ruta, no permiso para sobrescribir.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** dos procesos envían la misma revisión → exactamente uno actualiza contenido/revisión; el perdedor recibe content_conflict, sin sobrescribir; revisión rota incluso si el contenido coincide. Evidencia sistema.
- **CA-02 — invariante acumulado:** PK o ámbito ajenos, revisión incorrecta o modelo con conexión nombrada → no se amplía la escritura ni cambia conexión; actualización y revisión pertenecen a una sola sentencia y respetan transacción de la aplicación. Evidencia sistema y conductual.
- **CA-03 — invariante acumulado:** dos sesiones de edición del mismo recurso → se avisan; otro tenant/recurso no se cuenta; al caducar deja de contarse y un nuevo ping recupera presencia sin bloquear guardado. Evidencia sistema y conductual.
- **CA-04 — invariante acumulado:** petición sin identidad o malformada → rechazo; el recurso lo decide el servidor tras auth/can/CSRF y las identidades no se toman de POST; fallos no exponen SQL. Evidencia sistema y estructural.
- **CA-05 — condición de salida:** componente montado → envía tokens y una petición cada vez; oculto/destruido no inicia nuevas; respuestas antiguas no alteran nueva instancia; conflicto no confirma guardado ni reemplaza contenido. Evidencia conductual y sistema.
- **CA-06 — condición de salida:** instalar/actualizar módulo optativo y consultar recetas → esquemas repetibles, assets/meta correctos y contrato descrito con ejemplos ejecutables; regresiones relevantes pasan. Evidencia sistema y estructural.

## Riesgos y compatibilidad

Sin adoptar el guardado versionado en todos los escritores no hay garantía global. La presencia depende de red y relojes de servidor sincronizados; no concede exclusividad. La aplicación es dueña de transacciones y autorización, incluidas relaciones del contenido; un efecto externo no se revierte por rechazar una revisión.

## Bloqueos

Ninguno para implementar. MySQL no disponible se registra como no verificado, sin inferirlo desde SQLite.

## Preguntas abiertas

Ninguna de producto para este alcance reusable.

## Criterio para cerrar conciliación

Alcance de prioridad 1 autorizado; criterios y contratos técnicos explicitados antes de implementar. No se modifica el alcance cerrado de autoguardado privado.
