# Auditoría de plantillas de correo

## Pasada 1 — 2026-10-01

**Alcance:** petición explícita de comprobar homogeneidad. Revisión intermedia del árbol de trabajo; no es el cierre de una etapa. No existe una CONCILIACION.md cerrada ni TAREAS.md principal para esta auditoría. No se declara `coincide` ni se inventan criterios aprobados retrospectivamente.

**Plantillas:** `resources/skeleton/app/views/templates/mail/mailTemplate.html`, `contactTemplate.html` y `resources/modules/notifications-email/application/mail/notification.html`. Registro, reenvío y recuperación comparten `mailTemplate`; no son tres archivos diferentes. Contacto tiene una finalidad interna distinta y muestra remitente, asunto y mensaje.

**Resultado:** no son completamente homogéneas. No se modificaron plantillas ni servicios durante esta pasada.

**Snapshot:** HEAD `94fd7ee`, con cambios locales existentes. Se registran los SHA-256 de las plantillas auditadas.

- `mailTemplate.html`: `210281BA5FA65B0D8BEDF99A5F368BAA17FB0FF97967A9C190F7C01D837DC042`.
- `contactTemplate.html`: `ABE451343289C15205954C54BB2FD6EE7FE8D34857C4C26D668830FEA54387EA`.
- `notification.html`: `B939FBBF6217D75BAF10200541F8E933275F5195EF18FE9639749B34618A631A`.

## Hallazgos

| ID | Hallazgo | Evidencia | Trabajo propuesto, no implementado |
|---|---|---|---|
| H-01 | Notificaciones usa colores y Arial fijos; acceso/contacto usan MailThemeHelper. En el render aislado, fondos `#f4f6f8` frente a `#f6f6f6`, texto `#172033` frente a `#6c757d`, títulos 24 frente a 28 px y cuerpo 16 frente a 18 px. | E-01, E-02 | Usar las mismas variables y estilos de correo, conservando el contenido específico de cada operación. |
| H-02 | Notificaciones muestra un enlace sin estilo de botón. Acceso usa CTA con fondo, texto blanco y posición centrada. | E-01, E-02 | Aplicar el mismo estilo de CTA cuando haya una acción. Contacto no necesita un botón artificial. |
| H-03 | Notificaciones colapsa un mensaje de dos líneas en una sola. Contacto conserva los saltos mediante `white-space:pre-wrap`. | E-02 | Conservar saltos y envoltura de líneas también en Notificaciones. |
| H-04 | Los tres pies son iguales en contenido base, pero no reutilizan las áreas PHP de copyright/créditos personalizadas del proyecto y no incluyen el año. Notificaciones además fija otro color del pie. | E-01 | Acordar y aplicar una fuente compartida de contenido del pie; no ejecutar arbitrariamente templates web en el motor de correo. |
| H-05 | Acceso/contacto limitan el logo con CSS del head; Notificaciones declara altura inline y atributo HTML. No es el mismo nivel de resistencia ante clientes que ignoren estilos del head. | E-01 | Declarar dimensiones y límites inline coherentes en los tres. No se ha demostrado un fallo concreto en Outlook. |
| H-06 | El JSON de Notificaciones aún declara `app_name`, que ya no aparece en su HTML. | E-01 | Ajustar metadatos/documentación al contrato actual. |

## Comprobaciones satisfactorias

- Las tres utilizan `mailLogo` y `mailSiteName`, con texto alternativo para el logo y pie fuera del card.
- El motor común escapa las variables de contenido. Las pruebas de acceso y transporte comprueban contratos y enlaces; no se enviaron correos durante esta auditoría.
- En la prueba local se cargaron los tres logos con altura aproximada de 55 px.
- A 390 px de viewport, cada iframe tuvo 358 px de ancho y 358 px de scrollWidth: sin desbordamiento horizontal con la muestra. El pie existía en los tres documentos.
- El saludo personalizado de acceso no duplica el enlace del botón. No se exige saludo ni título decorativo en un correo interno de contacto.

## Ledger de evidencia

