# Inventario de CI, lint y comprobaciones

Revisión del checkout del 6 de octubre de 2026, lote 22; estado actualizado tras el lote 25. Inventario de configuración y código de pruebas; no ejecución de pipelines remotos ni una medición de cobertura de líneas. Los lotes 23–24 incorporan JS/YAML al workflow y el 25 completa lint, sin cambiar Composer, runtime o clientes.

## Cadena automática actual

[tests.yml](../.github/workflows/tests.yml) define GitHub Actions para push y pull_request. Tiene un job Ubuntu con matriz PHP 8.1, 8.2, 8.3 y 8.4, instala dependencias bloqueadas con Composer y ejecuta `composer check`. Configura mbstring, openssl y pdo_sqlite; `coverage: none`. La matriz declara versiones menores, no evidencia de qué parches se ejecutaron en un run remoto.

[composer.json](../composer.json) encadena `lint`, `test` y `skills:check`. El directorio de dependencias es `packages`. [phpunit.xml](../phpunit.xml) carga `packages/autoload.php` y descubre tests bajo `tests/`; su sección source incluye `src/`, pero no activa por sí sola un informe de cobertura.

Desde el lote 23, el workflow añade un job JavaScript independiente en Ubuntu con Node 24 y `actions/setup-node@v7`, sin instalación npm ni cache de paquetes. Ejecuta `node --test --test-reporter=tap tests/js/*.test.cjs`; el resultado muestra fallos y omisiones. Versiones/opciones contrastadas con la [documentación de setup-node](https://github.com/actions/setup-node) y las [versiones de Node](https://nodejs.org/en/about/previous-releases). Las dos pruebas optativas de navegador permanecen fuera del alcance de ese job.

El lote 24 añade el job skills-metadata en Ubuntu con Python 3.12 mediante `actions/setup-python@v7`. Instala `PyYAML==6.0.3` exclusivamente en ese entorno de CI, ejecuta el control de las catorce skills y sus siete pruebas unittest. Intérprete y parser explícitos, sin modificar dependencias Composer ni metadatos. Fuentes contrastadas: [setup-python](https://github.com/actions/setup-python) y [PyYAML](https://pypi.org/project/PyYAML/6.0.3/).

| Control | Incluido en la cadena actual | Alcance y límites comprobados |
| --- | --- | --- |
| [Lint PHP](../bin/lint.php) | Sí | `php -l` en src, bin, tests, config, resources y maintenance, más bin/gframe-update. No ejecuta el framework ni prueba comportamiento. |
| PHPUnit | Sí | 87 archivos `*Test.php` tras el lote 25. Incluye tests funcionales, de documentación, integración local y herramientas. Número de archivos no equivale a número de tests ni a cobertura completa. |
| [Estructura/enlaces de skills](../bin/validate-skills.php) | Sí | Catorce skills y links locales; no comprueba recetas o selección del agente. |
| [Metadata YAML](../bin/validate-skills-metadata.py) | Sí, job separado desde lote 24 | Python 3.12/PyYAML 6.0.3 en CI; campos conocidos opcionales y sintaxis YAML. Incluye las siete pruebas de [test_skill_metadata.py](../tests/test_skill_metadata.py). No prueba exactitud de instrucciones ni carga en clientes. |
| Pruebas JavaScript | Sí, job separado desde lote 23 | 21 archivos `tests/js/*.test.cjs` con node:test. No hay package.json en la raíz; predominan módulos nativos y dobles de DOM/transportes. Dos tests del instalador requieren navegador/Playwright y pueden omitirse. |
| [Instalación por proyecto](../tests/InstallProjectSkillsTest.ps1) | No | PowerShell y fixtures temporales. Se comprobó en Windows PowerShell 5.1; no prueba carga real en clientes. |
| [Generación del complemento](../tests/BuildSkillsPluginTest.ps1) | No | PowerShell, catálogo, hashes y portabilidad. La prueba usa junctions de Windows; no se debe copiar sin adaptación a un job Linux. |
| MySQL/MariaDB, Nginx y navegador reales | Sin jobs específicos | Parte de sus pruebas existe, pero puede omitirse sin entorno. No hay servicios ni variables que lo habiliten en este workflow. |
| SMTP/Redis reales | No acreditado | No se identificó un job de integración real para esos servicios. La prueba manual SMTP envía correo y no pertenece a la suite automática por defecto. |

No se consultó el estado de runs en GitHub. Esta configuración no acredita que el remoto tenga exactamente estos cambios sin commit ni que sus ejecuciones estén pasando.

## Límites de lint

El conteo estático inicial del lote 22 encontró 140 PHP en src, cinco en bin, 105 en tests y 131 en resources/modules. El lote 25 añade una clase de prueba; los archivos de tests incluyen fixtures además de clases de PHPUnit.

Las omisiones iniciales de `config/defaults.php`, los dos PHP de resources/install, los quince PHP de resources/skeleton, maintenance y [bin/gframe-update](../bin/gframe-update) se incluyen desde el lote 25. El ejecutable sin extensión tiene selección explícita. Se conservan los PHP de bibliotecas y fixtures que ya se comprobaban; no se entra en vendor/packages, .git ni otros directorios fuera del conjunto seleccionado.

El comando permite `--root` para una copia/fixture identificada, rechaza raíces inexistentes o sin archivos seleccionados y pasa argumentos a PHP directamente sin shell. Las pruebas [LintCommandTest](../tests/LintCommandTest.php) verifican detección de errores en las áreas añadidas, shebang, rutas con espacios, ausencia de ejecución y exclusiones. No se afirma cobertura sintáctica de todos los archivos del repositorio ni corrección funcional por pasar lint.

## Integraciones y omisiones

- [MySqlMigrationIntegrationTest](../tests/MySqlMigrationIntegrationTest.php) requiere `GFRAME_TEST_MYSQL_DSN`; crea y elimina una base temporal. Los tests MySQL de [InstallerTest](../tests/InstallerTest.php) y [DatabasePreflightTest](../tests/DatabasePreflightTest.php) se habilitan con `GFRAME_TEST_MYSQL=1` y parámetros de conexión. Son opt-in para un servidor de pruebas identificado. La evidencia histórica en MariaDB no demuestra MySQL 8.
- [ServerRoutingTest](../tests/ServerRoutingTest.php) verifica configuración y tiene un test real con `GFRAME_TEST_NGINX` y `GFRAME_TEST_PHP_CGI`; sin ambos puede omitirse. Un test de texto no prueba routing real.
- `tests/js/installer.test.cjs` requiere Playwright y `GFRAME_TEST_BROWSER` para dos pruebas de navegador. Su setup usa `GFRAME_TEST_PHP` o una ruta predeterminada de XAMPP. Un job Linux que las habilite debe identificar explícitamente PHP y navegador, no reutilizar esa ruta.
- Algunas pruebas se omiten sin PDO SQLite, PDO MySQL, GD o posibilidad de iniciar un servidor HTTP. El workflow declara SQLite, pero no convierte toda omisión restante en fallo. Registrar las omisiones es necesario antes de afirmar cobertura de una integración.
- [DatabaseSessionHandlerTest](../tests/DatabaseSessionHandlerTest.php) usa SQLite en memoria; no establece funcionamiento de Redis. [smtp-live.php](../tests/fixtures/smtp-live.php) requiere `--send`, configuración y destinatario; no se ejecutó ni se propone incluirlo en un check normal.
- [NewProjectCommandTest](../tests/NewProjectCommandTest.php) prueba `--help` con PHP habitual. No demuestra CLI sin extensiones mediante `php -n`. [ProjectUpdateServiceTest](../tests/ProjectUpdateServiceTest.php) y [ModuleCatalogTest](../tests/ModuleCatalogTest.php) contienen controles de archivos publicados/actualización; deben seleccionarse cuando se cambie skeleton o publicación, sin atribuirles todas las garantías de un despliegue.

## Adopción por lotes

| Orden | Propuesta | Criterio de cierre |
| --- | --- | --- |
| 1 | Job JS implementado en lote 23 | Comando local comprobado en Node 24.13.0: 80 tests, 78 pasan y dos omisiones de navegador, sin fallos. Ejecución del job en GitHub pendiente hasta subir los cambios; no se modificaron reglas de protección de ramas. |
| 2 | Job YAML implementado en lote 24 | Validación local con Python 3.12.14/PyYAML 6.0.3: catorce skills y siete tests correctos, sin omisiones. Run en Ubuntu/GitHub pendiente de subir el cambio; Composer/PHP sigue independiente. |
| 3 | Lint ampliado en lote 25 | Nueve tests y 32 aserciones correctos; errores detectados en fuentes nuevas, sin ejecución y con exclusiones comprobadas. Lint completo local comprobado y ejecución remota pendiente. |
| 4 | Job Windows para instalador/complemento | Ejecutar las dos pruebas PowerShell en temporales, preservando directorios globales. No prometer portabilidad Linux de junctions. |
| 5 | Integraciones por entorno y pruebas faltantes | MySQL, Nginx/PHP-CGI, navegador, Redis/SMTP y CLI sin extensiones solo con alcance/entorno propios; distinguir checks existentes de pruebas aún no creadas. |

La implementación de cada propuesta es un lote separado. No se exige ejecutar todas las suites para una corrección editorial ni se considera la estructura de una skill prueba de sus contratos. Semántica/recetas y carga real del complemento conservan sus pendientes.
