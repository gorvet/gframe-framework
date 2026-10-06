# Hallazgos para la integración de ramas

Este archivo registra diferencias detectadas durante la auditoría documental y de runtime que no deben resolverse a ciegas durante una fusión. Los hallazgos confirmados de esta rama ya se corrigieron o quedaron documentados como límites reales.

La regla para integrar es simple: **después de fusionar código, el runtime resultante vuelve a ser la fuente de verdad**.

## 1. Mail: protección antispam integrada después de la auditoría

La auditoría de reconstrucción confirmó que `GFrame\Mail\MailService` no implementaba un rate limiter propio para formularios públicos. La integración posterior incorpora `MailRateLimiter` y la opción explícita `rate_limit`, sin limitar el correo interno que omite esa opción. Consulta [Correo y plantillas](mail.md) para el contrato vigente.

La configuración bajo `notifications.email.max_attempts` y `retry_delay_seconds` pertenece a la cola de `notifications-email`, no a `MailService`.

La documentación anterior prometía `rate_limit`, `MAIL_RATE_LIMIT_*` y códigos `mail_rate_*` sin implementación; esa afirmación se retiró durante la auditoría. Ahora esas opciones cuentan con implementación y pruebas de ventana móvil, fallos, concurrencia y envío asíncrono.

**Estado:** capacidad integrada en el código actual; no formaba parte del runtime de la auditoría original.

## 2. Media: fuente única del límite de carga

`config/defaults.php` exponía `media.max_upload_bytes`, pero `MediaLibraryService` no consumía esa clave. El límite efectivo procedía de `resources/modules/media-library/config/media.php` mediante `MediaProcessor::getMaxUploadBytes()`.

La clave residual fue retirada de defaults y `ConfigurationDefaultsTest` comprueba que la fuente sea única.

**Estado:** hallazgo de runtime resuelto y cubierto por prueba.

## 3. SEO: `robots.txt` y sitemap desactivado

`routes_system.php` omitía `/sitemap.xml` cuando `SEO_ENABLE_SITEMAP_XML=false`, pero `Robots::render()` seguía anunciándolo.

`Robots` utiliza ahora la misma condición que el registro de la ruta. `MetaSeoTest` verifica que un sitemap desactivado no sea anunciado.

**Estado:** hallazgo de runtime resuelto y cubierto por prueba.

## 4. Errores: origen real del `noindex`

`error-pages.group.meta.php` declara:

```php
'robots' => 'noindex, nofollow',
```

Ese metadato, no el status HTTP por sí solo, genera la política robots de las páginas de error. `docs/errores.md` ya fue corregido.

**Estado:** corrección documental resuelta.

## 5. Notifications Email: reintentos sí; recuperación de jobs abandonados no

`EmailQueueProcessor` reserva trabajos, incrementa intentos, marca `sent`, reprograma `pending` con `available_at` o marca `failed`. No recupera por sí mismo filas abandonadas en `processing` tras una caída abrupta.

`docs/notifications-email.md` ya documenta esta limitación.

**Estado:** documentación correcta; límite conocido.

## 6. Campaigns y Cron: recuperación distinta a la cola de email

`CampaignService::dispatch()` utiliza `recoverRecipients($campaignID, 900)` antes de reservar destinatarios. Las campañas sí recuperan destinatarios bloqueados después del umbral; no debe extrapolarse ese comportamiento a `notification_queue`.

**Estado:** documentación alineada.

## 7. Compatibilidad legacy: clasificación final

### API global vigente cargada por classmap

Composer carga deliberadamente áreas como `src/routing`, `src/render`, `src/database`, `src/middleware`, `src/async`, `src/cron`, `src/services` y `src/utils`.

Por tanto, `RouteBuilder`, `ORM`, `HttpClient`, `Async` o `UrlHelper` no son legacy por carecer de namespace.

### Wrappers globales de compatibilidad

`src/utils/LegacyCompatibility.php` conserva funciones como `guess_url()`, `is_ssl()`, `sanitize()`, `randomNameGen()`, `buildMenu()`, `pagination()`, `send_cors_headers()`, `markdown2html()` y `logger()`.

Los ejemplos nuevos deben enseñar primero las clases/helpers equivalentes.

### Fallbacks de configuración todavía soportados

