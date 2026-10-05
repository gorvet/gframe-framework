# Hallazgos para la integración de ramas

Este archivo registra diferencias detectadas durante la auditoría documental y de runtime que no deben resolverse a ciegas durante una fusión. Los hallazgos confirmados de esta rama ya se corrigieron o quedaron documentados como límites reales.

La regla para integrar es simple: **después de fusionar código, el runtime resultante vuelve a ser la fuente de verdad**.

## 1. Mail: rate limit no implementado por `MailService`

La auditoría confirmó que `GFrame\Mail\MailService` no implementa un rate limiter propio para formularios públicos. Sus opciones efectivas de envío se relacionan con SMTP, destinatario, `reply_to`, `recipient_name` y `timeout`; las variantes asíncronas delegan en `Async`.

`config/defaults.php` tampoco contiene un bloque `mail.rate_limit`. La configuración de reintentos relacionada con correo bajo:

```php
'notifications' => [
    'email' => [
        'max_attempts' => 5,
        'retry_delay_seconds' => 300,
    ],
],
```

pertenece a la cola de `notifications-email`, no a `MailService` ni a formularios públicos.

La documentación anterior prometía `rate_limit`, `MAIL_RATE_LIMIT_*` y códigos `mail_rate_*`; esa afirmación ya fue retirada de `docs/mail.md`. La guía actual indica que la protección contra abuso debe aplicarse en middleware, controlador o un servicio del proyecto antes de llamar a Mail.

### Decisión al integrar

Si otra rama añade un rate limiter real a `MailService`, volver a auditar su API y documentarlo únicamente después de integrar ese código. No recuperar por conflicto la documentación antigua sin implementación.

**Estado:** corrección documental resuelta; la capacidad de rate limit no forma parte del runtime auditado.

## 2. Media: fuente única del límite de carga

La auditoría detectó que `config/defaults.php` exponía `media.max_upload_bytes`, pero `MediaLibraryService` no consumía esa clave. El límite efectivo ya procedía de:

```text
resources/modules/media-library/config/media.php
```

mediante `MediaProcessor::getMaxUploadBytes()`.

La clave residual fue retirada de `config/defaults.php`. El bloque de aplicación conserva únicamente opciones de integración como `scope` y `quota_bytes`; el procesamiento del archivo sigue perteneciendo al procesador del módulo.

Se añadió `ConfigurationDefaultsTest` para comprobar que:

- el default global no vuelve a exponer `media.max_upload_bytes`;
- el módulo sí declara `max_upload_bytes`;
- `MediaProcessor::getMaxUploadBytes()` devuelve ese valor.

`docs/media-library.md` ya enseña la fuente correcta y explica la extensión mediante un `MediaProcessor` propio.

**Estado:** hallazgo de runtime resuelto y cubierto por prueba.

## 3. SEO: `robots.txt` y sitemap desactivado

La auditoría detectó que `routes_system.php` omitía correctamente `/sitemap.xml` cuando `SEO_ENABLE_SITEMAP_XML` era falso, pero `Robots::render()` seguía anunciando:

```text
Sitemap: <site_url>/sitemap.xml
```

El runtime fue corregido para que `Robots` utilice la misma condición que el registro de la ruta: la línea `Sitemap:` solo aparece cuando el sitemap está habilitado o cuando la constante no está definida y se conserva el comportamiento predeterminado.

`MetaSeoTest` incluye ahora un caso separado que verifica que un sitemap desactivado no sea anunciado por `robots.txt`.

**Estado:** hallazgo de runtime resuelto y cubierto por prueba.

## 4. Errores: origen real del `noindex`

El módulo `error-pages` declara expresamente:

```php
'robots' => 'noindex, nofollow',
```

en `error-pages.group.meta.php`. Ese metadato es el mecanismo que genera la política robots de las páginas de error; el código HTTP no crea por sí solo el `<meta name="robots">`.

`docs/errores.md` ya fue corregido para atribuir el comportamiento al metadato del módulo y advertir que una personalización debe conservar conscientemente esa política si se desea mantener el `noindex`.

**Estado:** corrección documental resuelta.

## 5. Notifications Email: reintentos sí; recuperación de jobs `processing` abandonados no

`EmailQueueProcessor`:

- reserva trabajos del canal `email`;
- incrementa intentos mediante el repositorio;
- marca `sent` en éxito;
- vuelve a `pending` con `available_at` cuando todavía puede reintentar;
- marca `failed` al alcanzar el máximo.

No implementa por sí mismo recuperación temporal de filas que queden abandonadas en `processing` por una caída abrupta del worker.

`docs/notifications-email.md` ya advierte esta limitación correctamente.

**Estado:** documentación correcta; comportamiento conocido, no presentado como una capacidad inexistente.

## 6. Campaigns y Cron: recuperación distinta a la cola de email

`CampaignService::dispatch()` llama a:

```php
$this->campaigns->recoverRecipients($campaignID, 900);
```

antes de reservar destinatarios, por lo que las campañas sí tienen recuperación de destinatarios bloqueados en procesamiento después del umbral configurado por ese contrato.

No extrapolar esta capacidad a `notification_queue` ni a `EmailQueueProcessor`: son mecanismos distintos.

**Estado:** documentación de campañas alineada.

## 7. Compatibilidad legacy: clasificación final

La auditoría distingue cuatro categorías que no deben mezclarse durante una fusión.

### API global vigente cargada por classmap

Composer carga deliberadamente áreas como:

```text
src/routing/
src/render/
src/database/
src/middleware/
src/async/
src/cron/
src/services/
src/utils/
```

Por tanto, clases globales como `RouteBuilder`, `ORM`, `HttpClient`, `Async` o `UrlHelper` **no son legacy por el solo hecho de carecer de namespace**.

### Wrappers globales de compatibilidad

`src/utils/LegacyCompatibility.php` conserva funciones como:

- `guess_url()`;
- `is_ssl()`;
- `sanitize()`;
- `randomNameGen()`;
- `buildMenu()`;
- `pagination()`;
- `send_cors_headers()`;
- `markdown2html()`;
- `logger()`.

La documentación nueva enseña primero las clases/helpers equivalentes y mantiene estas funciones únicamente como compatibilidad.

### Fallbacks de configuración todavía soportados

La configuración recomendada actual es `config/app.php` + defaults del paquete. `Bootstrap`, sin embargo, todavía admite `config/bootstrap.php` y `core/Config.php` como fallbacks heredados cuando no existe la configuración estructurada.

Eso significa que son compatibilidad soportada, no el patrón que debe enseñarse a proyectos nuevos.

`LegacyConfigBridge` cumple la función inversa necesaria para aplicaciones actuales: deriva constantes históricas desde la configuración estructurada para código que aún las consume.

### Contratos concretos de compatibilidad

También existen puntos específicos, como:

- `HttpClient::requestCompat()`;
- nombres históricos de métodos de Auth que siguen disponibles;
- fallback a controllers globales del proyecto en ciertos overrides de módulos runtime.

No deben promocionarse automáticamente a API recomendada solo porque continúen funcionando.

No se detectó durante esta pasada una red general de `class_alias()` o clases marcadas como deprecated que obligue a una segunda capa de migración global. La compatibilidad visible está concentrada en contratos concretos.

**Estado:** clasificación documental cerrada.

## 8. Documentación interna fuera de `docs/`

Se revisaron las notas internas más relevantes encontradas durante la auditoría:

- `src/database/ORM_GUIDE.md` ya no enseña rutas/configuración antiguas y remite a las guías canónicas;
- `src/heartbeat/README.md` remite a la documentación pública correspondiente;
- `src/seo/SCHEMA_GUIDE.md` se mantiene como nota de arquitectura, no como tutorial paralelo;
- `AGENTS.md`, `CONTRIBUTING.md` y `SECURITY.md` no introducen contratos alternativos del framework;
- `CHANGELOG.md` conserva referencias históricas en su contexto de versión y no debe reescribirse como si fueran instrucciones actuales.

**Estado:** no se detectó otra fuente paralela que requiera saneamiento en esta fase.

## Uso de este archivo al fusionar

Antes de cerrar una integración con otras ramas:

1. comparar las ramas y revisar si la otra rama vuelve a tocar alguno de estos contratos;
2. conservar los tests que fijan los comportamientos corregidos;
3. no reintroducir por conflicto documentación retirada por carecer de implementación;
4. conservar como historia los cambios del `CHANGELOG`, sin confundirlos con la API recomendada actual;
5. después de integrar, ejecutar de nuevo la suite y tratar el código resultante como fuente de verdad.
