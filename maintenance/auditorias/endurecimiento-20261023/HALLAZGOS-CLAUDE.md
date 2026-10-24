# Endurecimiento de seguridad — hallazgos

> Auditoría defensiva de solo lectura del core realizada por Claude (Opus) el 2026-10-23, a petición del propietario del framework. Alcance: `src/` (routing, middleware, auth, sesiones, ORM, CORS, cifrado, multimedia). No se ejecutó explotación; los puntos son análisis estático y de contrato.

## Estado

| # | Hallazgo | Severidad | Estado |
| --- | --- | --- | --- |
| 1 | Identificadores/operadores sin validar en el ORM | Alta | **Implementado** |
| 2 | Cookies de sesión sin HttpOnly/Secure/SameSite forzados | Alta | **Implementado** |
| 3 | Redirección abierta por backslash en `pick()` | Media | **Implementado** |
| 4 | Confianza en cabeceras `X-Forwarded-*` en `is_same_origin()` | Media-baja | **Implementado en continuación** |
| 5 | Token CSRF sin caducidad (max-age) | Baja | **Implementado** (opcional, desactivado por defecto) |

> MediaStorage (traversal del nombre de archivo): **revisado y correcto**, sin cambios. `segment()` reduce el nombre a `[a-z0-9_-]` y `absolute()` rechaza rutas con `..`, iniciales con `/` y normaliza backslashes.

## Mapa de cambios (dónde y qué)

Para ubicar cada edición sin depender de números de línea, busca los símbolos indicados.

### Hallazgo 1 — `src/database/ORM.php`

- **Añadido:** constante `COMPARISON_OPERATORS` y métodos privados `assertIdentifier()` y `assertComparisonOperator()`, justo después de `isSafeIdentifier()`.
- **Modificado** (ahora llaman a esos validadores, constructores de cara a la petición): `where()`, `orWhere()`, `whereNull()`, `whereNotNull()`, `orWhereNull()`, `orWhereNotNull()`, `whereLikePattern()`, `whereAnyLike()`, `whereInWithBoolean()`, `whereBetweenWithBoolean()`, `orderBy()`.
- **Solo comentario de contrato** (sin cambio de lógica), posiciones de estructura/expresión propias del código: `select()`, `groupBy()`, `having()`, `join()`, `orderByExpr()` — admiten agregados/alias y por eso no validan identificadores (revisión de Codex: validarlos rompía `HAVING` con agregados y `JOIN` con alias).
- **Sin tocar:** `whereRaw()`, `whereExact()` (este ya usaba `quoteIdentifier()`).

### Hallazgo 2 — sesión

- `src/GFrame/Session/SessionRuntime.php`: en `start()`, añadidas las líneas `ini_set('session.use_only_cookies', '1')` y `self::applyCookieParams()` antes de resolver el nombre y `session_start()`. Nuevos métodos privados `applyCookieParams()` y `requestIsSecure()`.
- `config/defaults.php`: nuevas claves bajo `session` → `cookie_httponly`, `cookie_secure`, `cookie_samesite`, `cookie_path`, `cookie_domain`, `cookie_lifetime`.

### Hallazgo 3 — `src/routing/Router.php`

- **Modificado:** método privado `pick()`. Añadida `str_replace('\\', '/', $rd)` tras `urldecode()`, antes de quitar el host propio y de los bloqueos existentes.

### Hallazgo 5 — CSRF

- `src/GFrame/Security/CsrfToken.php`: en `validate()`, tras la comparación correcta del token, comprobación de antigüedad **solo para invitados** (`security.csrf_guest_max_age`); si caduca, `rotate()` y `to_reload`. Método nuevo `isGuestSession()`.
- `config/defaults.php`: nueva sección `security` con `csrf_guest_max_age` (0 = desactivado).

### Documentación y contrato

