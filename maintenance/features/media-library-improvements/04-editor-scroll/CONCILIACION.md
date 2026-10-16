# Etapa 04 — Escritura con desplazamiento propio

**Estado:** cerrada
**Baseline:** 2026-10-15; el usuario pide mejorar la UX de TinyMCE, concretamente evitar que un artículo largo expanda el formulario e impida acceder al aside.

## Resultado observable

Un artículo largo se desplaza dentro del editor y no aumenta continuamente la altura del formulario.

## Dependencias

Etapa 03 cerrada; se conservan biblioteca, guardado y personalización.

## Entra

Altura inicial acotada usando la configuración existente, scroll del contenido, aislamiento del desplazamiento en los extremos, barra de herramientas accesible y redimensionado manual. Conservar opciones por instancia para proyectos que elijan autoresize expresamente.

## No entra

Modificar asides o layouts de aplicaciones instaladas, cambiar editor, modo de página completa, nuevas opciones de publicación, edición visual de imágenes o metadatos.

## Criterios de aceptación

- **CA-01 — condición de salida:** documento largo → altura inicial del editor estable, contenido desplazable y aside alcanzable; rueda y teclado funcionan, incluso al final sin arrastrar el documento exterior. Evidencia sistema.
- **CA-02 — invariante acumulado:** cambio de altura explícito, editor corto, personalización y biblioteca → ciclos de edición/guardado y opciones por instancia conservados; escritorio y móvil sin overflow horizontal; documentación y skills actualizadas. Evidencia sistema y estructural.

## Riesgos y compatibilidad

Se retira autoresize de los valores base; un proyecto que lo registre expresamente conserva esa elección. El editor mantiene el mínimo ya configurado, con tamaño inicial igual; no controla la altura del aside ni el scroll del shell del proyecto.

## Preguntas abiertas

Ninguna para este ajuste concreto. Otras mejoras de escritura se decidirán después.
