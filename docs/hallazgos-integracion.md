# Hallazgos para la integración de ramas

Este archivo registra diferencias detectadas durante la auditoría documental que no deben resolverse a ciegas durante una fusión. Algunas son contradicciones entre código y documentación; otras son comportamientos del runtime que conviene decidir si se conservan o se corrigen.

La regla para integrar es simple: **después de fusionar código, el runtime resultante vuelve a ser la fuente de verdad**.

## 1. Mail: rate limit documentado, pero no implementado en el código auditado

`docs/mail.md` describe una protección para formularios públicos basada en:

- `mail.rate_limit`;
- `MAIL_RATE_LIMIT_ENABLED`;
- `MAIL_RATE_LIMIT_MAX_ATTEMPTS`;
- `MAIL_RATE_LIMIT_WINDOW_SECONDS`;
- la opción `rate_limit` de `MailService`.

En la rama auditada, `GFrame\Mail\MailService` no contiene esa lógica. Sus opciones efectivas de envío son las relacionadas con SMTP, destinatario, `reply_to`, `recipient_name` y `timeout`; las variantes asíncronas delegan en `Async`.

`config/defaults.php` tampoco contiene un bloque `mail.rate_limit`. La única configuración de rate/retry relacionada con correo está bajo:

```php
'notifications' => [
    'email' => [
        'max_attempts' => 5,
        'retry_delay_seconds' => 300,
    ],
],
```

Eso pertenece a la cola de `notifications-email`, no a `MailService` ni a formularios públicos.

### Decisión al integrar

- Si otra rama implementa realmente el rate limit, conservar y volver a verificar la sección de `mail.md` contra ese código.
- Si no existe implementación después de la fusión, retirar esa sección de la documentación y no exponer las variables `MAIL_RATE_LIMIT_*` como API disponible.

**Estado:** pendiente de reconciliar con código integrado.

## 2. Media: `media.max_upload_bytes` permanece en defaults pero no gobierna el límite actual

`config/defaults.php` todavía incluye:

```php
'media' => [
    'scope' => 'global',
    'max_upload_bytes' => 26214400,
    'quota_bytes' => 0,
],
```

Sin embargo, `MediaLibraryService::registerLocalFile()` obtiene el límite mediante:

```php
$this->processor->getMaxUploadBytes()
```

El procesador toma su configuración del módulo. `MediaLibraryService` sí consulta `media.quota_bytes`, pero no `media.max_upload_bytes`.

`docs/media-library.md` ya explica correctamente que la clave antigua `media.max_upload_bytes` no controla el límite actual.

### Decisión al integrar

Si ningún consumidor real sigue usando `media.max_upload_bytes`, conviene marcarla formalmente como legacy o retirarla de defaults para evitar una configuración engañosa.

**Estado:** documentación correcta; posible limpieza de código/configuración.

## 3. SEO: `robots.txt` puede anunciar un sitemap desactivado

`routes_system.php` solo registra `/sitemap.xml` cuando la indexación está permitida y `SEO_ENABLE_SITEMAP_XML` está activo.

`Robots::render()`, cuando permite indexación, añade siempre:

```text
Sitemap: <site_url>/sitemap.xml
```

sin comprobar `SEO_ENABLE_SITEMAP_XML`.

Por tanto, una configuración con robots activo y sitemap desactivado puede publicar un `robots.txt` que anuncie una ruta de sitemap inexistente.

### Decisión al integrar

Valorar que `Robots` añada la línea `Sitemap:` únicamente cuando el sitemap esté realmente habilitado/registrado.

**Estado:** observación de runtime; `seo.md` ya documenta que los switches siguen caminos separados.

## 4. Errores: el `noindex` procede de las metas del módulo, no del código HTTP por sí solo

`docs/errores.md` afirma que la política SEO del núcleo bloquea la indexación de las páginas de error por su código HTTP.

En el módulo auditado, `resources/modules/error-pages/application/app/views/error-pages/error-pages.group.meta.php` declara expresamente:

```php
'robots' => 'noindex, nofollow',
```

Ese es el mecanismo documentalmente demostrable que termina generando el meta robots de las páginas de error.

### Decisión al integrar

Reformular la explicación de `docs/errores.md` para atribuir el `noindex` al metadato del módulo, salvo que otra rama añada una política central basada realmente en el status HTTP.

**Estado:** corrección documental pendiente.

## 5. Notifications Email: reintentos sí; recuperación de jobs `processing` abandonados no

`EmailQueueProcessor`:

- reserva trabajos del canal `email`;
- incrementa intentos mediante el repositorio;
- marca `sent` en éxito;
- vuelve a `pending` con `available_at` cuando todavía puede reintentar;
- marca `failed` al alcanzar el máximo.

No implementa por sí mismo recuperación temporal de filas que queden abandonadas en `processing` por una caída abrupta del worker.

`docs/notifications-email.md` ya advierte esta limitación correctamente.

**Estado:** documentación correcta; comportamiento a tener en cuenta operativamente.

## 6. Campaigns y Cron: recuperación distinta a la cola de email

`CampaignService::dispatch()` llama a:

```php
$this->campaigns->recoverRecipients($campaignID, 900);
```

antes de reservar destinatarios, por lo que las campañas sí tienen recuperación de destinatarios bloqueados en procesamiento después del umbral configurado por ese contrato.

No extrapolar esta capacidad a `notification_queue` ni a `EmailQueueProcessor`: son mecanismos distintos.

**Estado:** documentación de campañas alineada.

## 7. Compatibilidad legacy

La auditoría ya confirmó varias capas conservadas por compatibilidad:

- funciones globales en `src/utils/LegacyCompatibility.php`;
- constantes derivadas por `LegacyConfigBridge`;
- `HttpClient::requestCompat()`;
- nombres históricos de algunos métodos de Auth, como `registerAcount()`;
- contratos/rutas antiguas mantenidos expresamente por ciertos módulos.

Durante la fusión no deben promocionarse automáticamente a API recomendada por el hecho de seguir existiendo. La documentación nueva debe enseñar primero las APIs actuales y etiquetar lo legacy como compatibilidad.

## Uso de este archivo al fusionar

Antes de cerrar la reconstrucción documental:

1. fusionar o comparar las ramas de código;
2. volver a comprobar cada hallazgo contra el runtime final;
3. eliminar del listado lo que haya sido resuelto;
4. corregir la documentación de los hallazgos que sigan vigentes;
5. no conservar una afirmación documental solo porque sea la versión más reciente del Markdown.
