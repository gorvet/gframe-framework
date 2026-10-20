# Mejora de la ayuda: estructura, enlaces e ilustración

Alcance acordado el 20 de octubre de 2026: reorganizar la ayuda por tareas y temas, corregir navegación/enlaces, revisar SEO, ilustrar componentes en su estado útil y sustituir capturas defectuosas. Conservar las rutas existentes, el contenido ya publicado y el inventario. No ejecutar suites generales por cambios documentales ni modificar el core de aplicaciones instaladas.

## Orden de trabajo

1. **Estructura y cobertura.** Separar arquitectura, datos, seguridad, cuentas, contenido, comunicación, procesos, integraciones y SEO. Usar el H1 documental como etiqueta del índice y del listado de módulos. Incorporar las guías públicas existentes que faltan en el catálogo; distinguir documentos históricos e internos.
2. **Enlaces y SEO.** Resolver dentro del sitio los enlaces a guías disponibles, incluso si vienen como URL de GitHub. Revisar fragmentos, títulos, descripciones, canonical, metadatos sociales, sitemap y navegación móvil. Mantener GitHub para código fuente y documentos de mantenimiento, identificándolo como tal.
3. **Capturas.** Retirar el encabezado repetitivo. Dar margen real y suficiente a las capturas. Mostrar GFSelect abierto y ambos tipos de feedback; verificar autenticación tanto en su guía central como en el módulo UI. Mantener el caso Estudio Nexo, sin el fondo de Base Confías. Registrar sustituciones y pendientes por archivo.
4. **Lectura pública.** La preview existente carga Bootstrap y los estilos generales, pero el ejemplo carece de un tratamiento editorial de anchura, jerarquía y ritmo. Preparar una plantilla de ejemplo con estilos de lectura propios; evaluar aparte si ese tratamiento merece una solución reutilizable del framework. No aplicar cambios globales de H1–H6 a todo el core sin revisar su impacto en las aplicaciones.
5. **Revisión final focalizada.** Una revisión del catálogo/enlaces y del renderizado de las páginas modificadas, más inspección visual agrupada en escritorio y móvil. Documentar lo que queda pendiente. Sin repetir las suites de PHP/JS del framework ni actualizar Composer.

## Inventario inicial contrastado

- Guías públicas presentes pero no catalogadas: API entrante, autenticación PHP, dependencias frontend, cifrado, helpers, cliente HTTP, perfiles de instalación e integración de texto/multimedia.
- También faltan guías de migración/versiones históricas. Deben quedar separadas de las instrucciones actuales.
- `hallazgos-integracion.md`, `preparacion-0.9.0.md` y `reconstruccion-documentacion.md` son registros internos/históricos, no ayuda inicial del usuario.
- Las capturas anteriores ilustran 19 guías, pero ese número no significa cobertura completa del framework. `docs/assets/INVENTARIO.md` conserva la matriz de archivos, uso y pendientes; se ampliará con el inventario de guías sin ilustración.

## Criterio de presentación

La página debe priorizar su explicación y su tarea, no repetir una introducción visual idéntica en cada módulo. Las capturas tendrán pie breve y ampliación; los esquemas se identificarán como esquemas. En el sidebar se abrirá la temática actual y las demás podrán desplegarse, conservando enlaces reales y controles nativos de teclado. El índice de módulos utilizará las mismas temáticas y nombres que sus páginas.

El margen solicitado pertenece al encuadre del PNG original, no al contenedor de la ayuda. Se vuelve a capturar la interfaz completa con espacio alrededor. El orden dentro de cada temática también es explícito: conceptos y requisitos antes de integración, operación y ampliación; las bibliotecas de referencia mantienen orden alfabético cuando no existe una dependencia de aprendizaje.

## Avance: descubrimiento y profundidad

Prioridad posterior del usuario: terminar los puntos 2 y 3 (revisión de las áreas pendientes e ilustraciones) antes de publicar el paquete. El cierre de ese lote se registra en [CAPACIDADES.md](CAPACIDADES.md); no implica crear un tag, actualizar Composer o desplegar producción.

El catálogo incorpora las ocho guías públicas omitidas, separa las temáticas y utiliza los títulos documentales. El orden explícito de las guías se aplica tanto al sidebar como al índice de módulos. El informe `docs/COBERTURA-AYUDA.md` del sitio registra páginas y enlaces; [MATRIZ-CAPACIDADES.md](CAPACIDADES.md) identifica funciones que deben explicarse o ilustrarse mejor, más allá del editor.

Se sustituyeron los encuadres indicados en [el inventario visual](../../docs/assets/INVENTARIO.md), se abrió GFSelect, se añadió el toast y se incluyó el acceso en la guía central de autenticación. La preview utiliza una plantilla de lectura del ejemplo; no se modificaron estilos globales del core.

La revisión focalizada del catálogo no detecta rutas documentales ni fragmentos rotos. Los tres enlaces a Markdown de mantenimiento se identifican como notas internas de GitHub. Se revisó el HTML real de autenticación, GFSelect e índice de módulos en navegador, con una vista móvil de autenticación: H1 único, canonical presente y sin desbordamiento horizontal en esa vista. Las hojas de captura permitieron revisar las imágenes sustituidas; se corrigieron específicamente el contenido del iframe y el desplegable completo. No se ejecutaron suites generales ni Composer.

El lote posterior completa la revisión documental y el apoyo visual de las siete áreas seleccionadas, según CAPACIDADES.md. Permanecen como ampliación la revisión móvil de cada componente, los estados visuales adicionales del inventario y la disponibilidad de enlaces externos. No equivale a una auditoría funcional exhaustiva del framework ni a la auditoría de seguridad aplazada. La publicación permanece pendiente por instrucción del usuario.
