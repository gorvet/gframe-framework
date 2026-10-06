# Comparativa de GitHub, trabajo local e integración

Fecha: 6 de octubre de 2026.

Se compararon los 142 archivos diferentes entre `origin/main` (`5b7d7fe`) y la rama local original (`5cbc233`). El [inventario por archivo](integracion-local-main-inventario.csv) contiene el aporte de cada versión, la evaluación y la decisión final. El [registro de integración](integracion-local-main.md) conserva respaldo, criterios y verificaciones.

La evaluación separa tres situaciones: una corrección que mejora main, una mejora local que aún no estaba en GitHub y un comportamiento o afirmación peor frente a la otra versión. La ausencia de una mejora no demuestra que las ramas de GitHub hayan causado una regresión histórica. Los registros de mantenimiento se valoran como trazabilidad, no como mejoras de ejecución.

## Qué mejoró con GitHub

| Archivos | Mejora conservada |
| --- | --- |
| `src/GFrame/Auth/AuthModel.php`, `tests/AuthVerificationTokenTest.php` | Verificación de caducidad del token antes de activar una cuenta, sin escritura cuando el token expiró. El local anterior no la comprobaba. |
| `src/seo/JsonLD.php`, `tests/JsonLdTest.php`, `tests/SchemaJsonLdRuntimeTest.php` | Conserva valores `0` y `false`, evita inventar precio cero para planes sin precio y omite rating vacío. |
| `src/seo/SchemaComposer.php` | SearchAction solo con búsqueda explícita, resolución recursiva con detección de ciclos y tratamiento correcto del arreglo vacío. |
| `src/seo/Robots.php`, `tests/MetaSeoTest.php` | Robots no anuncia un Sitemap que esté desactivado. Se conserva esta condición al integrar la política local. |
| `config/defaults.php`, `tests/ConfigurationDefaultsTest.php` | Una fuente efectiva para el límite de carga multimedia; el local conservaba una opción global residual. |
| `src/GFrame/Config/LegacyConfigBridge.php`, `resources/skeleton/config/routes/routes_system.php` | Configuración global e interruptores individuales de endpoints coherentes y documentados. No se sustituyeron por los cambios locales que ignoraban esos interruptores. |
| `README.md`, `docs/index.md` | Entrada organizada por tareas, creación de aplicaciones y referencias más completas. El README local todavía destacaba `1.0.0` y reducía el recorrido de instalación. |
| `docs/guia-desarrollo.md`, `docs/tutorial-productos.md`, `docs/primer-proyecto.md` | Aprendizaje desde cero y recorrido completo de una funcionalidad. Se evita mantener dos tutoriales CRUD canónicos. |
| `docs/autoload-proyecto.md`, `docs/modulos-en-aplicacion.md`, `docs/perfiles-instalacion.md`, `docs/identidad-autorizacion.md`, `docs/procesos-segundo-plano.md` | Cobertura transversal que no existía como estas guías en el HEAD local. |
| `docs/helpers-php.md`, `docs/http-client.md`, `docs/encryption.md`, `docs/meta.md`, `docs/vistas.md`, `docs/comandos.md`, `docs/respuestas.md` | Referencia ampliada de capacidades, ubicación, uso, límites y contratos. El inventario detalla cada guía añadida o ampliada. |
| Guías de bibliotecas frontend y módulos opcionales | Se conserva la cobertura de GitHub por paquete, evitando reducirla al inventario local más corto. |
| `src/database/ORM_GUIDE.md` | Rutas reales de `src/database/` y configuración actual; el local todavía presentaba `core/database/` y `config/Config.php`. |

## Qué estaba peor o incompleto frente al local

