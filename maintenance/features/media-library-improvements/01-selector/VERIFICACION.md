# Verificación de implementación — Selector

Entorno: Windows, PHP 8.3.35, SQLite, Node y Chrome real con Playwright. No se utilizaron aplicaciones instaladas. HEAD anterior 4f901dc; cambios de implementación en árbol de trabajo.

## Ejecuciones

- `php packages/phpunit/phpunit/phpunit --filter 'MediaRuntime|MediaLibrary|MediaDocumentation|MediaScopeResolver|MediaMountIdentifiers|ProjectUpdateService|DocumentationLinks'`: 35 pruebas, 1085 aserciones, salida 0.
- `node --test tests/js/media-picker-workflow.browser.test.cjs tests/js/media-field-controls.browser.test.cjs tests/js/media-multiple-mounts.test.cjs tests/js/media-toast.browser.test.cjs tests/js/media-field.test.cjs`, con NODE_PATH del runtime, GFRAME_TEST_BROWSER apuntando a Chrome y GFRAME_TEST_PHP opcional: 5 pruebas, sin omisiones ni fallos. El test de toast ya estaba pendiente y se conserva independiente.
- Lint de los ocho PHP afectados y node --check de los cuatro JS: correctos. `php bin/validate-skills.php`: 14 skills, 79 enlaces. DocumentationLinksTest adicional: 4 pruebas/121 aserciones. git diff --check sin errores.

## Evidencia por criterio

- CA-01: servidor PHP temporal con controlador/servicio/SQLite originales. Subidas multipart correctas y una inválida; respuestas entregadas en orden inverso mediante transporte Playwright. Selección múltiple ordenada, límite alcanzado, selección simple del último archivo válido y cierre/reapertura durante subida. Desde URL se simula solo el registro externo y se recuperan los detalles desde SQLite real: no se accede a Internet.
- CA-02: nombre visible/alt guardados por backend y devueltos normalizados; inputs y resultado reflejan el dato del servidor. Error 403 inyectado en fixture para verificar UX y ausencia de cambio en SQLite. No se usa esa inyección como prueba del motor de permisos: rutas nativas auth/can y CSRF no cambian. Scopes global/user/tenant verificados por PHP, incluidos IDs ajenos.
- CA-03: bandeja renderizada por PHP, orden [1,2] → [2,1], resultado replaceSelection y retirada aplicada al campo append sin conservar el ID retirado. Pruebas anteriores conservan campos simples/múltiples y miniaturas. IDs/url/is_image compatibles en nuevos resultados.
- CA-04: búsqueda antigua no reemplaza la actual; progreso y resultado por archivo; selección con espacio, orden mediante botones y estados aria-pressed. Guardar bloquea cambios concurrentes en inputs y cierre; cambios pendientes exigen guardar/descartar. Reapertura no hereda callbacks antiguos ni pestaña de subida.
- CA-05: no hay migraciones ni tablas nuevas; rutas y propiedad intactas. Nuevo fragmento selection acotado a lista explícita, máximo 50 y ámbito; campos sthum/gthumb conservados. El modal no añade formularios anidados ni nombres a FormData consumidor y los controles requeridos ocultos quedan deshabilitados. Documentación y skills actualizadas.

## Revisión UI

Se inspeccionaron capturas 1440×1000 y 390×844, generadas con plantillas/CSS reales. Filtros en dos filas en el selector para evitar compresión; panel de atributos lateral/apilado; modal scrollable con cabecera y confirmación visibles; marca de selección además del color; sin CSS ajeno al módulo ni nuevas bibliotecas.

Capturas locales en .playwright-mcp/media-selector-{desktop,mobile}.png (artefactos ignorados). Datos sintéticos: imágenes PNG mínimas, no fotografías reales. Se comprobó DOM, teclado y anchura; no se afirma certificación WCAG ni prueba con lector de pantalla real.

## Sensibilidad y reparaciones durante implementación

La primera prueba real falló al reabrir durante la transición de cierre; después detectó que persistía la pestaña de subida ocultando las tarjetas. Se corrigieron la espera del evento hidden y la activación de biblioteca; las mismas aserciones pasan. Las respuestas de guardado ahora incluyen el dato normalizado; se prueba con etiquetas HTML que el backend elimina. Se evita que campos requeridos de atributos bloqueen el formulario consumidor cerrado.

## Límites

No hay commit, release, despliegue ni validación sobre sitios reales en esta entrega. No incluye arrastre para ordenar, edición de píxeles, leyendas/descripciones nuevas, lista administrativa avanzada ni inserción de galerías en el texto. La ordenación inicial utiliza botones con teclado. La suite completa del repositorio queda para la publicación; se ejecutó el conjunto relevante de medios/actualización/documentación.

## Revisión posterior

AUDIT.md conserva los pases negativos y el cierre del pase 4. Deltas: confirmación de composición vacía con field pendiente; textos nuevos en vistas PHP; sincronización de teclado con shown.bs.modal en el fixture. Ejecución final independiente: 35 pruebas PHP/1085 aserciones y cinco JS/navegador sin omisiones. No se altera el baseline de la conciliación.
