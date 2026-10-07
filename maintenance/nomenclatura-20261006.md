# Inventario de nomenclatura de GFrame

Revisión del 6 de octubre de 2026, lote 20. No se renombró ningún símbolo ni se cambió runtime. La [convención canónica](../skills/gframe-core-architecture/references/naming-conventions.md) sigue aplicándose al código nuevo; este inventario clasifica el existente.

## Alcance y método

Inspección de `src/` y `resources/`, con exclusión de módulos `external-ui`, carpetas sin manifiesto, las bibliotecas PHP de markdown/password-utils, demos de iconos y JS minificado. Se conservaron wrappers propios de esos módulos backend. No se inspeccionaron proyectos instalados, dependencias Composer, tests, documentación como código ni scripts PowerShell.

El [generador de evidencia](inventory-naming.php) lee los manifiestos como texto, sin ejecutarlos, y utiliza `token_get_all()` para variables PHP: no cuenta nombres dentro de comentarios o cadenas como variables. Registra ubicaciones para las variantes y hashes de los archivos inspeccionados. Las búsquedas JS y de IDs HTML son léxicas, no un parser de JavaScript ni una comprobación del DOM. Pueden omitir parámetros, destructuring, templates dinámicos o atributos interpolados; no prueban colisiones ni accesibilidad.

Reproducir desde el framework:

```powershell
C:/xampp/php/php.exe maintenance/inventory-naming.php
```

El comando actualiza únicamente [nomenclatura-evidencia-20261006.json](nomenclatura-evidencia-20261006.json). El reporte identifica el generador y los archivos mediante SHA-256. Una ejecución posterior describe ese nuevo checkout, no preserva automáticamente la evidencia anterior.

| Medida | Resultado | Interpretación |
| --- | --- | --- |
| PHP inspeccionado | 270 archivos | Variables tokenizadas, incluidos parámetros y vistas PHP. |
| JS inspeccionado | 33 archivos | Candidatos léxicos y revisión puntual de consumidores. |
| HTML inspeccionado | 6 archivos | Templates estáticos, además del HTML incluido en archivos PHP. |
| Nombres PHP distintos terminados en `ID`/`IDs` | 23 | Uso existente; el sufijo por sí solo no prueba significado o calidad. |
| Nombres PHP distintos terminados en `Id`/`Ids` | 6 | Mezcla de estilo que debe clasificarse antes de cambiar. |
| Variables PHP distintas con guion bajo | 6 | Sin superglobales; incluye firmas públicas y variables de templates. |
| Declaraciones/candidatos JS con `Id`/`Ids` | 12 ubicaciones | Incluye funciones y variables; no son doce fallos. |
| Coincidencias de IDs HTML literales | 177 | Apariciones en fuentes; no son elementos únicos renderizados. |

## Variantes PHP comprobadas

| Nombre y fuente | Clasificación | Acción propuesta |
| --- | --- | --- |
| `$userId`, [Middleware.php](../src/middleware/Middleware.php), bloque de autorización | Variable local; llamadas usan valores posicionales en el bloque revisado. | Renombrado opcional a `$userID` cuando se trabaje ese bloque. No hay fallo demostrado por el nombre. |
| `$firstId`, `$lastId`, [ORM.php](../src/database/ORM.php), inserción por lote y recorrido por chunks | Variables locales de algoritmos. | Conservar ahora; una edición de esos algoritmos podría unificar el sufijo con verificación funcional propia. |
| `$mediaId`, `$mediaIds`, [SitemapDataProvider.php](../src/seo/SitemapDataProvider.php) | Locales y parámetro de un método privado, `fetchMediaUrlsByIds`. | Mejora opcional y acotada; revisar llamadas internas y recetas si se propone. |
| `$mediaId`, [_mlist.php](../resources/modules/media-library/application/app/views/media-library/_mlist.php) | Variable local que produce IDs y atributos HTML existentes. | Un cambio local no debe alterar `mID_`, `data-media-id` ni `media_id`. |
| `$submenuId`, [MenuHelper.php](../src/utils/MenuHelper.php) | Local que produce un ID de navegación con prefijo `sm_`. | Conservar el valor y los atributos relacionados; no convertir el prefijo por estética. |
| `$csrf_token`, `$csrf_timestamp`, `$middle_name`, [Middleware.php](../src/middleware/Middleware.php) | Locales con nombres de otro estilo. Las claves de entrada son contratos distintos. | Mejora local opcional; conservar `csrfToken`, `csrfTimestamp` y `middle_name`. |
| `$max_lifetime`, [DatabaseSessionHandler.php](../src/GFrame/Session/DatabaseSessionHandler.php) y [RedisSessionHandler.php](../src/GFrame/Session/RedisSessionHandler.php) | Parámetro público de `gc()` en implementaciones de interfaces de sesión. | Conservar junto con `create_sid()` y `validateId()`. No aplicar el estilo interno a firmas externas. |
| `$total_pages`, [LegacyCompatibility.php](../src/utils/LegacyCompatibility.php) | Parámetro de la función pública de compatibilidad `pagination()`. | Preservar; cualquier cambio requiere revisar argumentos nombrados y consumidores externos. |
| `$tenant_id`, [mediaPickerModal.php](../resources/modules/media-library/application/app/views/media-library/mediaPickerModal.php) | Entrada opcional al template, usada cuando no se obtuvo tenant de la ruta. | Preservar como punto de integración. No tratarla como variable privada del template ni sustituir el tenant configurado por uno fijo. |

