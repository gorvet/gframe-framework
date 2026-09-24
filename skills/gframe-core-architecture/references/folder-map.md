# GFrame Folder Map

## Config Layer

- `config/routes/` for route declarations split by transport type
- `config/Permissions.php` for tenant permission templates by role
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

## Practical Rule

If a shared change alters request lifecycle, access rules, rendering, or framework conventions, work in the GFrame repository. If it is project-specific, work in the application's `app/`, `config/`, or `public/` layers.
If it alters one feature module, start in `app/`.
