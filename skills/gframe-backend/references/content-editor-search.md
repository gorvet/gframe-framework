# Content Conversion, Editors and Authorized Search

For site preview install optional content-preview, reuse the authorized content-autosave resource/base revision, and call ContentPreviewController::read before rendering the project's real public template. Both page and state routes authenticate and authorize the exact record/tenant on every read. The helper requires active session, sets private/no-store/noindex, selects only a compatible private copy and never publishes. State omits HTML. Public preview assets/meta perform same-origin version polling; configure rich-text-editor preview_url for its replacement of the default TinyMCE preview. No admin bar is included. See docs/content-preview.md in the effective package.

For protected final saves, explicitly adopt `GFrame\Content\VersionedContentModel` in the project's model; preserve the revision opened by the user and the server-authorized scope. A private draft version is not a published-content revision. Handle `content_conflict` without replacing text or confirming snapshots. The optional `content-editing` module supplies presence only: delegate to ContentPresenceController after auth/can/CSRF and authorization of the specific record/tenant. It chooses no business tables or permissions. See `docs/content-editing.md` in the effective package.

Read only for Markdown, rich HTML editing or lexical search. Locate `docs/{markdown,rich-text-editor,lexical-search,html-sanitizer}.md` in the effective package for complete API/options. These modules do not create article storage or authorize business records.

## Choose the Input Contract

| Input | Existing capability | Boundary to preserve |
| --- | --- | --- |
| Plain text | `SanitizeHelper::sanitize` plus output escaping | Text cleanup is not permission, length or email validation. |
| Markdown | Global final/static `MarkdownHelper` | Choose chat or channel explicitly; PHP and JS support different syntax. Conversion is not a sanitizer for arbitrary input HTML. |
| Rich HTML | Core `GFrame\Security\HtmlSanitizer::sanitize` | Explicit backend call; DOM required. Editor filtering does not protect requests that bypass the browser. |
| Search query | `GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine::rank` | Rank an already authorized, bounded dataset. No automatic ORM query or permission filtering. |

For a framework module change, edit the sources declared by its manifest. For application work, use project controllers/services/views and supported overrides; do not patch the installed package.

## Markdown Display

Install/publish the markdown module only when the project task calls for it. Composer autoloads `MarkdownHelper`; no subclass or facade is needed.

```php
$contentHtml = \MarkdownHelper::chatMarkdownToHtml("## Guide\n\n**Start** here.");
```

