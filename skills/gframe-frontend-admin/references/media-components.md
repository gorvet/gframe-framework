# Media Components Stack

Read this only when an admin screen consumes the reusable media module.

For the media subsystem itself, use `gframe-media-module`.

## What Frontend Admin Needs To Know

- `MediaLibrary` owns list, filter, search, upload, and pagination UI.
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