## Nombres públicos y JavaScript

- `AuthModel::registerAcount()` y `recoveryAcount()` existen y tienen consumidores en AuthController. Su errata histórica no autoriza reemplazarlos. Un alias o transición se concilia como cambio de compatibilidad separado.
- `$byId` en los componentes multimedia representa una búsqueda de un nodo DOM, no un identificador de negocio. No se propone convertir todos los usos de `Id` indiscriminadamente ni modificar métodos externos como `getElementById()` o `lastInsertId()`.
- `nextIds`, `currentIds` e `initialIds` en media-field almacenan selección multimedia; unificar sus locales sería una mejora de estilo posible, con revisión del flujo múltiple. Los campos `ids`, `id`, eventos y APIs del picker se conservan.
- `buildScopeId()` de heartbeat y `getHashId()`/`loadHashId()` del skeleton identifican scope/fragmento. Se conservan nombres y formatos de storage/URLs. La búsqueda léxica no demuestra que todos sean APIs públicas ni garantiza ausencia de consumidores externos.
- No se generó un catálogo completo de métodos públicos, herencia o argumentos nombrados. Esos controles siguen pendientes para cualquier propuesta de renombrado.

## Recorridos entre capas comprobados

| Recorrido | Evidencia revisada | Decisión |
| --- | --- | --- |
| Usuarios: `data-user-id` → `row.data('user-id')` → `user_id` → `$userID` | `_userList.php`, user-admin.js y UserAdminController.php; esquema auth usa `user_id`. | Conservar el mapeo. Cada capa usa su convención. |
| Campañas: `#campaign-users` y `name="user_ids[]"` → `$_POST['user_ids']` → criterio `user_ids` | form.php, campaigns.js, CampaignController.php y CampaignUserAudience.php. | Conservar selector, lista enviada y criterios. `user_ids[]` no es nombre de variable PHP. |
| Multimedia: `media_id` → `$mediaId` → `mID_<n>` y `data-media-id` | `_mlist.php`. | El sufijo local puede revisarse; la forma de IDs/atributos no cambia con él. No se auditó todo el transporte del picker en este lote. |
| Avisos: `notification_id` → NotificationController → argumento `$notificationID` del servicio | notifications.js, NotificationController.php y NotificationService.php. | Conservar payload y API. El controlador ya existente no se creó en esta revisión. |
| CSRF: formulario `csrfToken`/`csrfTimestamp` → SessionManager/Middleware | Vista user-admin y ambos componentes de seguridad. | Excepción técnica explícita; no convertir claves a snake_case. |

No se verificaron aquí todos los modelos, SQL y consumidores de todos los módulos. Los recorridos anteriores son muestras concretas, no un cierre global de N-03.

## Selectores y prioridad

`userModal` y `userListMount` se comparten entre vista y JS de usuarios. `campaign-users` usa kebab-case en campañas; multimedia mantiene `mediaPickerModal` y IDs dinámicos `mID_`. La mezcla está comprobada. No se comprobó una colisión en una página renderizada y no se recomienda una conversión masiva. La decisión para componentes nuevos sigue en la skill frontend-admin; no se añade una regla global distinta aquí.

1. Mantener las convenciones acordadas en código nuevo y respetar los nombres públicos/externos existentes.
2. Priorizar cobertura de las instrucciones y verificadores de enlaces/metadata antes de renombrados puramente estéticos.
3. Si se propone un renombrado local, delimitar archivo/bloque y demostrar que no cambia payloads, HTML, storage ni resultados.
4. Si afecta parámetros públicos, templates de integración, selectores o persistencia, tratarlo como contrato y conciliar compatibilidad antes de implementar.

## Firmas públicas y herencia

[Catálogo de declaraciones](nomenclatura-apis-20261006.json), generado por [inventory-public-api.php](inventory-public-api.php): 272 archivos PHP, 150 tipos y 763 métodos públicos/funciones con sus nombres de parámetros, paso por referencia y variádicos. Incluye interfaces, padres y traits declarados; analiza AST con el parser de las dependencias de desarrollo, sin ejecutar fuentes del framework o módulos. Incluye los helpers propios Markdown/password-utils que el primer inventario de variables excluía.

