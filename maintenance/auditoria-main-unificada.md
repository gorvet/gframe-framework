# Auditoría de diferencias entre main y reconstrucción unificada

Rama auditada: `codex/reconstruccion-unificada`.

Base comparada: `main`.

Fecha de contraste: 2026-10-05.

## Resultado estructural

La rama unificada parte del HEAD actual de `main` y está por delante de él. En el momento de esta auditoría:

- `main` no contiene commits posteriores que falten en la unificada;
- la unificada acumula 124 commits sobre `main`;
- la gran mayoría de las diferencias corresponde a documentación, mantenimiento y tests;
- la superficie funcional modificada es pequeña y está concentrada en Auth, configuración y SEO/JSON-LD.

No debe interpretarse el número de commits como 124 cambios funcionales.

## Clasificación de archivos

En la comparación inicial:

| Grupo | Archivos |
| --- | ---: |
| Documentación pública | 92 |
| Mantenimiento / auditorías | 16 |
| Tests | 20 |
| Runtime/configuración + README/guías internas | 13 |

Dentro del último grupo, `README.md`, `src/database/ORM_GUIDE.md` y `src/seo/SCHEMA_GUIDE.md` son documentación. Los cambios funcionales quedan reducidos a los archivos PHP/config indicados abajo.

## Cambios funcionales frente a main

| Área | Archivo | Cambio | Evidencia / test | Decisión |
| --- | --- | --- | --- | --- |
| Configuración multimedia | `config/defaults.php` | Elimina `media.max_upload_bytes` duplicado del config global | `ConfigurationDefaultsTest` verifica que el límite se obtiene de `resources/modules/media-library/config/media.php` y coincide con `MediaProcessor::getMaxUploadBytes()` | Conservar |
| Verificación Auth | `src/GFrame/Auth/AuthModel.php` | `validateAcount()` rechaza tokens cuya fecha ya no es válida | `AuthVerificationTokenTest` cubre token fresco y expirado, incluido que no haya escritura al expirar | Conservar |
| Flags SEO | `src/GFrame/Config/LegacyConfigBridge.php` | Sitemap y LLMS dependen de `SEO_ALLOW_INDEXING`, mientras Robots puede seguir publicándose para comunicar bloqueo | `SeoIndexabilityTest::testGlobalIndexingBlockDisablesSitemapAndLlmsButKeepsRobots` y test de debug | Conservar |
| Meta robots | `src/render/Meta.php` | El robots final se calcula por política global + `context.seo.indexable`; una meta de vista no puede sobreescribir ese contrato | `SeoIndexabilityTest` cubre bloqueo global, bloqueo de ruta y meta de vista | Conservar |
| JSON-LD global | `src/render/Meta.php` | No imprime JSON-LD cuando `SEO_ENABLED=false`; evita script vacío | `JsonLdTest::testMetaOmitsJsonLdWhenSeoIsDisabled` y `SchemaJsonLdRuntimeTest::testSeoDisabledSuppressesJsonLd` | Conservar |
| Sitemap | `src/seo/Sitemap.php` | Excluye rutas no indexables y protegidas por permission/auth/admin/role/can | `SeoIndexabilityTest::testSitemapExcludesNoindexAndProtectedRoutes` | Conservar |
| LLMS | `src/seo/Llms.php` | Aplica la misma política de exclusión de rutas no indexables/protegidas | `SeoIndexabilityTest::testLlmsExcludesNoindexAndProtectedRoutes` | Conservar |
| Robots | `src/seo/Robots.php` | Solo anuncia Sitemap cuando el endpoint está habilitado | `MetaSeoTest::testRobotsReferencesTheGeneratedSitemap` y `testRobotsDoesNotAdvertiseDisabledSitemap` | Conservar |
| Presets JSON-LD | `src/seo/SchemaComposer.php` | Resuelve presets compuestos recursivamente, detecta ciclos y deja de inventar SearchAction | `JsonLdTest` y `SchemaJsonLdRuntimeTest` cubren presets, ciclos y SearchAction explícito | Conservar |
| Preset SaaS | `src/seo/schema.presets.php` | `saas_landing` fija `SoftwareApplication` como tipo principal; se eliminan moldes autorreferenciales redundantes | Tests de presets compuestos verifican tipo y ausencia de recursión | Conservar |
| Renderer JSON-LD | `src/seo/JsonLD.php` | Conserva valores escalares válidos `0`/`false`, corrige ofertas agregadas sin precio, omite rating vacío y normaliza nodos | `JsonLdTest` + `SchemaJsonLdRuntimeTest` | Conservar |