| ID | Procedimiento reproducible | Esperado | Observado | Entorno | Limitación |
|---|---|---|---|---|---|
| E-01 | Inspeccionar las tres plantillas, `notification.json`, `MailThemeHelper`, `MailTemplateRegistry` y `AuthController::sendAccessMail`. | Mismos recursos de marca, estilos comunes y contratos coherentes. | Recursos de marca compartidos; H-01, H-02, H-04, H-05 y H-06 presentes. | Árbol de trabajo local, Windows, 2026-10-01. | Inspección estructural, no compatibilidad de clientes. |
| E-02 | `php -S 127.0.0.1:8767 -t . tests/fixtures/mail-templates-preview.php`; abrir el fixture y consultar estilos calculados en cada iframe. | Misma base visual; mensaje de dos líneas conservado. | Diferencias de tipografía/color/CTA; Notificaciones colapsa las dos líneas. | PHP 8.1.5 y navegador IAB, 2026-10-01. | El fixture no carga las variables del demo: usa el fallback del helper y datos de prueba, iguales para las tres. |
| E-03 | Con el mismo fixture, viewport 390×700; comparar clientWidth/scrollWidth y comprobar imagen cargada y texto del pie. | Sin scroll horizontal; logo y pie presentes. | Tres documentos con ancho y scrollWidth 358; logos cargados y pies presentes. | Navegador IAB, 2026-10-01. | Una muestra breve; no cubre enlaces o palabras extremadamente largos. |
| E-04 | `php packages/phpunit/phpunit/phpunit --filter 'MailTemplatesTest|NotificationEmailTest|AuthUiTest'`. | Pruebas de render, escape y contratos verdes. | Exit 0; 20 pruebas, 143 aserciones. | PHP 8.1.5, PHPUnit 10.5.65, 2026-10-01. | Las pruebas verdes no contradicen los fallos de homogeneidad observados. |

## Límites y pendientes

- No se audita el SMTP, el cron ni la causa del correo inicial de registro en esta pasada. La investigación del registro sigue pendiente.
- No se verificaron Outlook, Gmail ni sus políticas de imágenes externas. Un logo con URL `localhost` no es accesible desde un cliente remoto.
- No se corrigieron hallazgos durante la auditoría. El aviso administrativo aprobado continúa pendiente de implementación y separado de esta revisión.
- Sin baseline de etapa aprobado no procede emitir un delta formal contra TAREAS.md ni cerrar la etapa. Los H-01…H-06 son el listado reproducible de trabajo propuesto.

## Pasada 2 — 2026-10-01

**Origen:** el usuario pidió homogeneizar utilizando el mecanismo existente de adaptación a las variables CSS. Se implementó fuera de la pasada 1 y se volvió a comprobar el resultado. Se conserva la evidencia anterior.

**Cambios autorizados:** copia física de `mailTemplate.html` a `notification.html` y cambios puntuales de contenido para sus contratos actuales. Dimensiones inline iguales del logo en las tres, saltos de línea en Notificaciones y retirada de `app_name` obsoleto del JSON. No se modificó el core ni se creó otro motor de personalización.

| Hallazgo original | Resultado de la nueva comprobación | Evidencia |
|---|---|---|
| H-01 | Corregido. Las tres coinciden en fuente, fondo y texto; cuerpo 18 px y títulos 28 px cuando hay título. | E-05, E-06 |
| H-02 | Corregido. Acceso y Notificaciones usan el mismo estilo de botón; contacto conserva su ausencia de CTA. | E-05, E-06 |
| H-03 | Corregido. Las dos líneas del mensaje permanecen separadas. | E-05, E-06 |
| H-04 | Color del pie homogeneizado. Sigue pendiente la reutilización del contenido de copyright/créditos personalizado y el año; el pie base permanece igual en las tres. | E-05, E-06 |
| H-05 | Dimensiones inline homogeneizadas. No equivale a demostrar compatibilidad en Outlook. | E-05, E-06 |
| H-06 | Corregido. El JSON ya no declara `app_name`. | Inspección del JSON actualizado |

### Evidencia nueva

| ID | Procedimiento | Observado | Entorno y límites |
|---|---|---|---|
| E-05 | `php packages/phpunit/phpunit/phpunit --filter 'MailTemplatesTest|NotificationEmailTest'`. | Exit 0; 12 pruebas, 82 aserciones. Se prueban estilos comunes con valores personalizados, escape, ocultación de CTA, dimensiones del logo y preservación de líneas. | PHP 8.1.5, PHPUnit 10.5.65; 2026-10-01. No envía correos reales. |
| E-06 | Fixture original renderizado; consulta de estilos calculados en los tres iframes y revisión a 390×700. | Tres fuentes iguales, fondo `rgb(246,246,246)`, texto `rgb(108,117,125)`, cuerpo 18 px; títulos 28 px y botones `rgb(60,106,243)` en acceso/Notificaciones. Logos de 55 px cargados; sin desbordamiento móvil; saltos conservados. | Navegador IAB, 2026-10-01. No prueba clientes de correo ni variables específicas del demo. |

**Conclusión:** estilos base homogéneos mediante MailThemeHelper y MailTemplateRegistry. No se declara un cierre formal de etapa ni compatibilidad universal de correo. Continúan pendientes el contenido personalizado del pie, la investigación del registro y la implementación del aviso administrativo.

## Pasada 3 — 2026-10-01: publicación y contenido real

