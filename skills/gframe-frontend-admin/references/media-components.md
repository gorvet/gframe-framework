# Media Components Stack

Read this only when an admin screen consumes the reusable media module.

For the media subsystem itself, use `gframe-media-module`.

## What Frontend Admin Needs To Know

- `MediaLibrary` owns list, filter, search, upload, and pagination UI.
- Native list fragments separate library/picker IDs (picker uses mp-), filter/action data attributes and instance-owned data-ml-pagination controls. Preserve these in overrides; read data-media-id instead of parsing DOM IDs. Older package versions may duplicate IDs when both mounts coexist. Include one picker modal per page.
- `MediaPicker` opens the library in a modal and resolves selected items.
- `MediaField` bridges form fields to picker results and requests backend-rendered thumb fragments.

## Frontend Contract

Common data attributes used by the UI:

- `data-ml-mount`
- `data-ml-kind`
- `data-ml-save-source`
- `data-ml-media-field`
- `data-ml-multiple`
- `data-ml-max`

## Integration Rule

- Media administration uses the same PHP listing for grid/list and allowlisted ordering with URL state. Preserve the picker grid. Details provide display name/alt, guarded previous/next navigation across filtered pages and native audio/video controls; release media sources on close/change. New list HTML and copy remain in PHP, not JavaScript templates. Keep the scrollable modal body and persistent footer actions on narrow screens.

- Picker results add `replaceSelection` for explicit removal/reordering of the complete selection; preserve ordinary append semantics otherwise. The ordered tray is backend HTML from the scoped field endpoint with internal variant `selection`.
- Metadata fields in the picker use their own data attributes, not a nested form or names that leak into a consuming FormData. Do not enable hidden required controls after the picker closes. Keep permissions and CSRF on details/save and protect unsaved changes.

- keep thumb rendering in backend fragments
- reload the owned media region from backend HTML after upload or library actions
- do not embed media thumb templates directly in JS
- load `media-library.js`, `media-picker.js`, then `media-field.js`; include the picker modal once and preserve the global `#tokens` form
- preserve the original Base Confías fragments; backend URLs and scope filtering must remain authoritative
