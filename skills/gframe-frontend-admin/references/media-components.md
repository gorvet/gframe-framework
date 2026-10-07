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

- keep thumb rendering in backend fragments
- reload the owned media region from backend HTML after upload or library actions
- do not embed media thumb templates directly in JS
- load `media-library.js`, `media-picker.js`, then `media-field.js`; include the picker modal once and preserve the global `#tokens` form
- preserve the original Base Confías fragments; backend URLs and scope filtering must remain authoritative