El patrón actual es `config/app.php` + defaults del paquete. `Bootstrap` todavía admite `config/bootstrap.php` y `core/Config.php` como fallbacks heredados. `LegacyConfigBridge` deriva constantes históricas desde la configuración estructurada para consumidores existentes.

### Contratos concretos de compatibilidad

Entre otros:

- `HttpClient::requestCompat()`;
- nombres históricos de métodos de Auth;
- fallback a controllers globales en ciertos overrides de módulos runtime.

No se detectó una red general de `class_alias()` o clases marcadas como deprecated que constituya otra capa global de migración.

**Estado:** clasificación documental cerrada.

## 8. Documentación interna fuera de `docs/`

Se revisaron las notas internas relevantes:

- `src/database/ORM_GUIDE.md` remite a la referencia actual;
- `src/heartbeat/README.md` remite a la documentación pública;
- `src/seo/SCHEMA_GUIDE.md` queda como nota de arquitectura;
- `AGENTS.md`, `CONTRIBUTING.md` y `SECURITY.md` no introducen APIs alternativas;
- `CHANGELOG.md` conserva historia y no se interpreta como guía vigente;
- `maintenance/` está declarado expresamente como archivo histórico/snapshot y no como backlog canónico.

**Estado:** cerrado para esta fase.

## 9. Auth: caducidad de tokens de verificación

`resetPassword()` validaba `token_updated_at` mediante `TokenManager::isValidTimestamp()`, pero `validateAcount()` aceptaba un token de verificación independientemente de su antigüedad.

Se corrigió `AuthModel::validateAcount()` para aplicar la misma ventana temporal. Un token vencido devuelve `invalid_token` y no activa la cuenta.

`AuthVerificationTokenExpiryTest` cubre token vencido y token vigente. `docs/autenticacion.md` documenta ahora una única política temporal para verificación y recuperación.

**Estado:** hallazgo de runtime resuelto y cubierto por prueba.

## 10. JSON-LD: cuatro desacoples de runtime

La revisión histórica señalaba varias limitaciones que seguían presentes en código.

### Presets compuestos

`SchemaComposer` solo resolvía el preset solicitado inicialmente. Los moldes que contenían `preset`/`presets` internos no se resolvían recursivamente y el catálogo incluía además definiciones duplicadas/autorreferenciales para algunos nombres.

Ahora:

- los presets anidados se resuelven recursivamente;
- los ciclos producen `InvalidArgumentException` en lugar de recursión infinita;
- se retiraron las definiciones duplicadas autorreferenciales;
- `saas_landing` conserva `SoftwareApplication` como tipo principal.

### SearchAction implícito

`SchemaComposer` inventaba `/buscar?q={search_term_string}` aunque una aplicación no tuviera buscador. Ese fallback fue eliminado. `SearchAction` solo se genera si el proyecto proporciona un target explícito con el placeholder correspondiente.

### `0` y `false`

`JsonLD` utilizaba `array_filter()` sin callback al construir nodos, eliminando datos válidos como `price=0`, `directApply=false` o `isAccessibleForFree=false`.

El filtrado actual elimina ausencia real (`null`, `''`, `[]`) y conserva `0`/`false`. Los agregados de software tampoco inventan ya `lowPrice=0` por planes que carecen de precio.

### `seo.enabled=false`

`LegacyConfigBridge` ya definía `SEO_ENABLED=false`, pero `Meta::renderSchema()` ignoraba esa constante y seguía generando JSON-LD. Ahora devuelve `''` cuando SEO está desactivado.

`SchemaJsonLdRuntimeTest` cubre estos contratos.

**Estado:** hallazgos de runtime resueltos y cubiertos por pruebas.

## Uso de este archivo al fusionar

Antes de cerrar una integración con otras ramas:

1. comparar las ramas y revisar si la otra rama toca alguno de estos contratos;
2. conservar los tests que fijan los comportamientos corregidos;
3. no reintroducir por conflicto documentación retirada por carecer de implementación;
4. revisar especialmente `AuthModel`, `Meta`, `SchemaComposer`, `JsonLD`, `schema.presets.php`, `Robots` y `config/defaults.php`;
5. conservar como historia los cambios del `CHANGELOG` y `maintenance/` sin convertirlos en API vigente;
6. después de integrar, ejecutar de nuevo la suite y tratar el código resultante como fuente de verdad.