## Configuración multimedia

La eliminación de `media.max_upload_bytes` en `config/defaults.php` no elimina el límite funcional.

La fuente activa es:

`resources/modules/media-library/config/media.php`

y `MediaProcessor::getMaxUploadBytes()` lee directamente ese archivo. La documentación de Media Library ya declara que `media.max_upload_bytes` de la antigua configuración del proyecto dejó de controlar el límite.

Mantener ambas claves habría creado dos fuentes de verdad con valores potencialmente divergentes.

## Auth

El cambio en `validateAcount()` corrige una asimetría: los tokens de recuperación ya comprobaban caducidad, mientras la verificación de cuenta aceptaba cualquier token localizado aunque hubiera expirado.

La unificada aplica `TokenManager::isValidTimestamp()` antes de activar la cuenta. El test comprueba también que un token expirado no cambie estado ni ejecute escritura.

## Política SEO canónica

La política validada queda:

1. `seo.enabled=false` desactiva la salida SEO del framework.
2. `seo.allow_indexing=false` impide Sitemap y LLMS.
3. Robots puede permanecer disponible para comunicar `Disallow: /`.
4. Una ruta con `context.seo.indexable=false` queda fuera de Sitemap/LLMS y recibe `noindex,nofollow,noarchive`.
5. Una ruta protegida no aparece en Sitemap ni LLMS.
6. Una meta de vista no puede volver indexable una ruta bloqueada ni imponer arbitrariamente noindex a una ruta que el contrato considera indexable.
7. JSON-LD depende de `SEO_ENABLED`, no de que la página sea indexable individualmente.

## JSON-LD

La reescritura de `JsonLD.php` es grande en diff porque normaliza buena parte del archivo, pero los cambios funcionales relevantes están cubiertos por tests.

Se confirmó además que campos existentes como `dateModified` siguen presentes en WebPage, Article y CreativeWork; no se perdieron durante la reescritura.

Contratos comprobados:

- SearchAction solo con target explícito que contiene `{search_term_string}`;
- producto gratuito puede emitir precio `0`;
- booleanos `false` no desaparecen;
- planes sin precio no inventan precio cero en low/high;
- aggregateRating vacío se omite;
- presets compuestos resuelven recursivamente;
- referencias circulares lanzan `LogicException`;
- `saas_landing` conserva `SoftwareApplication`.

## Cambios que no son runtime

No requieren una decisión funcional separada:

- ampliación y reorganización de `docs/`;
- traslado de auditorías y roadmap hacia `maintenance/`;
- README;
- `ORM_GUIDE.md` y `SCHEMA_GUIDE.md`;
- tests documentales y tests JS que validan contratos ya existentes.

Estos cambios sí deben revisarse editorialmente, pero no alteran por sí mismos el comportamiento de ejecución.

## Conclusión

No se detectó ningún cambio funcional de la rama unificada frente a `main` que deba revertirse antes de una eventual integración.

Los cambios de runtime son acotados, tienen una motivación verificable y cuentan con pruebas directas. La suite completa de la rama debe permanecer verde antes de cualquier merge.

Esta auditoría no autoriza ni ejecuta un merge a `main`; únicamente clasifica y valida las diferencias.
