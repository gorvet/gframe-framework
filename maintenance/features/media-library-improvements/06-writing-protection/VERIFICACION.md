# Verificación de implementación

2026-10-15. Implementación terminada, preparada para auditoría separada. HEAD 4f901dc con cambios previos conservados. PHP 8.3.35, Node 24 y Chrome en Windows.

| Tarea | Evidencia de implementación |
|---|---|
| T1 | beforeunload real: cancelar conserva texto; aceptar recarga; saveAll/submit prevenido no reconocen persistencia; snapshot confirmado actual no avisa; opción false desactiva el aviso |
| T2 | localStorage real tras pausa/recarga; recuperar conserva servidor hasta aceptación y permite undo/redo; swalAlert confirma descarte; cancelar/falta de helper conservan la copia; caducidad, JSON inválido y HTML hostil; cuota y tamaño se verifican por separado |
| T3 | snapshot inmutable; escritura posterior al envío sigue pendiente; respuesta de instancia antigua/objeto inventado/ack anterior rechazados; destroy conserva el cambio anterior a la pausa y no escribe después; identidades, documentos, campos y pestañas aislados |
| T4 | esquema y fullscreen con teclado, HTML sin IDs nuevos, pantalla móvil sin overflow, fullscreen vuelve tras biblioteca; guías/skills y publicación/overrides probados |

Comandos: `node --test tests/js/rich-text-protection.browser.test.cjs tests/js/rich-text-documentation.test.cjs`: última pasada 3/3, exit 0, 49,571 s. NODE_PATH del runtime de Codex, GFRAME_TEST_BROWSER a Chrome y GFRAME_TEST_SCREENSHOTS a .playwright-mcp. Ejecución de regresión anterior de cuatro archivos: 5/5, 64,940 s; la auditoría reejecutará el conjunto contra la versión final.

PHP: `php packages/phpunit/phpunit/phpunit --filter 'RichTextEditor|DocumentationLinks|MediaDocumentation'`: 13 tests/198 aserciones, exit 0, 5,289 s. `php bin/validate-skills.php`: 14 skills y 79 enlaces. node --check, lint de las vistas y diff --check pasan. Auditoría editorial: cero errores críticos; advertencias revisadas corresponden a indentación/código PHP/JS y al uso técnico de «clave». Sin cambio de esquema: no requiere migración de base de datos.

Sensibilidad y correcciones: al editar una copia recuperada antes de enviarla, el test descubrió que se volvía a ofrecer la copia original después de guardar (fallo reproducido, true !== false en panel tras recarga). Se asocia la copia recuperada al snapshot que la consume y se elimina por coincidencia exacta de su versión local, conservando las copias posteriores/ajenas. Reejecución verde. El envío durante escritura y las fallas de storage se ejercitan con negativos reales. La cuota inyectada se restaura antes de probar el límite de 1 MiB, para no confundir ambas ramas.

Se corrigió el fixture para reservar la cabecera del panel antes del editor sticky, se comparan entidades HTML adecuadamente y se limita la espera de una recarga cancelada. Son cambios del test, no relajaciones de la protección. Descarte usa el helper canónico; meta opcional de recuperación para plantillas propias. El modo fullscreen sale temporalmente al abrir Bootstrap y vuelve después; fullscreen_native requiere acción posterior del usuario por política del navegador.

Capturas: rich-text-recovery.png, rich-text-recovery-mobile.png, rich-text-recovery-conflict.png, rich-text-storage-failure.png y rich-text-protection-mobile.png en .playwright-mcp (ignoradas). Revisadas recuperación de escritorio y layout móvil. La auditoría añade captura de confirmación.

Límites: transporte HTTP interceptado; TinyMCE, Bootstrap, SweetAlert/alerts, PHP y almacenamiento del navegador reales. No se verifican servidores HTTP vivos, producción, cierre forzado del navegador ni todos los motores. Clave de identidad y confirmación de éxito deben integrarse en cada aplicación; no se modifican sitios instalados. Copias del campo, sin guardado remoto automático. No hay commit ni etiqueta. Este documento no emite veredicto de auditoría.
