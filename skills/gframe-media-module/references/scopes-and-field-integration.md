# Media Scope and Field Integration

This reference describes the existing media-library contract. Read its `module.php` and matching-version `docs/media-library.md` in the resolved GFrame package before using application examples.

## Source Ownership and Published Files

Framework originals are under `resources/modules/media-library/application/app/`; project view overrides use `app/views/media-library/` and controller overrides use `app/controllers/media-library/`. Original controllers/views are resolved at runtime, not copied into those customization folders. Core services/models remain under `src/GFrame/Media/`.

The manifest publishes inclusion wrappers from `application/components/` to `app/views/components/media/`. The wrappers resolve `mediaField.php` and `mediaPickerModal.php` through `ModuleRuntime::file`. Assets publish from `javascript/` to `public/js/modules/media-library/` and from `public/` to `public/css/modules/media-library/`. Edit the authorized source, not only its generated asset copy.

## Scope, Permission and Physical Access

| Scope | Identity | Default local storage path |
| --- | --- | --- |
| `global` | No owner ID | `public/uploads/<source>/<year>/<month>/` |
| `user` | Session `auth.id`, with legacy `userID` fallback | `public/uploads/user/<id>/<source>/<year>/<month>/` |
| `tenant` | Active session tenant through `TenantContextResolver` | `public/uploads/tenant/<id>/<source>/<year>/<month>/` |

`MediaScopeResolver` reads `media.scope`. Missing user/tenant identity or an invalid scope fails rather than falling back to global. Tenant resolution requires session context and rejects conflicting identities from the request; a submitted tenant ID does not select another library. The application must establish an authorized tenant before that request. See the shared [session and tenancy contract](../../gframe-auth-access/references/sessions-and-tenancy.md).

The existing JS supports `tenantID`/`data-ml-tenant-id` and may send `tenant_id`. Preserve those compatibility names, but do not use them as an ownership override: for tenant-scoped native endpoints, a supplied identity must agree with the active session. Check stale field attributes after an application changes tenant.

Route permissions remain separate: list/field/details/quota use `media.view`, upload/hotlink/base64 use `media.add`, save uses `media.edit`, delete uses `media.delete`, and sync uses `media.sync`; all native AJAX routes also require `auth`. Preserve CSRF handling for state-changing requests and alternate picker endpoints.

The service's optional scope defaults to global, not to configured `media.scope`. An HTTP integration resolves it and passes it to every relevant service call; a job must receive an explicitly authorized `MediaScope`. Services do not read sessions. Pass uploader identity separately for upload, hotlink and base64; never use browser-supplied authorship.

The native controller uses `ABSPATH . 'public'` as `MediaStorage` root. Authorization of listing or metadata does not authorize direct static file delivery: these paths can be served publicly. No native private-download route is declared in the module. Do not claim confidential storage or implement a new download subsystem merely because the task uses tenant scope; record that requirement separately when relevant.

## Trace a Gallery Field

1. The consumer sets `$mediaField` with its business `name`, current `value`, `multiple`, `accept`, `max`, `behavior`, `save_source` and `fragment`, then includes the published mediaField wrapper. Include the picker modal once.
2. Register the module CSS and `media-library.js`, `media-picker.js`, `media-field.js` in that order through the consuming view/group meta, with the existing jQuery, alerts and token dependencies. `media-admin.js` initializes the library screen; it is not required merely to consume a field.
3. The PHP field normalizes its initial IDs. A single field stores an ID string or empty string; a multiple field stores a JSON array of IDs. Its input `name` is the business request key, not a scope declaration.
4. `MediaPicker.open({selected, multiple, max, kind, saveSource, endpoints})` resolves `{ids, items}` or `null` on cancellation. `endpoints` overrides list/upload URLs only. The field's `data-ml-field-endpoint` overrides preview requests; all alternate handlers must resolve scope and enforce permissions server-side.
5. The field requests preview HTML with `media_ids`, `variant` and `allow_remove_one`, plus tokens. The controller accepts only `sthumb`/`gthumb`, at most 50 raw IDs, validates their format, loads each through scoped `details` and renders native/custom PHP fragments. Unknown or inaccessible IDs are omitted from the preview; successful preview rendering is not proof that every submitted ID is valid for saving.
6. The application's save handler validates the single ID or strict JSON array, checks scoped `details` for each ID and applies allowed type/content permissions. Browser `accept`, normalized IDs and a hidden input do not authorize the selection.
7. Saving the field JSON does not create `media_relations`. Use explicit `attach`/`detach`/`related` calls with the same scope when the content model requires them. Removing a field selection or detaching a relation does not delete the library file; `delete` removes its record and local files/variants.

For AJAX-inserted content, call `MediaField.bindAll(container)` for that region. Preserve existing `data-ml-*` contracts and `mediafield:change`; do not rename them to match PHP variables. The field derives its DOM ID from `name` with a `media-field-` prefix and sanitized characters: verify uniqueness when repeated forms or names normalize to the same ID. Apply the canonical [view naming rules](../../gframe-frontend-admin/references/view-form-structure.md#field-names-and-selectors).

## Configuration Boundaries

The module's `config/media.php` defines the supported per-file limit, safe extension subsets and variants. Customize a project's processor through protected `MediaProcessor::configuration()` and connect it with protected `MediaController::createProcessor()`. Ensure the project subclass is autoloaded; inject that same processor into external service integrations.

Default variants are `small` 150×150 crop and `medium` 300×300 fit. Empty variants disable generation for new files; configuration changes do not regenerate or delete old derivatives. Original bytes are preserved and small images are not enlarged. The processor's upload limit is separate from total scope quota and PHP/web-server request limits; list meta exposes the server per-file limit for pickers.

Hotlink registration stores an inspected remote URL, not a downloaded local original. Deleting that record does not delete a remote server's file. An API's existence does not imply that its controls are exposed in the picker: check actual markup/JS before claiming hotlink, quota, sync or previous/next UI support.

## Verification Limits

Existing media tests cover scope isolation, browser-path rejection, field fragments, runtime overrides, processor inheritance and service behaviors. The current JS field test checks ID normalization; it does not exercise the full picker, uploads, cancellation or multiple-field interaction in a real browser. Verify those separately when changing them and record unperformed checks.
