# Repository Workflow

The GFrame repository is the source of framework code. Applications consume it with Composer and normally retain only `core/Load.php` as a bootstrap bridge.

For a shared change:

1. implement and test it in GFrame;
2. commit the framework change;
3. run `composer update gframe/framework` in the application;
4. verify application routes and adapters;
5. commit the application lock and integration changes.

Before installation, the same update command must refresh installer/bootstrap files without loading project configuration, connecting to a database, publishing application modules or writing an installation lock. Test both `--dry-run` and execution. Unconfigured web requests must redirect to the installer before application bootstrap, preserving the deployment subdirectory; do not enable reinstallation merely because an installed project's configuration is missing.

For an installed project, update the package and then run the managed project updater:

1. `composer update gframe/framework`;
2. `composer gframe:update -- --dry-run`;
3. review the managed files that will be replaced;
4. `composer gframe:update`;
5. run the application integration checks and commit `composer.lock` plus `storage/gframe-installed.json`.

Module schema changes must be delivered as ordered MySQL and SQLite migrations in the module manifest. Fresh installations baseline those migrations after installing the current schema.

Treat framework and published module files as managed code. Applications extend them through external services, adapters, composition, and public contracts; direct edits may be overwritten by the updater.

The managed skeleton update includes `app/views/templates/mail/` (HTML and adjacent metadata), not only web templates and shared CSS. When changing mail templates, test rendering from an updated temporary project, including replacement of old published files, dry-run and `--preserve-custom`; source-only render tests cannot establish installed-project behavior.

`ProjectScaffolder::UPDATE_PATHS` is the shared managed skeleton policy used by installation hashes and updates. Do not silently omit new skeleton files: the coverage test requires an update entry or an explicit project-owned exception. Keep project routes, permissions, Composer metadata, footer credits and branding outside managed replacement. Test every module's published files against the updater; runtime controller/model/view files remain in the package and customization directories remain empty.

Create module customization directories only for native layers containing files, not every possible MVC layer. `storage/gframe-installed.json` is the sole installed-module registry; do not generate or consult `config/modules.php`. Use `ModuleRuntime::isInstalled()` for installed modules including non-MVC transports, and `has()` for active MVC runtime modules. Deployment aids belong in root `deployment/`, not PHP `config/`; keep them protected in Apache/Nginx and included in the updater. Preserve old customized files instead of deleting them during migration.

Use semantic versioning for public releases. During `0.x`, document compatibility changes and migration requirements explicitly.

Optional frontend components live in `resources/modules`. Register dependencies in the module manifest and publish them through `ModuleAssetPublisher`; do not duplicate browser libraries across framework directories.

The visual installer uses four task-focused screens: project and conditional administrator account (one password), conditional database connection, optional modules grouped by topic and filtered by profile, then review/install. Mandatory modules and technical dependencies are never presented as choices. Do not add a welcome screen or anticipate database/account instructions before their applicable step. Use the original password meter and SweetAlert feedback without abandoning the connection form. Before confirmation, only installer public assets may be published; no application configuration, routes or database tables. Keep this contract and its tests in sync with `docs/instalacion.md`.

The visual installer is a compact pre-bootstrap wizard at `resources/skeleton/install.php`, with assets under `public/css/install/` and `public/js/install/`. Its assets must work before modules are published. Keep those paths in ProjectScaffolder::UPDATE_PATHS. Distinguish shared mandatory defaults (including gfselect/gf-table), profile requirements and optional extras. Group optional modules by topic in their own step; do not ask users to select profile requirements, timezone, SEO activation or generated SEO files. SEO owns virtual sitemap/robots/llms responses. DatabasePreflight checks connectivity and emptiness without writes; final installation creates a missing database if permitted and rechecks the target. Never erase existing tables. Only final confirmation calls ProjectInstaller. Preserve server CSRF and installation-lock guards, omit secrets from restored browser data and summaries, and test all four profiles.

Installer optional topics live in resources/install/optional-modules.php. Use InstallationProfileCatalog::optionalModules() for both display and submitted-selection validation. Exclude all resolved defaults/profile requirements and incompatible transitive schema needs; module install_profiles can narrow availability. Technical dependencies are resolved automatically, not offered as extra decisions. Hide empty topic groups.