- `docs/orm.md`: subsección «Validación de identificadores y operadores».
- `docs/sesiones.md`: tabla de banderas de cookie (reemplaza el párrafo que decía que `SessionRuntime` no fijaba esas opciones).
- `skills/gframe-orm-models/references/orm-query-patterns.md`: sección «Allowed Ordering» actualizada.
- `CHANGELOG.md`: dos entradas en «Sin publicar» con prefijo `Seguridad`.

### Cómo verificar (otro agente)

```bash
# 1. Diff acotado a los archivos de código
git diff -- src/database/ORM.php src/GFrame/Session/SessionRuntime.php \
  src/routing/Router.php src/GFrame/Security/CsrfToken.php config/defaults.php

# 2. Confirmar que los validadores del ORM existen y están conectados
grep -n "assertIdentifier\|assertComparisonOperator\|COMPARISON_OPERATORS" src/database/ORM.php

# 3. Confirmar las banderas de cookie antes de session_start()
grep -n "applyCookieParams\|use_only_cookies\|session_set_cookie_params\|requestIsSecure" src/GFrame/Session/SessionRuntime.php

# 4. Confirmar backslash en pick() y caducidad CSRF
grep -n "protocolo-relativo" src/routing/Router.php   # normalización de backslash en pick()
grep -n "csrf_guest_max_age\|isGuestSession" src/GFrame/Security/CsrfToken.php config/defaults.php

# 5. Pruebas (deben quedar en verde, sin fallos)
php packages/bin/phpunit --no-coverage
php bin/validate-skills.php
```

Comprobaciones de comportamiento esperadas:

- `where('a; DROP', '=', 1)`, `orderBy('1; DROP')` o un operador fuera de la lista blanca lanzan `Exception` (no llegan al SQL).
- `orderBy('users.email')`, `where('user_id', '=', 1)` y `join('roles', 'roles.role_id', '=', 'users.role_id')` siguen funcionando igual (identificadores válidos, con punto permitido).
- `groupBy('SUBSTR(created_at, 1, 7)')` y `select('COUNT(*) AS total')` siguen funcionando (vías de expresión).
- Tras una petición autenticada, la cookie de sesión responde con `HttpOnly`, `SameSite=Lax` y, bajo HTTPS, `Secure`.

## 1. Identificadores y operadores del ORM — Implementado

**Antes:** `src/database/ORM.php` enlazaba los valores con `?` (correcto) pero concatenaba crudos los nombres de columna/tabla y los operadores en `where()`, `orWhere()`, `whereIn()`, `whereNull()`/`whereNotNull()`, `whereBetween()`, `whereLike()`/`whereAnyLike()`, `having()`, `join()` y `orderBy()`. `ORDER BY` no admite enlaces, por lo que una columna de ordenación procedente de la petición era inyectable. La defensa ya existía (`isSafeIdentifier()`/`quoteIdentifier()`) pero solo se usaba en `whereExact()`.

**Cambio:** dos validadores centrales en `ORM.php`:
- `assertIdentifier()`: exige identificador seguro (`[A-Za-z_][A-Za-z0-9_]*`, un punto opcional para `tabla.columna`) o lanza excepción.
- `assertComparisonOperator()`: normaliza y valida contra la lista blanca `= != <> > >= < <= LIKE "NOT LIKE"`.

Conectados a todos los constructores que interpolan identificadores/operadores. Las vías de **expresión** se conservan y se documentan como de entrada propia del código, sin validar: `select()`, `groupBy()`, `orderByExpr()`, `whereRaw()`. Es defensa en profundidad: no sustituye la lista blanca de columnas de ordenación en el controlador, pero convierte un descuido en excepción en lugar de SQLi.

**Contrato:** una columna/operador no literal ahora lanza excepción. Ayuda actualizada en `docs/orm.md` y skill `gframe-orm-models`.

## 2. Cookies de sesión — Implementado

