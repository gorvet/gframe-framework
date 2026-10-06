# Public Templates and Assets

## Template Rule

Public pages render through public templates such as:

- `homeTemplate.php`
- `slugTemplate.php`
- other non-admin templates in `app/views/templates/`

These are examples, not a requirement that every project ship both home and slug templates. The current skeleton supplies `homeTemplate.php`; Render resolves the selected template through `ModuleRuntime::template`, then uses the application's shared `header.php` and `footer.php`.

Keep the view body focused on page content and let the template wrap the shared structure.

## Asset Registration

The skeleton provides `public/js/app/home/mngnoadmin.js` in home group meta. In this repository, edit its original under `resources/skeleton/public/js/app/home/` only for an authorized framework change. In an application, edit the application's copy. For runtime modules, follow manifest asset sources and targets rather than assuming the home paths.

The navigation script uses `#header`, `#mng`, `.mobile-nav-toggle` and `.scroll-top`; it does not define project navigation markup or visual styles. Preserve those selectors or update their HTML/CSS/JS consumers together. Load it in footer `js` after the DOM, not `hjs`; it does not require jQuery. Bootstrap collapse and this script must not control the same mobile menu.

The application supplies CSS for `body.mobile-nav-active`, `body.scrolled`, `#mng a.current` and `.scroll-top.active`. Existing behavior includes Escape closing and focus return, restored body overflow, reduced motion and native handling for anchors belonging to other pages or queries. The script initializes the original DOM; do not assume an API for AJAX-replaced headers or a complete modal focus trap.

Locate `docs/navegacion-publica.md` in the resolved GFrame package for the full markup and state contract. Check interactions against the project's actual header, not just the skeleton fixture.

Load CSS and JS through meta files.

Typical public assets include:

- page CSS under `public/css/home/` or other public folders
- public page JS under `public/js/app/home/` or similar folders
- vendor assets only when the screen really needs them

## Public Markup Style

Public pages are usually content-first:

- hero or title section
- supporting sections
- CTAs
- content blocks or article body

Do not force admin-style layout patterns into public templates.

## `section` Rule

Use `section` for major page areas inside the same page, for example:

- hero
- CTA band
- quienes somos
- services
- testimonials
- contact block

Do not use `section` for internal slices inside an `article`, card, repeated content item, or small component. Those internal structures should usually be `div` plus a clear class name.

## Layout Preference

For public pages, prefer:

1. Bootstrap `container` or `container-fluid`
2. Bootstrap `row` and `col-*`
3. Flexbox for custom alignment needs
4. CSS Grid only as the last option

Avoid `display: grid` when the same result can be achieved cleanly with Bootstrap structure or flexbox.
