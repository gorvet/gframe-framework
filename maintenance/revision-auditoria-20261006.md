# Revisión de la auditoría y reducción de alcance

Fecha: 6 de octubre de 2026. Base comprobada: `d5221de54654fc4ea66eeff5f99bff86e8612449`.

## Qué se comprobó

Los doce comportamientos del informe externo se reprodujeron sobre fuentes extraídas de la base original. El reproductor verifica que no se carguen archivos de `src/` modificados. Se usan las dependencias existentes del proyecto. C05 se ejecutó en MariaDB 10.4.24, no en MySQL 8.

| Hallazgo | Resultado en el original | Decisión de esta entrega |
| --- | --- | --- |
| C01 | Autoriza el tenant 2 mientras multimedia usa el tenant 1 de sesión. | Rechazar discrepancias entre ruta, solicitud y sesión; compartir resolución entre middleware y multimedia. |
| C02 | Una etiqueta GCM reducida a un byte todavía permite descifrar. | Exigir la longitud de etiqueta e IV emitida por el cifrador y validar los tipos del payload. |
| C03 | El reset cambia la contraseña y la sesión gestionada anterior sigue válida. | Revocar las sesiones gestionadas del usuario tras el reset. |
| C04 | La actualización histórica de auth falla por duplicar `authorization_version`. | Omitir una adición de columna solo si la definición existente coincide; conservar los SQL publicados. |
| C05 | MariaDB crea la tabla y registra la migración, pero el runner devuelve `There is no active transaction`. | Usar transacciones para SQLite; ejecutar DDL MySQL sin envolverlo en una transacción. |
| C06 | El JSON decodificado en objetos devuelve `wordpress_contract_mismatch`; el mismo contenido en arrays funciona. | Normalizar también los objetos JSON anidados. |
| C07 | Un contexto con solo `methods` permite un webhook sin secreto. | Conservar la autenticación predeterminada si el contexto no configura una credencial verificable. |
| C08 | `require_token` sin verificador acepta un token arbitrario. | Rechazar la configuración sin secreto esperado ni callable de verificación. |
| C09 | El segundo worker no recupera una notificación reservada por el primero. | Posponer la mejora de reservas y recuperación: era una limitación documentada. |
| C10 | Un handler que devuelve `status=error` se registra como éxito. | Conservar el contrato documentado: el handler debe lanzar una excepción para fallar. |
| C11 | `whereIn([])` omite el filtro y devuelve las dos filas. | Conservar el comportamiento documentado; revisar compatibilidad en otra entrega. |
| C12 | La ruta literal `audit.orders` coincide con `auditXorders`. | Escapar los caracteres literales y conservar los placeholders existentes. |

La auditoría describe comportamientos reales, pero reproducirlos no convierte automáticamente cada uno en un defecto. C09–C11 se separan como propuestas de contrato. C07 y C08 son ajustes de seguridad con efecto sobre configuraciones incompletas; su nueva exigencia se documenta expresamente.

## Archivos de esta entrega

| Archivo | Cambio y justificación |
| --- | --- |
| `src/GFrame/Auth/TenantContextResolver.php` (nuevo) | Resuelve IDs coherentes y exige sesión para el ámbito multimedia. Soporta la clave configurada y las claves de sesión existentes. |
| `src/middleware/Middleware.php` | Usa el resolver de tenant y corrige las verificaciones incompletas de webhook y SSE. |
| `src/GFrame/Media/MediaScopeResolver.php` | Usa el mismo tenant que se comprueba al autorizar. |
| `src/GFrame/Auth/RolePermissionService.php` | Evita que recargar los permisos globales sobrescriba el tenant activo con el marcador global `0`. La regresión de tenant comprueba ambas recargas. |
| `src/GFrame/Security/Encryption.php` | Rechaza etiquetas truncadas, IV incorrectos y payloads con tipos inválidos. |
| `src/GFrame/Auth/AuthModel.php` | Revoca sesiones tras recuperar la contraseña. La dependencia opcional conserva los argumentos anteriores del constructor. |
| `src/GFrame/Install/MigrationRunner.php` | Separa las transacciones por motor y comprueba las columnas ya presentes en las migraciones aditivas históricas. |
| `src/GFrame/Headless/WordPressClient.php` | Convierte los objetos anidados del transporte en arrays. |
| `src/routing/Router.php` | Escapa los segmentos literales de las rutas. |
| `tests/AuditRegressionTest.php` (nuevo) | Siete regresiones para tenant, cifrado, reset, migraciones, WordPress, guardas y rutas. El reset comprueba que otro usuario conserva su sesión. |
| `tests/MySqlMigrationIntegrationTest.php` (nuevo) | Tres recorridos sobre una base temporal: actualización histórica, actualización parcialmente aplicada e instalación con baseline. |
| `tests/MediaRuntimeTest.php` | Comprueba el rechazo de un tenant solicitado diferente del tenant activo y el éxito cuando coinciden. |
| `docs/actualizaciones.md` | Explica transacciones, columnas compatibles y límites de recuperación de migraciones MySQL. |
| `docs/permisos.md` | Documenta la coherencia requerida entre los tenants de ruta, solicitud y sesión. |
| `docs/rutas.md` | Documenta las credenciales verificables de webhook y SSE. |
| `docs/sesiones.md` | Incluye el reset por token en la revocación de sesiones gestionadas. |
| `CHANGELOG.md` | Registra solo las correcciones retenidas en una sección sin publicar. |
| Este informe (nuevo) | Conserva la clasificación, inventario y evidencia de la revisión. |

`NotificationController`, `CampaignController`, los modelos y procesadores de notificaciones, sus esquemas y manifiesto, cron, ORM, CI, lint y las nueve skills existentes quedan iguales a la base original.

## Trabajo pospuesto y respaldo

Las skills nuevas, el orquestador, el complemento, los cambios de contratos de cola/cron/ORM y el registro de sentencias de migración MySQL quedan para otra entrega. No se dan por terminados ni validados. Se conserva el patch completo anterior y los archivos nuevos originales en:

`C:/Users/Juank de Gorvet/Downloads/GFrame-cambios-pospuestos-20261006-133744/`

Allí se guarda también `reproducciones-original.jsonl`, con las doce reproducciones. No se deben reaplicar automáticamente los cambios pospuestos: requieren revisión de alcance y compatibilidad.

## Verificación

- Pruebas dirigidas tras los ajustes finales: 27 pruebas, 207 aserciones, sin errores.
- Integración en MariaDB 10.4.24: 3 pruebas, 7 aserciones, sin errores. Cada prueba crea y elimina una base con nombre aleatorio en el servidor temporal del puerto 13308.
- `composer validate --strict`: correcto.
- `composer audit`: sin avisos de vulnerabilidades.
- `php bin/lint.php`: correcto.
- `php bin/validate-skills.php`: nueve skills válidas.
- `git diff --check`: correcto.
- Suite completa: 420 pruebas, 4193 aserciones, sin errores ni fallos; seis pruebas omitidas. Tres omisiones corresponden a la integración MySQL, ejecutada por separado en MariaDB con éxito. Las otras tres mantienen las condiciones de omisión de la suite existente.

No se ejecutó MySQL 8 ni se modificaron proyectos instalados. La revocación multidispositivo requiere un driver gestionado. Las migraciones MySQL pueden quedar parcialmente aplicadas si una sentencia falla; no se ofrece recuperación automática de SQL arbitrario. Los cambios no se han publicado ni confirmado en Git.
