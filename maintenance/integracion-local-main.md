# Integración de mejoras locales sobre main

Fecha: 6 de octubre de 2026.

Los [ajustes posteriores de documentación e identificadores](ajustes-documentacion-20261006.md) registran los cambios solicitados después de esta conciliación. El inventario de 142 archivos describe la selección entre las fuentes originales y el ajuste de Opis; esos cambios posteriores se documentan aparte para no atribuirlos a las ramas comparadas.

## Estado y fuentes

- Base de GitHub y del `main` local: `5b7d7fe7bd913798473d2903573a0c14cb19f7dd`.
- Rama local original conservada: `codex/auditoria-reconstruccion`, en `5cbc233bc9b82b3ad4c15932bc429199f62569f1`.
- Rama de trabajo: `codex/integracion-main-local`, creada desde la base de main.
- Comparación completa: 142 archivos diferentes entre main y el HEAD local original.
- [Inventario por archivo](integracion-local-main-inventario.csv): 79 conservan main, 29 recuperan el local intacto, 33 integran o adaptan cambios y 1 permanece solo en la rama original. La actualización posterior a Opis 4 reclasifica Composer, ClosureWrapper, las pruebas de Async y el requisito PHP del README como adaptaciones. La [comparativa de calidad](comparativa-github-local.md) explica qué mejoró con cada fuente y qué estaba peor frente a la otra versión.
- Los dos commits locales recientes, `1d52ee6` y `5cbc233`, no se subieron. La comparación incluye también los dos commits documentales anteriores que no son ancestros de main.
- El estado original de la mezcla se guardó antes de cancelarla: archivos modificados, índice, metadatos de merge y un bundle verificado del historial en `.git/local-recovery/20261005-212035/`.
- No se eliminaron ramas ni se publicaron cambios. Las mejoras trasladadas quedan sin commit para revisión.

## Actualización de Opis acordada el 6 de octubre de 2026

- [x] Mantener Opis: instalada `opis/closure` 4.5.0 con restricción `^4.3`, retirada la dependencia Laravel y adaptado `ClosureWrapper` a las funciones de Opis 4. Se comprueba la lectura de una tarea real de Opis 3.7, su conversión a Opis 4 y la ejecución en otro proceso PHP. Actualizadas la documentación y la comparativa. El aviso de Opis 3.7 no implica que la librería Opis esté deprecada ni justifica por sí solo cambiar de proveedor.
- [ ] Actualizar el PHP de XAMPP antes de usar esta integración en ese servidor. PHP 8.1.5 reproduce el fallo de referencias en WeakMap corregido en PHP 8.1.9; el mínimo de Composer pasa a `^8.1.9`. Para la verificación se utiliza PHP 8.1.33 portátil, sin modificar XAMPP ni el PATH persistente. Los payloads locales de Laravel deben finalizar antes del cambio; no se mantiene esa dependencia para leerlos.

## Decisiones de código

| Área o archivos | Resultado |
| --- | --- |
| `src/routing/Router.php` | Recuperado `routeParams.uri`, que conserva el patrón declarado sin confundirlo con la carpeta del controlador. Incluye prueba de ruta parametrizada. |
| `src/async/Async.php`, `ClosureWrapper.php`, Composer | Se conserva Opis y se actualiza a la rama 4, con lectura del formato anterior mediante `v3_unserialize()`. La sustitución local por Laravel se descarta: no se demostró una ventaja frente a actualizar Opis. Se conserva el resto del ejecutor de main. |
| `MailService.php`, `MailRateLimiter.php` | Recuperada protección opcional para formularios públicos: ventana móvil, concurrencia, cuota independiente por identidad y ámbito, devolución de cupo ante fallos y reserva única al encolar. |
| `config/defaults.php`, `.env.example`, `ProjectConfigWriter.php` | Recuperadas exclusivamente las opciones del límite de correo. |
| `SeoPolicy.php`, `Meta.php`, `Sitemap.php`, `Llms.php` | Recuperada y adaptada la política común de rutas públicas, protegidas, privadas y de error. Conserva la configuración y el alcance legacy documentados por main. |
| `Robots.php` | El bloqueo global utiliza la política común. Conserva la comprobación de Sitemap habilitado de main. |
| Rutas de `auth-ui` | Recuperado `context.seo.indexable=false` en login, registro, recuperación, restablecimiento y verificación. |
| Metas de admin-panel, auth-ui, error-pages, media-library, notifications, self-account y user-admin | Retirados robots redundantes de las metas. La política real pertenece al contexto y protección de la ruta. |
| `schema.presets.php` | Recuperado FAQ como complemento que no cambia el tipo principal de otro preset. Se conserva el tipo explícito SoftwareApplication de SaaS de main. |
| `SchemaComposer.php` | Conservado main: resolución recursiva, detección de ciclos y búsqueda únicamente explícita. La implementación local anterior volvería a inventar SearchAction y perdería el manejo correcto del arreglo vacío. |
| Presets news_article y tech_article | Ya existen en main. No se agregaron las definiciones duplicadas del archivo local. |
| `JsonLD.php` | Conservado main: valores cero/false, ofertas sin precio, rating vacío y normalización de nodos. |
| `AuthModel.php` | Conservada la comprobación de caducidad de tokens de main. |
| Configuración multimedia | Conservada la fuente única del límite de carga. No se recuperó la clave global residual del archivo local. |
| `LegacyConfigBridge.php`, `routes_system.php`, `ProjectInstaller.php`, `install.php` | Conservados main y los interruptores individuales `seo.sitemap`, `seo.robots` y `seo.llms`. No se fuerza la publicación de Robots cuando el endpoint está desactivado. |