| Archivos | Hallazgo | Resultado |
| --- | --- | --- |
| `src/async/ClosureWrapper.php`, `composer.json`, `composer.lock` | Main usaba Opis 3.7 y reproducía el aviso de la interfaz Serializable en PHP 8.1; el local lo evitaba cambiando a Laravel. Ese cambio no demostró una ventaja frente a actualizar Opis, y no implica que Opis como librería esté deprecada. | Se descarta la sustitución por Laravel y se actualiza a Opis 4.5.0, con compatibilidad de lectura para tareas de Opis 3.7. Se requiere PHP 8.1.9 o superior por un fallo de WeakMap en PHP antiguo. |
| `src/seo/schema.presets.php` | El preset FAQ de main imponía WebPage y podía reemplazar el tipo al combinarlo con SoftwareApplication, Product o NewsArticle. El local lo dejaba como complemento. El tipo explícito de `saas_landing` de main sí era correcto. | FAQ vuelve a complementar otros tipos; se conserva el tipo explícito de SaaS. |
| `docs/json-ld.md` | La referencia de main era más amplia, pero los ejemplos completos del local eran más fáciles de ejecutar y estaban comprobados. | Se conservan ambas ventajas: referencia de main y seis metas completas en `docs/json-ld-ejemplos.md`. |
| `src/seo/SCHEMA_GUIDE.md` | La nota interna de main afirmaba que los presets no se resolvían recursivamente, contradiciendo su propio código corregido. La versión local explicaba la recursión, aunque incluía rutas antiguas y una búsqueda predeterminada incorrecta. | Conservada la estructura de main y corregidas recursión y búsqueda. |
| `src/routing/Router.php` | Main no contenía el nuevo campo `routeParams.uri` que ya estaba en el local. Es una mejora local pendiente de incorporar, no una regresión demostrada del router de GitHub. | Recuperado y probado el patrón declarado. |
| `src/GFrame/Mail/MailService.php`, `src/GFrame/Mail/MailRateLimiter.php` | Main aún no tenía la protección opcional antispam del local. Su documentación describía correctamente esa ausencia, pero no recuperaba el adelanto local. | Integrado el límite opcional con pruebas y documentación correspondiente. |
| `src/render/Meta.php`, `src/seo/Sitemap.php`, `src/seo/Llms.php` | Main tenía filtros separados; el local unificaba las rutas privadas, de error y protegidas, y distinguía la URL de la carpeta del controlador. | Recuperada la política común, preservando el alcance legacy y los controles individuales de main. |

## Qué mejoró con lo que ya había en local

| Archivos | Aporte recuperado |
| --- | --- |
| `src/routing/Router.php` | Patrón de la ruta disponible para las políticas y el resto de la ejecución. |
| `src/async/Async.php`, `src/async/ClosureWrapper.php`, `tests/AsyncTest.php` | Serialización compatible con la prueba de PHP 8.1, conservación de datos capturados y rechazo de payloads que no contienen una closure. |
| `src/GFrame/Mail/MailService.php`, `src/GFrame/Mail/MailRateLimiter.php`, `tests/MailRateLimiterTest.php` | Ventana móvil, cuotas por ámbito/identidad, bloqueo concurrente, fallos sin consumir cupo y reserva única al encolar. |
| `config/defaults.php`, `resources/skeleton/.env.example`, `src/GFrame/Install/ProjectConfigWriter.php` | Configuración y variables de entorno del límite de correo. |
| `src/GFrame/Seo/SeoPolicy.php`, `src/render/Meta.php`, `src/seo/Sitemap.php`, `src/seo/Llms.php`, `src/seo/Robots.php` | Política común adaptada a los contratos que debían conservarse de main. |
| `resources/modules/auth-ui/application/routes/routes_auth.php` | Noindex explícito en las cinco páginas de acceso y recuperación. |
| Metas de siete módulos/layouts | Eliminación de reglas robots redundantes; se mantiene la política en las rutas y su protección. |
| `src/seo/schema.presets.php` | FAQ no altera el tipo de los presets que complementa. |
| Pruebas documentales de Auth, utilidades, JSON-LD, idiomas, multimedia, middleware, notificaciones, ORM, permisos, Render y sesiones | Ejemplos ejecutables o comprobados frente a las APIs actuales, adaptados cuando main amplió las guías. |
| `docs/servidores-web.md` | Explicación del controlador frontal y pasos de Apache, conservando las comprobaciones y referencias de main. |
| Skills canónicas de mantenimiento | Correcciones de la ubicación del fragmento Nginx y criterios para conservar pendientes y documentar contratos reales. |