Para reproducirlo desde este checkout con dependencias de desarrollo disponibles, ejecuta `php maintenance/inventory-public-api.php`. Dos ejecuciones produjeron el mismo hash del catálogo; sus 272 hashes de fuentes coinciden con los archivos inspeccionados. El inventario es independiente del runtime y no añade una dependencia de producción.

Solo tres parámetros públicos del conjunto usan guion bajo o sufijo Id/Ids: `DatabaseSessionHandler::gc($max_lifetime)`, `RedisSessionHandler::gc($max_lifetime)` y `pagination($total_pages)`. Los dos primeros respetan SessionHandlerInterface; el tercero es una función legacy cuyo parámetro puede usarse mediante argumentos nombrados. Se conservan los tres. Las variantes Id/Ids registradas como variables no justifican cambiar por sí solas una API pública.

El catálogo representa declaraciones encontradas, no todos los métodos heredados efectivos ni llamadas externas. Omite clases anónimas como puntos de extensión estables, excluye módulos external-ui y carpetas sin manifiesto, y no certifica el API de dependencias o extensiones PHP. Antes de un renombrado público futuro, hay que seguir padres/traits/interfaces y consumidores de ese símbolo; un catálogo de firmas no puede demostrar ausencia de callers externos.

La adopción actual se limita a código nuevo: referencia común desde las catorce skills y reglas de IDs/clases nuevos en frontend-admin. Se conservan todos los nombres existentes. Un renombrado futuro se trata como cambio independiente con mapeo de consumidores y compatibilidad; no se crea un verificador que declare errores por cada excepción histórica.

El inventario no encontró evidencia de un fallo de funcionamiento causado únicamente por estas variantes. No evalúa por ese hecho la corrección completa de los módulos.

## Comprobación posterior de transporte y DOM

El recorrido posterior cubre los ocho módulos con controladores MVC propios. Se mantienen las diferencias necesarias entre atributos HTML, claves de transporte, parámetros PHP y columnas; no se aplica una conversión masiva.

| Módulo | Correspondencia comprobada | Resultado |
| --- | --- | --- |
| auth-ui | Campos `login_email`/`login_password`, registro y recuperación → AuthController → AuthModel; identidad persistida `user_id` y rol `role_id`. | Se conservan nombres y contratos de autenticación, incluidos métodos históricos. |
| self-account | Campos de contraseña → SelfAccountController → SelfAccountService; el usuario proviene de la sesión. | No se añade un `user_id` del navegador como autoridad. |
| user-admin | `data-user-id` → `user_id`, `role_id` → UserAdminController → UserAdministrationService/UserModel/RoleModel. | Mapeo consistente con esquema y servicios. |
| media-library | `data-media-id` → `media_id`; selección `media_ids` JSON/lista → MediaController → MediaLibraryService/MediaModel. | Identificadores conservados y ámbito resuelto en servidor. |
| notifications | `data-notification-id` → `notification_id` → controlador/servicio/modelo, con usuario y tenant del servidor. | Mapeo consistente; `id` de la ruta de detalle sigue siendo su contrato de URL. |
| notification-campaigns | `data-campaign-id`/formulario → `campaign_id`; `user_ids[]` → criterios `user_ids` → CampaignModel/audiencia. | Mapeo consistente; IDs de URL y columnas no se renombran. |
| heartbeat-client | Claves de canales y payload `visible`/`force` → HeartbeatController/HeartbeatMaster → handler autorizado. | No tiene un nuevo ID persistente que normalizar. |
| admin-panel | AdminController devuelve el contexto de la vista. | Sin formulario de identidad ni transporte propio que renombrar. |

Nueve conjuntos de vistas nativas se renderizaron con datos ficticios: usuarios con gestión habilitada, Mi cuenta, lista/formulario de campañas, modales multimedia juntos y las cuatro pantallas de autenticación. Sus 83 IDs no presentan duplicados ni destinos ausentes de `label[for]` en esas composiciones. [Resultados del render](nomenclatura-render-20261006.json). Esta comprobación no cubre todos los estados, overrides o plantillas de aplicaciones instaladas.

La comprobación adicional de los dos listados multimedia con el mismo archivo sí detectó IDs vacíos y repetidos. Se corrigió el fragmento del picker con prefijo `mp-`, conservando IDs históricos de la biblioteca y claves de transporte. El cliente envía `fragment=picker` según su modo y los controles de paginación pertenecen a cada instancia. No se encontraron selectores CSS propios que dependieran de los IDs cambiados del picker. `MediaMountIdentifiersTest` cubre el DOM PHP combinado; `media-multiple-mounts.test.cjs` lo verifica en Edge real, sin red, junto con independencia de filtros, paginación e identidad seleccionada.

El catálogo de API actualizado después de los contratos contiene 274 archivos PHP, 152 tipos y 771 declaraciones públicas, con hashes de las fuentes actuales. La nomenclatura queda resuelta para este alcance del framework; consumidores externos y composiciones personalizadas se comprueban al intervenir en el proyecto que los posee.