**Antes:** `SessionRuntime::start()` activaba `use_strict_mode` pero no llamaba a `session_set_cookie_params()`; las banderas `HttpOnly`, `Secure` y `SameSite` quedaban a merced del `php.ini` (por defecto, sin HttpOnly ni SameSite).

**Cambio:** `SessionRuntime::applyCookieParams()` fija las banderas antes de `session_start()`, con `use_only_cookies` y valores seguros por defecto configurables: `HttpOnly=true`, `SameSite=Lax`, `Secure` automático bajo HTTPS (`HTTPS`, puerto 443 o `X-Forwarded-Proto`). Nuevas claves en `config/defaults.php` (`session.cookie_*`). Ayuda actualizada en `docs/sesiones.md`.

## 3. Redirección abierta por backslash — Implementado

`Router::pick()` (`src/routing/Router.php`) bloqueaba `//`, `http(s)://` y CRLF, pero no `\`. Un `rd=/\evil.com` quedaba como `/\evil.com`, que varios navegadores tratan como protocolo-relativo.

**Cambio:** al inicio de `pick()`, tras `urldecode()`, se normaliza `\`→`/` (`str_replace('\\','/',$rd)`) antes de quitar el host propio y de validar. Un destino con backslashes se reduce a ruta interna o al `$fallback`, nunca a un host externo.

## 4. Cabeceras X-Forwarded-* — Pendiente

Actualización de la continuación: middleware, cookies y límites HTTP usan `RequestSecurity`, con proxies IP/CIDR explícitos en `security.trusted_proxies` (vacío por defecto). Las cookies conservan políticas PHP existentes más estrictas. Véase `maintenance/seguridad-auditoria-20261023.md` para el alcance y la verificación posterior; la descripción siguiente conserva el hallazgo original.

`Middleware::is_same_origin()` usa `HTTP_X_FORWARDED_PROTO`/`HTTP_X_FORWARDED_HOST` como *fallback*. Riesgo real bajo porque `site_url` suele aportar host, pero no conviene derivar decisiones de seguridad de cabeceras falsificables salvo tras un proxy de confianza que las reescriba.

## 5. CSRF sin caducidad — Implementado (solo invitados, opcional)

`CsrfToken::validate()` compara con `hash_equals` (correcto) pero no aplicaba ventana máxima al `csrfTimestamp`.

**Decisión de diseño (propietario):** para un usuario autenticado el token debe caducar con la sesión (logout o inactividad, `session.idle_timeout`), nunca con un temporizador propio que provocaría un 403 a mitad de una operación. Solo las sesiones de **invitado** (formularios públicos) admiten una caducidad acotada.

**Cambio:** tras validar el token, si la sesión es de invitado (`CsrfToken::isGuestSession()` → sin `auth.id`) y `security.csrf_guest_max_age` (segundos) es mayor que 0 y el token supera esa antigüedad, se rota y devuelve `to_reload`. Para usuarios autenticados no se aplica caducidad propia. Por defecto `0` (desactivado). Clave nueva en `config/defaults.php` → `security.csrf_guest_max_age`.

## Verificado y correcto (sin cambios)

- Cifrado AES-256-GCM con IV aleatorio y validación de tag/versión (`Encryption.php`).
- Login con `password_verify` + `session_regenerate_id(true)` + rotación de CSRF.
- Subidas: MIME real con `finfo` y lista blanca de extensiones (`MediaProcessor.php`). Sanitización del nombre y traversal en `MediaStorage` **revisados y correctos**: `segment()` reduce el nombre a `[a-z0-9_-]`, `normalizeExtension()` a `[a-z0-9]`, y `absolute()` rechaza `..`, rutas iniciadas con `/` y normaliza backslashes.
- Divulgación de errores controlada por `DebugMode` (por defecto `false`).

## Verificación de los cambios 1 y 2

`php packages/bin/phpunit --no-coverage`: 553 pruebas correctas (1 warning y 14 omitidas, previos y ajenos a este cambio).
