# Revisión inicial de seguridad e historial

> **Seguimiento conciliado — 2026-10-25:** La revisión ampliada autorizada se realizó después y quedó integrada por PR #2. Las capturas se incorporaron en entregas posteriores. La configuración efectiva del hosting y los límites del escaneo histórico conservan su alcance; no equivalen a una certificación de producción. Véase [estado y evidencia de cierre](../../cierre-auditorias-20261025.md).

Alcance: repositorios locales del framework y del sitio documental, refs disponibles con `--all`. Se conservan las correcciones y el informe de alineación anteriores. No se reescribe historial, revocan credenciales ni modifican aplicaciones o producción.

## Historial

- Framework: 198 commits, 2602 blobs de texto inspeccionados. Sitio documental: 24 commits, 246 blobs de texto.
- `git log --all --format= --name-only` con patrones `.env`, `.env.*` y equivalentes anidados solo identifica `resources/skeleton/.env.example` y `.env.example`, respectivamente. No aparece un `.env` real en esos historiales.
- El escaneo de objetos alcanzables identifica encabezados de claves privadas y formatos conocidos de GitHub, AWS, Google API, Stripe live y Slack. Cero candidatos en ambos repositorios. Los valores nunca se imprimen; los informes solo admiten ruta, objeto, regla y línea.
- Evidencia: `.phpunit.cache/security-history-framework.json`, `.phpunit.cache/security-history-docs.json` y script `.phpunit.cache/security-history-scan.py`.
- Límite: no prueba ausencia universal de secretos. No detecta contraseñas arbitrarias o todos los formatos de proveedor; no cubre objetos inalcanzables, refs remotas no disponibles, forks, cachés externas, artefactos publicados, logs del hosting ni archivos ajenos a Git. No se comprueba validez de ninguna credencial contra servicios.

## Core y módulos: primer contraste

- CSRF usa token aleatorio y comparación constante, identidad de sesión y respuestas establecidas; se inspeccionó CsrfToken.
- SessionManager regenera el identificador al autenticar y SessionRuntime activa el modo estricto. Secure/HttpOnly/SameSite siguen dependiendo de PHP según `docs/sesiones.md`; no es una discrepancia nueva, pero debe verificarse la cabecera real de producción. Los valores del PHP CLI local no describen PHP-FPM del servidor.
- HtmlSanitizer usa lista de etiquetas/atributos, limpieza de URLs y LIBXML_NONET. La inspección no sustituye probar todos los contextos de salida.
- El inspector remoto multimedia restringe a HTTPS/443, rechaza IP privada/reservada, fija la resolución cURL y desactiva redirecciones/proxy. Se inspeccionaron también extensión/MIME en MediaLibraryService y normalización de MediaStorage; no se certifican todos los casos de filesystem o symlinks.
- HttpClient es un transporte general y verifica TLS por defecto; acepta URLs/headers del consumidor. No se ha demostrado un endpoint público que entregue entradas arbitrarias a ese transporte. Revisar aplicaciones consumidoras antes de clasificarlo como SSRF explotable.
- El .htaccess distribuido bloquea archivos internos, dotfiles y scripts fuera de los puntos de entrada permitidos. No se probó su configuración efectiva en aaPanel.

Pruebas focalizadas ejecutadas de nuevo: HtmlSanitizerTest, ApiBearerCorsTest, VisitorCsrfTest, MediaScopeResolverTest y AuthVerificationTokenTest: **30 pruebas, 80 aserciones, cero fallos y cero omisiones**, `.phpunit.cache/security-focused.xml`.

No se confirma una vulnerabilidad nueva en este pase. El problema de restauración/publicación ya identificado conserva su informe y corrección separados. Esto no constituye una auditoría de seguridad completa ni una prueba de penetración.

## Trabajo posterior útil

1. Ampliar revisión de autenticación/recuperación, revocación y permisos por registro/tenant, cargas/descargas y exposición de errores, con casos negativos específicos.
2. Comprobar en el hosting cabeceras de sesión, bloqueo HTTP de .env/.git/storage/packages y configuración PHP. No pedir ni copiar el contenido del .env.
3. Después, añadir capturas reales y versionadas a las guías de instalación, biblioteca y edición. El cron y las capacidades editoriales optativas necesitan una aplicación de ejemplo identificada: el sitio documental no aporta por sí solo esas pantallas. No ilustrar funcionalidades con pantallas inventadas ni incluir datos personales o secretos.