## Documentación y skills

La estructura principal, las guías ampliadas y el tutorial CRUD canónico proceden de main. No se reemplazaron por documentos locales más cortos ni por el tutorial duplicado de la mezcla cancelada.

Se actualizaron de forma puntual correo, dependencias, Async, Router y SEO para corresponder al código integrado. Las notas históricas identifican los contratos posteriores sin reescribir las versiones publicadas.

Se añadieron las guías de Header, Menús y Sanitización al índice. MenuHelper y SanitizeHelper ya existían en el código; su referencia se encontraba dentro de helpers. Las guías nuevas explican sus métodos, ubicación, entradas, salida, límites y wrappers. La guía de Header distingue la apertura del documento de la navegación visible y describe recursos, campos CSRF y actualización gestionada.

Los seis ejemplos completos de JSON-LD se copiaron del documento local como complemento de la referencia más amplia de main. No se trasladaron las afirmaciones locales antiguas sobre una búsqueda predeterminada. Las pruebas ejecutan esos ejemplos con el renderer conservado de main.

`src/database/ORM_GUIDE.md` conserva la versión de main, con rutas `src/database/` y configuración actual. `src/seo/SCHEMA_GUIDE.md` también conserva su estructura de main, corrigiendo las afirmaciones internas obsoletas sobre recursión y búsqueda.

Las skills canónicas de backend y arquitectura incorporan los contratos de correo y la política SEO integrada. Se conservaron también las correcciones locales de mantenimiento sobre documentación y el fragmento Nginx situado en la raíz. La instrucción local de navegación que cita proyectos particulares queda en la rama original: no se convierte en un requisito general de todos los proyectos. No se instaló una copia ajena al repositorio.

## Pruebas locales conservadas

- Async y antispam de Mail.
- Política SEO, compatibilidad de interruptores y exclusiones legacy, rutas privadas/de error y páginas de autenticación.
- Todos los presets existentes, composición con FAQ y parámetros de Router.
- Ejemplos documentados de autenticación, utilidades, JSON-LD, idiomas, multimedia, middleware, notificaciones, ORM, permisos, Render y sesiones.

Las pruebas documentales se adaptaron a la documentación canónica más amplia cuando correspondía. No se importó la prueba local que duplicaba comprobaciones de enlaces y exigía retirar indiscriminadamente notas históricas de la documentación de main.

## Verificación

- Composer install y validate estricta: correctos.
- Composer audit con lock: sin avisos de vulnerabilidades.
- Comparación de espacios y parches: correcta.
- Pruebas específicas finales de política SEO, presets, ejemplos JSON-LD y enlaces: 16 pruebas, 374 aserciones, sin avisos ni fallos.
- Sintaxis de las nuevas guías y ejecución de sus ejemplos de menús y sanitización: correctas.
- Verificación inicial: `composer check` con 407 pruebas, 4133 aserciones y 3 omitidas; sin fallos.
- Tras actualizar Opis: `composer check` con PHP 8.1.33 portátil, 410 pruebas, 4140 aserciones y 3 omitidas; sin fallos. Lint correcto y 9 skills válidos. Composer validate estricta, auditoría con lock y requisitos de plataforma correctos.