La comprobación del proyecto instalado detectó un fallo no cubierto por la pasada anterior: `ProjectUpdateService` no incluía el directorio de correo del esqueleto. El demo tenía la plantilla nueva de Notificaciones, pero acceso y contacto seguían usando los archivos antiguos. Corregir únicamente las fuentes no trasladaba el saludo ni el pie al ejecutar el update.

Se añadió el directorio completo de correo al actualizador, conservando sus reglas de vista previa y protección de personalizaciones. No se modificaron archivos del demo. Una prueba sobre un proyecto temporal reemplaza la plantilla antigua y renderiza desde los archivos publicados, verificando saludo y pie en acceso, contacto y Notificaciones. También verifica que `--preserve-custom` conserve un archivo modificado y lo informe como conflicto.

Se comprobaron las variables capturadas de las closures de registro, reenvío y recuperación del controlador de autenticación: asunto específico con nombre del sitio, título, saludo personalizado, explicación de la operación, un botón con su enlace y nota de seguridad. Las tres se renderizan en pruebas sin marcadores pendientes ni exposición del token en la respuesta HTTP. El nombre base procede de la parte local del correo; un perfil personalizado puede proporcionar el nombre real.

Contacto organiza asunto como título, remitente y mensaje, sin repetir el asunto ni añadir un saludo o botón artificial. Todas las plantillas incluyen ahora el año del envío mediante el helper común.

En campañas, el asunto y título proceden del título de la campaña; el mensaje y enlace se resuelven por destinatario antes de encolarse. El transporte de correo conserva el texto redactado y no introduce un saludo duplicado: la personalización del mensaje utiliza `{{user_name}}`. Las reglas automáticas existentes incluyen ese marcador. Se corrigió el aviso inicial de desactivación para incluir nombre, saludo separado y fecha de eliminación sin repetir el título en el primer párrafo. El transporte muestra el botón solo cuando existe un enlace HTTP/HTTPS válido. El contenido de campañas ya guardadas no se reescribe al actualizar.

**Evidencia:** suite `ProjectUpdateServiceTest`, `MailTemplatesTest`, `AuthUiTest` y `NotificationEmailTest`: 25 pruebas, 265 aserciones, sin fallos en PHP 8.1.5. Vista previa del demo con `--dry-run --no-database`: tres archivos pendientes de actualización; no escribe ni consulta la base de datos. No se realizaron nuevos envíos SMTP.

**Pendientes conservados:** créditos personalizados del pie web, aviso administrativo de registro y diagnóstico independiente de entregas iniciales. La homogeneidad visual y la publicación están comprobadas; no se afirma que todas las campañas redactadas por el usuario lleven un saludo ni que se hayan probado clientes de correo externos.

**Regresión general:** suite completa finalizada en PHP 8.1.5: 283 pruebas, 2423 aserciones, sin fallos; una prueba omitida. La prueba específica del ciclo de desactivación comprueba también el saludo y el pie del correo encolado. Validación de skills satisfactoria (9 skills).

## Pasada 4 — 2026-10-01: cobertura de actualización y saludo común

La revisión completa encontró otras exclusiones accidentales del esqueleto: `.htaccess`, `index.php`, `install.php`, `core/Load.php`, `public/css/colores.html` y el JavaScript de navegación del home. Se añadieron a una política compartida `ProjectScaffolder::UPDATE_PATHS`, utilizada por el actualizador y por el registro de huellas inicial del instalador. Antes, el instalador solo registraba huellas de archivos publicados por módulos, no las de archivos base gestionados.

`ProjectUpdateServiceTest` compara cada archivo del esqueleto contra los gestionados y una lista explícita de archivos propios del proyecto. También publica todos los módulos en un proyecto temporal y verifica que cada archivo publicado figure en la vista previa como cubierto por la actualización. Se conservan configuración, rutas, permisos, créditos y marca del proyecto; los archivos runtime siguen dentro del paquete.

Las tres plantillas estándar contienen saludo y pie. `MailService` suministra el nombre del destinatario desde las opciones, el contexto `user_name` o la parte local del correo. El registry completa el saludo si no se suministró; contacto y Notificaciones ocultan el saludo adicional cuando el mensaje ya comienza con uno reconocido. Se comprueban los tres saludos renderizados y la ausencia de duplicación. Esta decisión sustituye la ausencia de saludo en contacto descrita en pasadas anteriores. No modifica campañas guardadas ni reescribe HTML libre enviado con `sendHtml()`.

**Evidencia:** pruebas de actualización, instalación, plantillas, transporte de correo y controlador de autenticación: 40 pruebas, 1004 aserciones, sin fallos, una omitida, PHP 8.1.5. Sintaxis válida en los archivos PHP modificados. Vista previa del demo sin escritura ni conexión a base de datos: tres plantillas pendientes, 582 archivos sin cambios. No se actualizaron demos ni se enviaron correos reales.
