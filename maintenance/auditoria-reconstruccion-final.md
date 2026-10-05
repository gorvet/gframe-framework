# Estado canónico de la auditoría y reconstrucción

Fecha de cierre técnico: 2026-10-05.

Este documento representa el estado vigente de la rama `codex/auditoria-reconstruccion`. Los demás archivos de `maintenance/` conservan historia, planes y listas de trabajo y pueden contener nombres, limitaciones o pendientes que ya no representan el código actual.

La fuente de verdad de la auditoría es el código de la rama. La documentación se reconstruyó después de contrastar los contratos reales del framework; no se asumió que la documentación previa fuera correcta por existir.

## Objetivo alcanzado

La documentación deja de depender de una lectura componente por componente como único camino de aprendizaje. Se separaron dos capas:

1. **Guía de desarrollo orientada a tareas**, con el recorrido `URL → Route → Middleware → Controller → Service → Model/ORM → View → Template → Response`, una primera funcionalidad completa y guías para AJAX, seguridad y capacidades comunes.
2. **Referencia técnica**, que conserva el detalle de routing, render, ORM, módulos, contratos, helpers, perfiles e integraciones sin obligar a leerlo antes de construir una funcionalidad.

El inventario de capacidades se contrastó con el árbol real del framework y de `resources/modules`. Las guías técnicas existentes que ya eran correctas se conservaron o reorganizaron; no se reescribieron únicamente para producir documentación nueva.

## Correcciones de runtime derivadas de la auditoría

La auditoría encontró inconsistencias demostrables que no podían resolverse solo cambiando texto. Se corrigieron con cambios mínimos y cobertura de regresión.

### SEO

- `Robots` ya no anuncia `/sitemap.xml` cuando el Sitemap está desactivado.
- `seo.allow_indexing=false` desactiva Sitemap y LLMS desde la política global, mientras Robots puede seguir publicado para comunicar `Disallow: /`.
- `context.seo.indexable=false` excluye una ruta de Sitemap y LLMS y fuerza `noindex,nofollow,noarchive` en su meta robots.
- Una meta de vista no puede volver a habilitar indexación bloqueada globalmente o por la ruta.
- Sitemap y LLMS excluyen también rutas protegidas por `permission`, `auth`, `admin`, `role:*` y `can:*`.
- Los contratos legacy `context.sitemap.include` y `context.llms.include` se mantienen para compatibilidad, pero el contrato recomendado es `context.seo.indexable`.
- `SEO_ENABLED=false` impide emitir JSON-LD.

Archivos principales: `src/GFrame/Config/LegacyConfigBridge.php`, `src/render/Meta.php`, `src/seo/Robots.php`, `src/seo/Sitemap.php`, `src/seo/Llms.php`.

### JSON-LD

- Los presets compuestos se resuelven de forma recursiva.
- Las referencias circulares entre presets se detectan y lanzan `LogicException` con la cadena implicada.
- Se eliminaron alias recursivos inválidos del catálogo de presets.
- `saas_landing` conserva `SoftwareApplication` como tipo principal al combinar software y FAQ.
- No se inventa una ruta `/buscar`; `SearchAction` solo aparece cuando existe un `search.target` explícito con `{search_term_string}`.
- El filtrado conserva escalares válidos como `0` y `false`.
- Un producto gratuito puede emitir `Offer` con `price=0`.
- Los planes sin precio no se convierten en precio cero al calcular agregados.
- Un `aggregateRating` vacío no genera un nodo incompleto.

Archivos principales: `src/seo/SchemaComposer.php`, `src/seo/schema.presets.php`, `src/seo/JsonLD.php`, `src/render/Meta.php`.

### Autenticación

`AuthModel::validateAcount()` comprueba ahora `token_updated_at` mediante `TokenManager::isValidTimestamp()`, igual que el restablecimiento de contraseña. Un enlace de verificación vencido devuelve `invalid_token` y no activa la cuenta. Se conserva el nombre público existente `validateAcount()` por compatibilidad.

Archivo principal: `src/GFrame/Auth/AuthModel.php`.

### Media

