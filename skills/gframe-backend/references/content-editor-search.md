# Content Conversion, Editors and Authorized Search

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
