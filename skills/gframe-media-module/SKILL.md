---
name: gframe-media-module
description: Implement and maintain GFrame media libraries, uploads, scopes, reusable selectors, media fields, and content relations in standalone framework modules or GFrame applications.
---

# GFrame Media Module

Work from the framework module manifest and preserve the MVC boundaries already present in GFrame.

## Scope model

- Use `MediaScopeResolver` for application requests.
- Keep `global`, `tenant`, and `user` as the only storage scopes.
- Store content ownership in `media_relations`; do not invent a scope per content type.
- A tenant or user scope must fail when its identity cannot be resolved.

## Module contract

A complete reusable media module includes:

- MySQL and SQLite schemas;
- `MediaModel`, `MediaLibraryService`, storage and processor classes;
- an authenticated controller and routes for listing, upload and deletion;
- the library view and its public resources;
- reusable `mediaField` and `mediaPicker` components;
- `media-library.js`, `media-picker.js` y `media-field.js`, en ese orden, declarados en el meta de cada vista que consume los componentes;
- fragmentos HTML del servidor para listado y vistas previas, con variantes `sthumb` y `gthumb` permitidas expresamente;
- publication tests for application files and resources.

Use the project's standard alerts, pagination, route middleware and CSRF fields. Keep visual styling in the module stylesheet and use Bootstrap for layout and interaction.
Preserve the original Base Confías views, fragments, CSS and JS. Copy complete source files physically before targeted changes; never reconstruct their markup. Native MVC files belong in `application/app/{controllers,models,services,views}/media-library`; routes declare `module('media-library')` and installation creates empty app customization directories. Preserve existing `admin/media` and `ajax/admin/media/*` URLs. Custom controllers extend the native non-final controller; do not add callbacks or factories for customization.
Picker endpoints configure list/upload URLs, not storage ownership or view names. Resolve global/user/tenant scope on the server for every operation. Preview requests contain IDs, never trusted browser paths. Return absolute asset URLs from the backend. Do not claim quotas, synchronization or previous/next controls are visible merely because their APIs exist.
El campo múltiple guarda un arreglo JSON de IDs; el campo simple guarda un solo ID. El ámbito se resuelve en el servidor desde la sesión, no desde un `tenant_id` proporcionado por el cliente. No se deben marcar las capacidades de hotlink ni los controles avanzados del modal como extraídas hasta que tengan implementación y pruebas propias.

## Configuration

Read the selected scope from `media.scope`. The generated application config defaults to `global` outside multitenancy and `tenant` for SaaS installations. Do not add project-specific table names or business rules to the framework module.
Processing defaults belong to `resources/modules/media-library/config/media.php`, not project `config/app.php`. Override protected `MediaProcessor::configuration()` in a project subclass and connect it through protected `MediaController::createProcessor()`. Pass the same subclass to external PHP services. Default variants are small 150x150 crop and medium 300x300 fit; preserve original bytes, no automatic optimized copy or upscaling. Empty variants disable generation. Do not regenerate/delete existing derivatives on configuration changes. Expose the server upload limit through list meta for reusable pickers. Safe extension configuration restricts the supported formats and cannot enable executables.
Controllers pass the session uploader explicitly to upload/hotlink/base64 services; persist it in metadata.uploader (id and name). Services do not read sessions. Never invent authors for old files or accept browser-supplied authors. Search includes alt_text (Título descriptivo) as well as filenames. Verify standalone picker uploads and both single/multiple field flows outside the library page.

## Verification

Run `composer check`, `composer validate --strict`, `composer audit` and `git diff --check` after changing the framework module.
