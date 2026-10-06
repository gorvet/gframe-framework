# Component Integration and Readiness

Use only for the component changed by the task. Resolve `resources/modules/<module>/module.php`, the matching package's component guide and the actual template meta before editing. Installation/publication does not imply loading or initializing every component.

## Existing Component Boundaries

| Component | Contract to preserve |
| --- | --- |
| frontend-core | Publishes utils.js and helpers/errors/forms/pagination under public/js/core; does not include all visual modules. |
| alerts | JS/CSS feedback, not notifications inbox. Load its declared dependencies and keep one toastBox; existing AJAX feedback reference owns presentation conventions. |
| gf-select | Native select retains name/value/validation. GFSelect is explicit, supports getInstance/refresh/destroy, and reuses an existing instance for the same element. |
| gf-table | jQuery local filtering/sorting, not ORM pagination or authorization. API has sort/filter/reset, no destroy. |
| gframe-icons | CSS/font files, no JS. Preserve relative fonts and use existing gicon classes with accessible button names. |
| admin-panel/error-pages | Runtime originals and project overrides; resolve template/view ownership rather than requiring copied app files. |

## Wait for Shared Utilities

`public/js/core/utils.js` loads its files asynchronously in sequence. Positioning the view script after it in meta does not guarantee the internal helpers are ready. Use the existing readiness contract for code that depends on them:

```js
if (window.__gfUtilsReady) {
    initializeView();
} else {
    document.addEventListener('gfutilsready', initializeView, {once: true});
}
```

`initializeView` is the application's existing initialization routine, not another framework global. Handle `gfutilserror`/`__gfUtilsErrors` when loading failure affects the flow; do not keep sending actions whose required helpers are missing. If loading utility files directly, inspect that actual order instead of assuming the loader's readiness flag applies.

## Select and Table Lifecycles

GFSelect publishes canonical JS/CSS under `public/vendors/internal/gf-select/`; `gfselect` remains a catalog/path compatibility alias. Do not load both copies. The class name remains GFSelect. It can load its CSS asynchronously; use onReady before work needing the wrapper, or load CSS through meta with loadStyles:false. Preserve native multiple/name fields and project limits. Refresh after relevant options change; destroy before removing an owned instance and initialize only the new select after replacement. Do not replace native validation with UI hiding.

GF Table publishes `public/vendors/internal/gf-table/gf-table.js` and has no separate stylesheet. Sorting operates on loaded visible rows; sort(index) indexes sortable headers, not every column. Auto-init observes inserted tables. Replacing tbody rows retains the instance: reapply filter if needed. Replace the whole table/container when changing captured headers or search fields; do not call an invented destroy API. This is local presentation of already authorized rows.

## External Libraries

For any of the sixteen external-ui modules, read the actual manifest and corresponding `docs/<library>.md` plus `docs/paquetes-visuales.md` in the effective package. Use its distributed API/version and preserve originals; shared tokens/theme and module bridges own the visual integration. Register original CSS before its bridge and view-specific styles after it. Add only the library needed by the task; a module dependency does not authorize a vendor upgrade, CDN replacement or another UI system.

Check asset paths, dependency order, initialization, repeated initialization, dynamic replacement, keyboard/focus and theme where affected. Existing gfselect, gf-table and alerts JS tests cover parts of their APIs using simulated environments; they do not certify the rendered page or all sixteen vendors. Never use hidden rows as access control.
