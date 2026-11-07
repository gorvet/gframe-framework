---
name: gframe-media-module
description: Implement and maintain GFrame media libraries, uploads, scopes, reusable selectors, media fields, and content selections in standalone framework modules or GFrame applications.
---

# GFrame Media Module

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Work from the framework module manifest and preserve the MVC boundaries already present in GFrame.

All persistent module images and documents must enter through MediaLibraryService, with resolved scope, visibility and uploader. This includes generated and imported files. Other modules retain media IDs, never a parallel file store. Temporary upload/processing files remain part of Media's ingestion flow; lower-level delivery helpers are not an alternative storage API.

Read [references/scopes-and-field-integration.md](references/scopes-and-field-integration.md) when integrating a picker/field, calling the service from PHP, or assessing storage privacy. Locate `docs/media-library.md` in the project's resolved GFrame package for the full examples; an installed skill's location is not the package root.

## Scope model

- Use `MediaScopeResolver` for application requests.
- Keep `global`, `tenant`, and `user` as the only storage scopes.
- Each content CRUD stores its own single media ID or ordered JSON array of gallery IDs. Do not add generic relation tables or a scope per content type.
- A tenant or user scope must fail when its identity cannot be resolved.
- Scope identifies the library owner; it does not check tenant membership or replace route/business permissions. Tenant scope requires the active session identity and rejects contradictory request/session identities through `TenantContextResolver`.
- PHP service calls that omit their optional scope default to `global`, regardless of `media.scope`. Resolve and pass it explicitly in application integrations; background jobs use an explicitly authorized scope rather than a browser session.

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

`media.quota_bytes` controls total scoped storage; zero disables that quota. It is separate from the processor's per-file limit. Visibility is independent of global/user/tenant ownership. Existing files remain public. `media.visibility` and `media.sources.<source>.visibility` select public/uploads or private/media (configurable private_root). Read the matching package's docs/archivos-privados.md for protected delivery; never infer confidentiality from a scope or path name alone.

The native controller injects MediaAccessPolicy; private actions require view plus their action permission, with configurable source permission prefixes. Apply authorization filters before SQL pagination and filter counts. PHP services without that policy trust their caller: authorize actor, scope and resource explicitly. Private originals/variants use authenticated GET/HEAD admin/media-file, never direct assets; viewItem removes physical paths. Native uploads choose visibility from server configuration. Private hotlinks are rejected. changeVisibility moves originals/variants; check its result and never promise recall of downloaded copies or external caches. Native sync stays public; private sync is an explicit authorized PHP call.

PrivateDownloadService and PrivateFileStorage are lower-level delivery utilities, not a parallel way to persist images/documents outside Media. Grant issuance/revocation are trusted server APIs; never accept paths or actor IDs from the browser. Links default to disabled and support per-grant expiry, revocation, optional identity/context checks and atomic GET-request limits; only token-secret hashes are stored. Media issueDownload binds scope/media/path; integrations own current entitlement callbacks. HEAD/invalid ranges do not consume uses; interrupted/range GETs can. Do not describe this as completed-download accounting, automatic retention or non-transferable bearer links. Preserve server protection, shared SQL grant storage and no-store delivery. Check PrivateDownloadTest, MediaPrivateTest, MySqlMigrationIntegrationTest and real HTTP/server coverage for changes here.
Processing defaults belong to `resources/modules/media-library/config/media.php`, not project `config/app.php`. Override protected `MediaProcessor::configuration()` in a project subclass and connect it through protected `MediaController::createProcessor()`. Pass the same subclass to external PHP services. Defaults are small 150x150 crop, medium 300x300 fit and optimized WebP 1920x1920 fit at quality 80, without upscaling. Preserve original bytes; retain optimized only when smaller. Skip WebP conversion for GIF and when GD lacks WebP. The controller's media_url prefers optimized, original_url retains the original, and private variants require their actual MIME. Original and optimized weights are separate metadata; size_bytes still accounts for all unique files for quota. Empty variants disable generation. Do not regenerate/delete existing derivatives on configuration changes. Existing stored URLs are not rewritten. Expose the server upload limit through list meta for reusable pickers. Safe extension configuration restricts the supported formats and cannot enable executables.
Controllers pass the session uploader explicitly to upload/hotlink/base64 services; persist it in metadata.uploader (id and name). Services do not read sessions. Never invent authors for old files or accept browser-supplied authors. Search includes alt_text (Título descriptivo) as well as filenames. Verify standalone picker uploads and both single/multiple field flows outside the library page.

