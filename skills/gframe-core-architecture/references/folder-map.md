# GFrame Folder Map

## Config Layer

- `config/routes/` for route declarations split by transport type
- `config/Permissions.php` for initial and updateable role permission templates
- `config/meta/` for reusable meta schema helpers and examples

## Framework Repository

- `src/routing/` for RouteBuilder and Router
- `src/render/` for Render and Meta
- `src/middleware/` for Middleware and permission data access
- `src/database/` for ORM, connections, and dialects
- `src/GFrame/` for namespaced modules such as configuration, authentication, notifications, security, and text classification
- `src/async/`, `src/cron/`, `src/heartbeat/`, `src/seo/`, `src/services/`, and `src/utils/` for reusable runtime capabilities
- `config/defaults.php` for internal framework defaults
- `tests/` for framework behavior

## App Layer

- `app/controllers/` for HTTP controller classes
- `app/models/` for ORM models
- `app/services/` for reusable subsystem logic
- `app/views/` for templates, views, partials, and meta files

## Public Layer

- `public/js/core/` for shared JS helpers
- `public/js/core/utils/` for shared JS utilities
- `public/js/app/admin/` for admin module JS
- `public/css/app/admin/` for admin module CSS

## Optional Modules

- `resources/modules/<module>/module.php` for the module manifest and dependency declarations
- `resources/modules/<module>/public/` for publishable browser assets
- `resources/modules/<module>/application/` for optional application files consumed by the installer
- `resources/modules/<module>/application/app/` for runtime MVC originals when declared by the manifest; project overrides use the corresponding `app/<layer>/<module>/` path
- `GFrame\Modules\ModuleCatalog` for discovery and dependency resolution
- `GFrame\Modules\ModuleAssetPublisher` for safe publication into the application's `public/` directory

External browser libraries publish below `public/vendors/external/`. GFrame-owned browser components publish below `public/vendors/internal/` or the documented shared JS destination. Do not copy optional modules manually when the catalog can resolve and publish them.

Asset source names and targets belong to each manifest; `public/js/app/admin` is an application convention, not the universal destination. Native module assets can publish to `public/js/modules/<module>` and `public/css/modules/<module>`. Inclusion wrappers and routes may be published while MVC originals remain in the package. The installed module registry is `storage/gframe-installed.json`, distinct from Composer's package version.

## Practical Rule

If a shared change alters request lifecycle, access rules, rendering, or framework conventions, work in the GFrame repository. If it is project-specific, work in the application's `app/`, `config/`, or `public/` layers.
For one application feature, start in its project layer or supported override. For an authorized distributable module change, start in its framework originals. Locate the actual package and startup configuration using the [bootstrap/version reference](bootstrap-configuration-version.md).
