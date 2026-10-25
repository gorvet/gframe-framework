# Etapa 06 — Protección de escritura

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; la [auditoría de etapa 05](../05-writing-tools/AUDIT.md) y [AUDIT.md](AUDIT.md) de esta etapa ya tienen veredicto coincide. No queda activa la excepción de secuencia descrita al preparar la implementación.
**Spec:** [SPEC.md](../SPEC.md), protección de escritura.
**Baseline:** 2026-10-15. El usuario prioriza protección ante pérdidas y autoriza implementarla, junto con la continuación de UX propuesta. Decisiones técnicas descritas abajo, sin atribuirlas como opciones elegidas por el usuario.

## Resultado observable

Advertir al abandonar cambios sin confirmar y ofrecer recuperación explícita de borradores, con concentración y navegación por títulos accesibles desde el editor.

## Dependencias

- Etapa 05: cerrada con [auditoría coincide](../05-writing-tools/AUDIT.md). La excepción original permitía preparar esta etapa sin cambiar su código hasta terminar aquella auditoría.

## Entra

- Aviso nativo beforeunload por comparación de HTML con la última versión confirmada, independiente de dirty/triggerSave. Desactivable por campo.
- Copias locales optativas con draft_key explícita por aplicación, usuario/ámbito, documento y campo. localStorage con identidad independiente por instancia/pestaña; caducidad de siete días y límite de tamaño. No inferir identidad del usuario ni documento de una URL.
- Recuperar manualmente con Deshacer, o descartar una copia con confirmación. No reemplazar automáticamente el documento, incluso si cambia el servidor. Identificar fallos de almacenamiento sin impedir escribir.
- API de snapshot y confirmación de guardado: confirmar solo tras éxito real; conservar ediciones posteriores a la petición y rechazar respuestas de instancias antiguas.
- Modo concentración nativo de TinyMCE y menú nativo del esquema por títulos, sin guardar IDs nuevos en contenido.
- Documentación, skills, pruebas reales de navegador con recarga, pestañas y fallo inyectado, y revisión visual.

## No entra

Backend de artículos, autosave del servidor, historial/revisiones colaborativas, permisos nuevos, sitios instalados, persistir otros campos del formulario ni garantizar recuperación si el navegador borra o bloquea almacenamiento. Mover secciones y mejoras adicionales de enlaces quedan para una etapa posterior.

## Criterios de aceptación

- **CA-01 — condición de salida:** editar → salida/recarga pide confirmación; cancelar conserva contenido; saveAll/submit fallido no cuentan como guardado; sin cambios o tras confirmar snapshot actual no avisa; evidencia sistema.
- **CA-02 — condición de salida:** draft_key configurada y edición → copia local tras pausa y al abandonar; recarga ofrece recuperación explícita, conserva versión del servidor hasta aceptarla y permite deshacer. Fallo de storage y límite no bloquean edición y se comunican. Evidencia sistema.
- **CA-03 — invariante acumulado:** varias identidades/documentos/campos/pestañas → copias separadas; descarte confirmado y guardado no borran copias ajenas; caducidad y carga inválida no ejecutan HTML; evidencia sistema y conductual.
- **CA-04 — invariante acumulado:** confirmar snapshot capturado antes del envío → limpia solo cambios enviados; texto escrito después sigue pendiente y recuperable; respuesta de instancia destruida no confirma la nueva; listeners/timers se liberan al destruir. Evidencia sistema.
- **CA-05 — condición de salida:** concentración y esquema → entrar/salir y navegar a títulos con teclado sin cambiar HTML; conservar scroll, medios, callbacks y documentación. Evidencia sistema y estructural.

## Riesgos y compatibilidad

El navegador decide cuándo muestra beforeunload y su texto; requiere interacción y no cubre todos los cierres forzados. Copias locales contienen HTML sin cifrar: activación explícita con identidad opaca, limpieza en equipos compartidos y sanitización en backend al guardar. Las escrituras al servidor siguen siendo responsabilidad de la aplicación. La confirmación no debe inferirse de un evento submit ni de setDirty(false).

## Preguntas abiertas

Ninguna para el contrato reusable. Integrar claves y confirmaciones en una aplicación concreta se hará al actualizarla.