Se retiró `config/defaults.php['media']['max_upload_bytes']` porque no participaba en el runtime. El límite real sigue perteneciendo al módulo Media Library y se obtiene de `resources/modules/media-library/config/media.php`; el valor predeterminado de 25 MB no cambió.

## Cobertura añadida o ampliada

- `tests/MetaSeoTest.php`: Robots no anuncia un Sitemap desactivado.
- `tests/AuthVerificationTokenTest.php`: token de verificación vigente frente a token vencido.
- `tests/JsonLdTest.php`: presets compuestos, ciclos, SearchAction explícito, `0`, `false`, rating vacío y política `SEO_ENABLED`.
- `tests/SeoIndexabilityTest.php`: política global de indexación, debug, exclusión de rutas en Sitemap/LLMS y precedencia del noindex global/de ruta.

La suite existente `ModuleCatalogTest` ya valida manifiestos, dependencias, existencia de assets, destinos de publicación y publicación efectiva de módulos `external-ui`.

## Dependencias frontend: frontera de verificación

El repositorio puede demostrar automáticamente que los assets declarados existen, que sus dependencias se resuelven y que su publicación genera los destinos esperados. `docs/dependencias-frontend.md` distingue ahora la versión declarada por el manifiesto de la identificada en una distribución vendorizada.

La licencia es metadato curado a partir de avisos distribuidos y proyecto de origen; no es un campo validado por `ModuleCatalogTest`. El comportamiento visual real de una biblioteca externa en navegador, móvil, tema y combinaciones de módulos sigue siendo una validación de integración, no una garantía derivable del manifiesto.

## Nomenclatura histórica

La API asíncrona actual es `Async`. Las apariciones de `PHPAsync` dentro de snapshots o planes históricos de `maintenance/` corresponden a nomenclatura anterior y no deben interpretarse como una clase o tarea vigente.

Del mismo modo, una casilla sin marcar en un documento histórico no constituye por sí sola un bug abierto. Antes de retomarla debe contrastarse con el código y con la documentación vigente.

## Elementos que no forman parte de este cierre

No se implementaron features futuras solo por aparecer en `maintenance/`. Entre ellas pueden existir propuestas como nuevas operaciones de administración de usuarios, avisos administrativos, enlaces adicionales de campañas o cambios de BridgeFrame. Esas ideas siguen siendo roadmap hasta que se decida trabajarlas explícitamente.

Tampoco se pretende certificar mediante PHPUnit lo que requiere un entorno externo real: render visual en navegadores, comportamiento de Nginx/Apache, SMTP de un proveedor, MySQL/Redis concretos o compatibilidad visual de una librería de terceros. Las pruebas automatizadas validan los contratos reproducibles del framework.

## Archivos a vigilar al unificar con otra rama

Los siguientes cambios de runtime no deben perderse durante una futura reconciliación:

- `config/defaults.php`
- `src/GFrame/Auth/AuthModel.php`
- `src/GFrame/Config/LegacyConfigBridge.php`
- `src/render/Meta.php`
- `src/seo/JsonLD.php`
- `src/seo/Llms.php`
- `src/seo/Robots.php`
- `src/seo/SchemaComposer.php`
- `src/seo/Sitemap.php`
- `src/seo/schema.presets.php`

Cobertura asociada:

- `tests/AuthVerificationTokenTest.php`
- `tests/JsonLdTest.php`
- `tests/MetaSeoTest.php`
- `tests/SeoIndexabilityTest.php`

Las guías `docs/autenticacion.md`, `docs/json-ld.md`, `docs/seo.md`, `docs/media-library.md` y `docs/dependencias-frontend.md` describen los contratos resultantes y deben reconciliarse junto al código, no por separado.

## Criterio de cierre

La rama se considera cerrada cuando:

1. el barrido final no encuentre contratos obsoletos presentados como vigentes;
2. la documentación de las áreas modificadas coincida con el runtime;
3. el HEAD final pase el workflow de CI;
4. la comparación contra `main` quede registrada antes de iniciar cualquier unificación con otra rama.

El SHA y el resultado del CI final se incorporarán aquí en el último paso del cierre.