## Qué no se recuperó del local

| Archivos o cambio | Motivo |
| --- | --- |
| Sustitución de Opis por Laravel Serializable Closure | Se conserva el proveedor original y se actualiza a Opis 4. El aviso de la versión antigua no justificaba cambiar de librería. |
| Versión local de `AuthModel.php` y `JsonLD.php` | Perdería las correcciones de tokens y del renderer añadidas en main. |
| Búsqueda automática de `SchemaComposer.php` | Inventaría un buscador que podría no existir. |
| Eliminación de `seo.sitemap`, `seo.robots` y `seo.llms` | Rompería controles existentes y documentados. |
| Robots registrado incondicionalmente por las rutas locales | Ignoraría la configuración del endpoint. |
| `media.max_upload_bytes` global | Duplicaría una fuente que el servicio multimedia no utiliza. |
| Definiciones posteriores duplicadas de news_article y tech_article | Main ya contiene ambos presets. |
| Versión local de `src/database/ORM_GUIDE.md` | Presenta rutas y configuración antiguas. |
| `tests/DocumentationTest.php` y recuentos rígidos de ejemplos | Parte duplicaba comprobaciones existentes o convertiría una ampliación válida de las guías en un fallo. Las pruebas útiles de ejecución se conservaron. |
| Instrucción de navegación móvil que cita Dane y Base Confías | No se estableció como contrato general del framework a partir de referencias de proyectos particulares. Permanece en la rama original. |

## Mejoras añadidas durante esta integración

Estas no se atribuyen a una de las dos versiones:

- Opis 4.5.0, adaptación del wrapper, lectura de tareas de Opis 3.7 y pruebas de closures capturadas y ejecución entre procesos. Composer exige PHP 8.1.9 o superior por el fallo de referencias de WeakMap corregido en PHP.
- `docs/header.md`: guía específica de apertura del documento, recursos, metas, CSRF, navegación visible y actualización gestionada.
- `docs/menus.md`: contrato completo de MenuHelper, arreglo posicional, dropdown/collapse, elemento actual y validación de destinos.
- `docs/sanitizacion.md`: los dos métodos públicos de sanitización, wrappers, validación de entrada y escape de salida.
- Enlaces directos desde `docs/index.md` y referencias cruzadas entre Header, Footer, Render y Helpers.
- Corrección de la descripción de actualización gestionada de `footer.php`, que era imprecisa en ambas versiones.
- Pruebas del patrón dinámico de Router, alcance legacy, interruptores SEO individuales y campos del límite de correo generados por el instalador.

MenuHelper y SanitizeHelper no cambiaron entre las versiones comparadas: ya existían. Su documentación estaba dentro de helpers; esta integración mejora su desarrollo y localización. El header compartido también existía, pero carecía de una guía propia equivalente a la del footer.

## Resultado comprobado

Tras actualizar Opis, `composer check` con PHP 8.1.33 portátil: 410 pruebas, 4140 aserciones, 3 omitidas; sin fallos. Lint correcto y 9 skills válidos. Composer validate estricta, auditoría de seguridad y requisitos de plataforma correctos. Se verificaron también los ejemplos de las nuevas guías de menús y sanitización. El PHP 8.1.5 de XAMPP necesita actualizarse antes de utilizar esta integración.

Los cambios permanecen en `codex/integracion-main-local` sin commit ni push. Las ramas originales y el respaldo siguen disponibles.
