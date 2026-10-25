# Auditoría transversal de alineación de GFrame 4.3.0

> **Seguimiento conciliado — 2026-10-25:** H-01 y H-02 cerrados mediante CORRECCIONES.md y release 4.3.1. El dictamen del snapshot 4.3.0 se conserva intacto. La metadata YAML y las verificaciones posteriores no convierten todas las omisiones ambientales o de navegador en aprobaciones. Véase [estado y evidencia de cierre](../../cierre-auditorias-20261025.md).

## Corte y alcance

Base: `8fdacc30795076f7f4ca8489f56edab70ab86b33` (`v4.3.0`), árbol inicialmente limpio. Solicitud: contrastar framework, instalador, contratos, documentación y skills antes de nuevas funcionalidades. Windows/PHP 8.3.35, SQLite y MySQL local. No se modificó runtime, documentación pública ni skills durante este pase.

Es una revisión transversal del paquete publicado, no el cierre de una etapa con CONCILIACION/TAREAS propias. No se inventan criterios históricos ni se reabren las etapas de cron/CSRF. Se aplica la separación entre evidencia de auditoría y reparación. La revisión semántica se concentró en instalación/actualización, descubrimiento de versión, límites de autenticación y capacidades editoriales 4.3.0; no certifica cada API del paquete.

## Dictamen

**Alineación incompleta.** Dos contradicciones comprobadas: una funcional entre restauración e interpretación del estado de publicación, y una documental en la versión del generador. Las suites generales pasan, pero no detectan el primer cruce. No se propone añadir módulos.

### H-01 — Alta: restaurar historial puede volver a publicar

- Promesa: `docs/content-revisions.md`, apartado Restaurar: restaurar «no publica automáticamente».
- Configuración recomendada: `docs/content-publication.md` incluye `status` y `publish_at` en `historyFields()`.
- Implementación: `RevisionedContentModel::restoreRevision()` recupera todos esos campos y llama a `saveWithHistory()` sin separar las transiciones de publicación. La protección de `ScheduledContent` se aplica a sus métodos específicos, no a esta restauración.
- Reproducción independiente: `php maintenance/auditorias/alineacion-4.3.0/repro-restauracion-publicacion.php`. Utiliza únicamente SQLite en memoria y el mismo modelo documentado. Captura un contenido publicado, lo programa para 2099 y restaura el snapshot publicado: resultado `before=scheduled`, `restore_code=content_saved`, `after=published`. No interviene cron ni se espera la fecha futura.
- Impacto: una integración que autorice restauración como operación distinta de publicación puede hacer visible contenido mediante esa restauración. No se afirma una vulnerabilidad en una aplicación concreta: depende de sus rutas, permisos y lectores.
- Hueco de pruebas: las pruebas de historial restauran texto; las de programación verifican publicación/historial, pero no la restauración de snapshots con estados de publicación.
- Corrección necesaria: mantener los snapshots de auditoría sin permitir que una restauración normal ejecute implícitamente una transición a publicado o reactive una programación antigua. Definir una vía explícita para restaurar estados si se requiere. Añadir una regresión combinada en SQLite y MySQL; alinear documentación y referencia ORM. No basta con borrar la promesa documental.

### H-02 — Media: guía de skills y generador discrepan

- `docs/skills.md`, Activación local: afirma que el esqueleto requiere `gorvet/gframe:^2.0.1`.
- `resources/skeleton/composer.json` requiere `^4.3`; `ProjectSkillsInstallerTest` verifica esa restricción; `docs/actualizaciones.md` también anuncia `^4.3`.
- Corrección necesaria: distinguir la disponibilidad histórica del instalador PHP desde 2.0.1 de la restricción actual del generador. No cambiar el código ni la historia de publicaciones para ajustar esa frase.

## Matriz de contraste

