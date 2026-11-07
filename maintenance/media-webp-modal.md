# WebP y modales de medios

Solicitud: generar una copia WebP para nuevas imágenes, preferirla al insertar contenido y corregir los detalles y la estabilidad de los modales. Solo fuentes del framework; no actualizar aplicaciones instaladas ni publicar una versión.

Ampliación autorizada el 7 de noviembre de 2026: commit y push del framework, etiqueta Composer y actualización del sitio documental. Después el usuario solicitó regeneración o un botón para las imágenes antiguas: se añade Optimizar en ambos paneles de detalles, junto a los datos de peso, conservando original/ID/atributos/miniaturas y con avisos existentes. Preflight del ajuste PASS: acción secundaria contextual, solo disponible para imágenes elegibles, bloqueada durante operaciones y ante ediciones pendientes. Se añade `skills/gframe-orm-models` para la escritura condicional. No se ha identificado ni autorizado una aplicación de producción para ejecutar conversiones. Cuenta/seguridad, compatibilidad de iconos y sangría del menú quedan registrados en mejoras-pendientes.md para otra entrega.

Skills canónicas: `skills/gframe-orchestrator`, `gframe-media-module`, `gframe-framework-maintenance`, `gframe-backend`, `gframe-frontend-admin`, `gframe-ui-design-clean`. GORVET: pipeline, estructura, implementación, QA visual y auditoría.

Contexto → diseño: selección de archivos como tarea principal. De las referencias WordPress se trasladan la superficie estable, la vista previa contenida y los metadatos etiquetados y discretos; no sus extensiones ni sus campos ajenos al modelo de GFrame.

Diseño → construcción, preflight PASS: conservar biblioteca/filtros a la izquierda y atributos a la derecha en escritorio. Vista previa de altura reservada; nombre, tipo, peso y dimensiones en una lista de definiciones. Altura del modal independiente de la imagen, cuerpo desplazable y acciones del footer siempre accesibles. En móvil, regiones apiladas con scroll interior. Mantener Bootstrap, sus variables de tema y controles; sin tarjetas o efectos nuevos. Confirmaciones y errores mediante alertToast; estado de carga/cambios pendientes accesible sin alterar la altura. El modal de administración conserva la navegación y separa una vista previa amplia de los atributos.

Aceptación: original intacto, WebP sin ampliar y solo si reduce peso, fallback sin GD/WebP y GIF intactos; URL preferida y URL original diferenciadas, variantes privadas con MIME correcto y permisos conservados. Campos/selector/editor reciben la URL preferida. Cambiar entre imágenes y carga no mueve el diálogo; sin bytes crudos ni párrafo gigante de metadatos; sin confirmación de guardado incrustada. Conservar guardas de cambios pendientes, selección múltiple, orden y respuestas tardías.

Aclaración del usuario durante la implementación: ambos diálogos deben aprovechar casi toda la pantalla como WordPress; al abrir el selector con preselección se consultan automáticamente los detalles de la última imagen seleccionada. Ajuste previo a construcción de esta ampliación: margen exterior de 1 rem sin límite máximo de ancho; sidebar del selector acotado para priorizar resultados. En móvil, pantalla completa. Se mantienen el resto de las decisiones, preflight PASS.

Comprobaciones previstas: PHPUnit de medios/privados/runtime/escritura/documentación; navegador de selector, administración, campos y escritura, con escritorio/móvil/oscuro; lint PHP/JS, diff y validación de skills afectadas. Las pruebas usan proyectos temporales.

## Verificación

Ampliación final con Optimizar: publicación comprobada con 642 pruebas PHP, 6345 aserciones, ocho omisiones de infraestructura opcional y ningún error/fallo; MySQL de pruebas real incluido. JavaScript: 101/102 en la pasada conjunta y el recorrido cancelado por tiempo pasó aislado en 106 s; ningún caso omitido. PHPStan nivel 5, lint de 541 PHP y skills correctos. El botón se verificó en biblioteca y selector con registros antiguos: mantiene ID, original y miniaturas; deja de mostrarse tras generar la variante. Cuota, ámbito, privados, repetición y conflictos cubiertos. Detalles de ejecución y distribución en releases/5.1.0.md.

- PHPUnit: `php packages/phpunit/phpunit/phpunit --filter 'Media|PrivateDownload|HtmlSanitizer|DocumentationLinks' --display-skipped`: 60 pruebas, 668 aserciones, 59 aprobadas y una omitida por ausencia de `GFRAME_TEST_MYSQL_DSN`. Sin cambios de esquema.
- Navegador Chrome/Playwright: ocho pruebas distintas aprobadas entre selector, nuevos tamaños/temas, gestión, escritura, controles de campo, normalización de IDs, montajes simultáneos y avisos. La regresión inicial de gestión esperaba el mensaje incrustado; se actualizó para comprobar el aviso y pasó. Última repetición de selector + layout: 2/2, sin omisiones.
- Lint de los nueve PHP modificados y sintaxis de ambos JS: correctos. `php -n` confirma generación vacía sin GD. Configuración de formato/calidad inválida, transparencia, reducción proporcional, copia sin ahorro descartada, GIF, ausencia de WebP, colisiones de nombres, cuota y eliminación cubiertos por PHPUnit.
- `php bin/validate-skills.php`: 14 skills y 80 enlaces locales correctos. `git diff --check`: correcto. Auditoría editorial de la guía: cero errores críticos; avisos de indentación, código y contenido existente revisados sin alterar contratos.

## QA de interfaz

VERDICT: PASS para los componentes nativos en el fixture temporal.

SCOPE / EVIDENCE: capturas e interacción reales de selector y detalles a 1920×1080, 1440×1000, 1280×720, 768×1024 y 390×844; temas claro y oscuro, horizontal/vertical, nombres largos, preselección, carga retardada, guardado, cambios pendientes, navegación y cierre/reapertura.

COMPOSITION: resultados como foco principal y atributos en sidebar acotado; metadatos pequeños, separados de los campos editables. La vista previa del modal de detalles ocupa su columna y altura disponible. Sin nuevas tarjetas, métricas o efectos decorativos. Se trasladan el uso del viewport y la jerarquía de WordPress, manteniendo Bootstrap y los tokens del framework.

CONFORMANCE: margen exterior de 1 rem y pantalla completa móvil, altura estable antes/durante/después de cambiar proporciones, desplazamiento interior y footer visible. Preselección con atributos automáticos, pesos separados, avisos externos al formulario y progreso ubicado en la pestaña de subida. Selección/teclado/guardas y temas conservados.

FINDINGS / CORRECTIONS: se corrigió el ancho desigual causado por nombres largos mediante `min-width: 0`; la prueba verifica anchuras uniformes y se revisó la captura regenerada. La pestaña de subida conserva su desplazamiento para lotes largos. La carga no añade ni retira el formulario ya visible ni desplaza su contenido.

LIMITS / DEFERRALS: fixture con Bootstrap y fuentes nativas, no el shell personalizado de una aplicación instalada; iconos globales ausentes en el fixture. Sin certificación de lector de pantalla ni contraste instrumental. No se actualizó ningún sitio, no se publicó versión y no se regeneraron archivos antiguos ni URLs ya guardadas. Las capturas locales ignoradas están en `.playwright-mcp/media-modal-*` y `media-selector-*`; son reproducibles con `GFRAME_TEST_SCREENSHOTS`.