Print generated HTML in a body-content context. Escape plain text with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`; do not double-escape generated markup. If storing/rendering arbitrary rich HTML, use the separate sanitizer contract instead.

The channel profile interprets `*text*` as bold; chat uses it as emphasis. Browser `markdown2Html` supports basic channel inline formatting, not the complete PHP chat profile. `html2Markdown` returns text that may retain unknown tags: display through textContent, never assume safe innerHTML. Both conversions can lose formatting.

Assets publish to `public/vendors/internal/markdown/markdown.js`; load through view meta only when needed. Validate any project-specific remote-image/domain policy separately.

## Rich-Text Form Lifecycle

Optional content-autosave installs a private content_autosaves table (MySQL/SQLite). The application must authorize its business record in an auth/can/CSRF-protected route, then delegate to ContentDraftController::handle(serverResourceKey, currentPublicRevision). Never take that key, owner or current revision from POST. ContentDraftService scopes by trusted user/tenant/resource, sanitizes HTML and compares opaque draft versions atomically using affected count. Discard writes a versioned tombstone. PHP autosave option supplies same-origin URL/base_version/interval; client serializes requests, offers recovery explicitly and preserves edits/local fallback on failure. Private autosave is not publication and does not call markSaved. A confirmed business save must call markSaved(snapshot, newPublicRevision) to serialize private cleanup without deleting newer work; missing revision pauses autosave. See docs/content-autosave.md for integration, expiry and retention boundaries.

The PHP field enables beforeunload protection by default through protect_changes, comparing canonical content to a confirmed baseline independently of dirty/triggerSave. Optional draft_key must distinguish authorized actor/tenant/document/field; never infer identity from URL. Local drafts use per-instance localStorage entries, seven-day expiry, bounded size and explicit recover/undo or confirmed discard. Expose storage failures, preserve typing and never automatically restore HTML. Capture getSaveSnapshot(id) before sending its content, then markSaved(theSameSnapshot) only on business success. Stale instances/older acknowledgements must not clear newer edits. Backend authorization/sanitization still apply. Document browser storage/privacy limits and application logout policy. Never equate submit, setDirty(false) or local persistence with server success. Fullscreen and gframeOutline are native controls; outline navigation must not persist generated IDs.

Text selection uses the native contextual formatting toolbar. The PHP-rendered writing labels feed a bounded native slash menu at paragraph start (paragraph, h2/h3, lists, quote, separator); do not interpret typed text as command names or replace HTML persistence with a block schema. Keep Escape non-destructive, undoable actions, read-only guards and per-instance setup composition. Word count uses the bundled native plugin, independent of media-library.

The native toolbar groups secondary commands under gframeMore (floating mode); an explicit non-floating mode expands that owned group inline. Media-enabled editors offer scoped image/gallery context actions; removing from content is undoable and never deletes library files. Optional PHP save_button names an existing action button ID in the same form: gframeSave and meta+s synchronize then click that button, retaining native validation and app AJAX/submit semantics. Guard disabled/read-only/foreign targets and pending media; do not invent persistence or clear dirty before confirmed success. The field's native submit synchronization preserves dirty; application calls remain application-owned. Disconnect form observers on teardown and keep custom setup/toolbars functional.

The native editor has a stable initial height equal to the configured PHP min_height (580 by default), internal content scrolling and no default autoresize plugin. Preserve overscroll containment in a custom content_style when required. Per-instance height/min_height and explicit autoresize remain project choices; do not make long content expand the surrounding form by default. Verify long documents, wheel/keyboard boundaries and access to surrounding form actions in a real browser.

The optional `media_library` PHP field option connects the existing picker through `richTextEditorMedia.meta.php`; install media-library separately and include its modal once outside the form. PHP field fragments `editor_image`/`editor_gallery` reject partial, wrong-kind or wrong-scope selections. Preserve figure[data-media-gallery="1"] and img[data-media-id] through the supported sanitizer. These bounded markers are not authorization: the receiving application must authorize IDs and compare source URLs. See docs/rich-text-media.md in the effective package. Plain editors remain independent of the media module.

Resolve `rich-text-editor/richTextEditor.php` and its meta via `ModuleRuntime::file('views', ..., 'rich-text-editor')`. Originals live under `resources/modules/rich-text-editor/application/app/views`; JS publishes to `public/js/app/admin/components/rich-text-editor.js`. Its dependencies are jQuery and TinyMCE; do not add a second editor instance/library.

Use a unique field id and the established name. Register per-instance options before init. Before validation and AJAX serialization/FormData, call `window.AdminRichTextEditor.saveAll()`. Required textarea validity is not semantic content validation; define whether text, images or other allowed content counts and display errors beside the editor.

For replaced AJAX fragments: save content if it must survive, then destroyAll(oldContainer), replace markup and initAll(newContainer). Containers must be DOM elements; initAll searches descendant textareas, while init(textarea) handles an individual field. destroyAll does not save automatically. Missing TinyMCE produces no automatic retry.

At the authorized controller/service boundary, limit input size and apply the existing HTML sanitizer before persistence:

```php
$safeHtml = \GFrame\Security\HtmlSanitizer::sanitize($untrustedHtml);
```

`$untrustedHtml` is the received string after the project's size/access checks, not a trusted editor output. Validate required content after sanitizing as well. Server/editor allowlists may differ; test save/reload. The sanitizer is final with no configurable presets, removes classes/IDs/styles, and permits some remote URLs without a host allowlist. It does not authorize images or provide an upload endpoint. Connect the existing media specialist only when selecting/uploading media is part of the task.

Render the persisted sanitized fragment in the body context; attributes, JS and CSS require their own escaping. A DOM failure is a handled operation failure at an appropriate boundary, never a reason to retain dangerous original HTML.

## Authorized Lexical Search

The snippet runs in a bootstrapped project where `lexical-search` is installed and registered with ModuleRuntime. Composer autoload alone in a standalone diagnostic does not register this native MVC service. In an isolated test, initialize ModuleRuntime with a temporary project root and the selected module as LexicalSearchTest does; do not boot a real application merely to test ranking.

Load permitted rows through the model before ranking. Never send private rows to the browser and then filter them with JS. Preserve publication and user/tenant constraints in the original query; an empty authorized selection must not become an unrestricted ORM IN query.

```php
$engine = new \GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine();
$results = trim($query) === '' ? $authorizedRows : $engine->rank(
    $authorizedRows, $query, ['title' => 2, 'body' => 1],
    ['snippet_fields' => ['body']]
);
```

The caller defines and bounds `$authorizedRows` and `$query`; field names/options are code-owned. Empty-query behavior above is an application choice. Rank before pagination for relevance across this dataset. Pagination before rank searches that page only; use a suitable indexed backend for large datasets.

After ranking, compose list metadata and slice this result, rather than running ORM pagination on another unsorted query. Here `$requestedPage` is normalized by the controller and `$perPage` is a positive, bounded project setting:

```php
$totalItems = count($results);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = max(1, min($requestedPage, $totalPages));
$rows = array_slice($results, ($page - 1) * $perPage, $perPage);
$listing = ['data' => $rows, 'meta' => [
    'page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalItems,
]];
```

An empty result still has no rows; the UI hides pagination for total_pages <= 1. Backend owns the operation envelope and frontend contract; this helper array is not a complete HTTP response. For rendered controls, use the matching-package admin pagination recipe.

Results retain fields and add `_search_score` plus optional plain-text snippet. Escape snippet/title before HTML output; score is not a percentage and tied order is not guaranteed. A boost can include a nonmatching row. The engine owns no database index or persistent state.

The native service is extensible, but declaring a project subclass does not replace explicit native construction: inject/instantiate the intended subclass. Browser JS publishes to `public/vendors/internal/lexical-search/lexical-search.js` and must be loaded in meta only when used.

## Verification

Existing `MarkdownTest`, `HtmlSanitizerTest`, `RichTextEditorTest` and `LexicalSearchTest`, plus the matching JS tests, verify portions of these contracts. For a changed flow, check unsafe links/events, Unicode, empty/markup-only content, missing assets, dynamic editor teardown/init, round-trip persistence, empty authorization and search pagination. Source tests or simulated DOM do not prove browser focus, TinyMCE interaction or full HTTP authorization.