Existing images expose Optimizar in library/picker details only when can_optimize is true. The protected optimize action requires media.edit, CSRF and resolved scope; private source edit permission also applies. MediaLibraryService::optimize preserves ID, original, metadata, uploader, existing thumbnails and visibility, adds only optimized plus missing aliases, and updates stored-byte accounting. Use the optional MediaOptimizationRepository CAS capability to reject concurrent path/visibility/metadata/variant changes; preserve compatibility with older MediaRepository implementations by disabling optimization for those adapters. Temporary derivatives have random names and are removed on quota rejection, conflict or persistence failure. Repeated requests retain an existing registered variant. No automatic batch conversion or rewrite of saved content URLs.

## Verification

Writing integration is opt-in (`richTextEditor['media_library']`), using the optional editor/media meta and one picker modal outside the form. The PHP field fragments `editor_image` and `editor_gallery` require an exact scoped image selection, never partial results or browser URLs. They are not thumbnail variants. Ordered figures and bounded image IDs survive the HTML sanitizer; callers still authorize content and media IDs/URLs when saving. Preserve cursor, undo/redo, teardown guards, and plain editors without media dependencies. Check MediaWritingTest, HtmlSanitizerTest and media-writing.browser.test.cjs with real TinyMCE; a mocked transport is not proof of live HTTP or middleware.

Administration offers grid/list via the same scoped PHP list action; `_table.php` is resolved through ModuleRuntime. Preserve default newest-ID order and the allowlisted sort keys (`newest`, `oldest`, `name_asc`, `name_desc`, `size_asc`, `size_desc`), stable ID tie-breaks, URL state and page clamps. The picker remains a grid. Detail navigation crosses filtered pages, guards unsaved changes/in-flight saves and ignores stale detail replies. Original_name is a display name, never a physical rename. Native audio/video previews must have controls, no autoplay, and release their source on change/close. Opening a public/remote file URL does not implement private or proxied downloads. Check `MediaManagementTest` and `media-management.browser.test.cjs` when touching these behaviors.

The reusable picker displays attributes and a server-rendered ordered selection. Preselection must load its details on open, including items absent from the visible page; hydrate the selected item from that scoped response. Both media dialogs use the available viewport with a stable height, contained previews, scrollable content and persistent footer actions. Render separate labeled metadata with readable sizes; normal save/error feedback uses alertToast, not an embedded success paragraph. Keep thumbnail widths uniform with long names. Upload progress belongs to the upload tab. Upload batches select only successful compatible files, in input order; respect maximums and ignore callbacks from closed sessions. Editing existing original_name/alt_text uses details/save routes with their existing permissions and CSRF. The internal field fragment `selection` renders the tray and retains scope/50-ID guards; it is not a MediaField thumbnail variant. `replaceSelection=true` in picker results means an explicit composition removal/reorder and overrides append for that result only. Preserve append for other callers. Do not introduce nested forms or named metadata inputs that pollute a consuming form; disable the hidden required metadata controls outside editing. Keep unsaved-change and in-flight-save handling, keyboard controls and close/reopen tests.

For runtime module changes, run the relevant media PHP/JS tests and integration checks, then the framework checks appropriate to the changed surface. Composer metadata/dependency changes require `composer validate --strict`/`composer audit`; a requested release requires the full repository checks. For skill-only edits, validate the skill, its portable references and source claims, plus `git diff --check`; do not claim browser behavior from structural validation.

Existing focused checks include `MediaScopeResolverTest`, `MediaLibraryTest`, `MediaRuntimeTest`, `MediaDocumentationTest` and `tests/js/media-field.test.cjs`. Check real single/multiple fields, standalone picker uploads, cancellation, replaced DOM and permissions in a browser when those behaviors change.