| Superficie | Evidencia del pase | Resultado y límite |
| --- | --- | --- |
| Instalador, scaffolder y actualización | Suite PHP; casos HTTP de perfiles y wizard; SaaS y preflight en MySQL desechable | Sin fallos en los casos ejecutados. Las personalizaciones y archivos administrados tienen pruebas; no equivale a probar un despliegue de producción. |
| Contratos editoriales | Comparación de VersionedContentModel, RevisionedContentModel, ContentTrash, ScheduledContent y sus guías; reproducción H-01 | No alineado en restauración/publicación. Se conservan los límites propios de aplicación, actor/tenant, CAS y transacciones. |
| Vista previa y autorización | ContentPreviewController, referencia backend y pruebas PHP | Lectura exige sesión, ámbito activo y autorización previa del registro por el proyecto; no-store/noindex y estado sin HTML. No sustituye comprobar cada endpoint de una aplicación. |
| Cron/CSRF y canales | Suite PHP y CronCsrfBoundaryTest | Sin fallos en los casos ejecutados; HTTPS/FPM y servidores externos siguen sin ejecutarse. |
| Documentación | Contraste semántico focalizado y 712 destinos locales | Ningún destino ausente; H-01/H-02 abiertos. Los enlaces válidos no prueban que todas las recetas sean correctas. |
| Skills | 14 skills, 79 enlaces; referencias backend, ORM, core y jobs frente a código | Estructura válida y límites fundamentales presentes. La receta ORM de restauración necesita incorporar el desenlace de H-01. Metadatos YAML no verificados por falta de PyYAML. |
| Navegador | Suite JS de este pase | 81 aprobadas, 17 omitidas. La integración de navegador registrada antes de la entrega es antecedente, no una repetición independiente en este pase. |

## Evidencia ejecutada

- PHP: 530 pruebas, 5321 aserciones, cero fallos/errores, 14 omisiones. Logs/JUnit: `.phpunit.cache/alignment-4.3.0.log` y `.xml`.
- MySQL específico: 21 pruebas, 226 aserciones, cero fallos/errores/omisiones. Incluye historial/papelera/programación, migraciones, preflight, bindings booleanos y perfil SaaS. Logs/JUnit: `.phpunit.cache/alignment-4.3.0-mysql.log` y `.xml`. Los esquemas usados por las pruebas se crean con nombres temporales y se eliminan al terminar; bindings utiliza una tabla temporal de conexión.
- Once de las catorce omisiones PHP tienen cobertura en esa ejecución MySQL separada. Restan tres ambientales: HTTPS/FPM, Nginx y Apache. No se declaran aprobadas.
- Lint: 493 PHP válidos, `.phpunit.cache/alignment-lint.log`.
- JS: 98 casos, 81 aprobados, 17 omitidos, cero fallos, `.phpunit.cache/alignment-4.3.0-js.log`.
- Skills: `php bin/validate-skills.php`, 14/79. Comprobación adicional de Markdown fuera de bloques de código: 712 destinos relativos existentes en docs/skills, `.phpunit.cache/alignment-links.json`; no valida fragmentos ni URLs externas.
- `validate-skills-metadata.py`: no ejecuta validación por faltar PyYAML; no se instaló una dependencia adicional implícitamente.
- Reproducción H-01: archivo versionable adyacente, salida observada incluida arriba. El reproductor describe la conducta defectuosa del corte; no es una prueba de aceptación que deba conservar ese comportamiento.

## Pendientes y criterio de segunda revisión

1. Corregir H-01 en un cambio independiente con prueba negativa: restaurar texto no publica ni rearma fechas antiguas; mantener autor, scope, CAS, historial atómico y conexión del modelo.
2. Corregir H-02 sin cambiar la restricción actual del generador.
3. Repetir las verificaciones afectadas y el reproductor contra el nuevo corte, registrar el cambio de resultado y comprobar docs/skills. No repetir suites ajenas por una corrección puramente editorial.
4. Conservar como no verificados los tres escenarios de infraestructura, las 17 pruebas de navegador omitidas y los metadatos YAML hasta disponer de sus entornos.

No se ha publicado otra versión ni actualizado consumidores como parte de esta auditoría. Los módulos aplazados y el backlog existente permanecen intactos.
