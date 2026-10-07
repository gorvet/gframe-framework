# Repository Workflow

The GFrame repository is the source of framework code. Applications consume it with Composer and normally retain only `core/Load.php` as a bootstrap bridge.

## Framework Change

For a shared change in the standalone repository:

1. implement and test it in GFrame;
2. update affected contracts, documentation and canonical skills;
3. prepare reviewable changes and record verification plus integration limits;
4. commit, tag or publish only when the respective action belongs to the user's authorized task.

Changing GFrame does not automatically update an installed application or global assistant skills. Verify consumer behavior in a temporary project when needed; do not use a real application as a test fixture without authorization. Preserve existing work and separate a behavior fix from a proposed compatibility change.

## Application Integration

Use the following recipe only when the user has authorized updating a specific consuming project. Confirm its root, Composer-resolved GFrame version, installed modules and managed/custom files. Resolve its configured vendor directory rather than assuming every project uses `packages/`. Creating a project, installing a module, publishing assets and upgrading an existing project are different operations; consult the matching package's `docs/comandos.md`, `docs/instalacion.md` and `docs/actualizaciones.md` for that task.

The commands here describe the integration path; reading this reference does not authorize running them in other projects.

When developing or verifying the pre-installation updater, the same update command must refresh installer/bootstrap files without loading project configuration, connecting to a database, publishing application modules or writing an installation lock. Test both `--dry-run` and execution. Unconfigured web requests must redirect to the installer before application bootstrap, preserving the deployment subdirectory; do not enable reinstallation merely because an installed project's configuration is missing. These checks are not prerequisites for updating an already installed application.

For an authorized installed-project upgrade, update the package and then run the managed project updater:

1. confirm the requested target version is permitted by the project's Composer constraint; if an exact version was requested, resolve that exact target rather than an arbitrary allowed latest version. Adjust the constraint only as needed within the authorized update, then run `composer update gorvet/gframe` and verify the resolved lock and installed package match the target;
2. `composer gframe:update -- --dry-run --preserve-custom` when preserving customizations;
3. review the managed files that will be replaced;
4. `composer gframe:update -- --preserve-custom`, retaining the preservation policy reviewed in dry-run; replace customized managed files only when that replacement is in scope;
5. resolve reported conflicts without discarding personalizations, then run application integration checks and verify the final loaded version. Include changed Composer metadata, `composer.lock` and `storage/gframe-installed.json` in the review, and commit them only when committing is part of the task.

`composer update gorvet/gframe` resolves/downloads the package and changes the lock. `composer gframe:update` applies managed files and migrations from that downloaded version. `--dry-run` previews changes; `--preserve-custom` preserves modified managed files and reports conflicts, rather than merging them. `--no-database` is not a completed upgrade when schema changes are required. Plan the compatibility path before applying a migration or replacing a customized managed file.

Production deployment and release publication remain separate from validating a temporary integration project. Follow existing task authorization; do not add a new approval requirement for an action the user has already authorized.

## Distribution and Installer Contracts

Read the following only for installer/updater/distribution development or the specific integration surface being verified. Updating one installed application does not require redesigning or retesting the entire visual installer.

Module schema changes must be delivered as ordered MySQL and SQLite migrations in the module manifest. Fresh installations baseline those migrations after installing the current schema.

MySQL migration execution journals statements and holds a connection lock per database. Confirmed statements are skipped; a changed partial migration or an uncertain started statement blocks retry. Inspect actual effects before using MigrationRunner::resolveInterruptedStatement with applied/not-applied confirmation. Never automatically replay an uncertain non-idempotent statement. Older executions have no journal. SQLite retains transaction-per-migration behavior; see the effective package's docs/actualizaciones.md.

Treat framework and published module files as managed code. Applications extend them through external services, adapters, composition, and public contracts; direct edits may be overwritten by the updater.

The managed skeleton update includes `app/views/templates/mail/` (HTML and adjacent metadata), not only web templates and shared CSS. When changing mail templates, test rendering from an updated temporary project, including replacement of old published files, dry-run and `--preserve-custom`; source-only render tests cannot establish installed-project behavior.

`ProjectScaffolder::UPDATE_PATHS` is the shared managed skeleton policy used by installation hashes and updates. Do not silently omit new skeleton files: the coverage test requires an update entry or an explicit project-owned exception. Keep project routes, permissions, Composer metadata, footer credits and branding outside managed replacement. Test every module's published files against the updater; runtime controller/model/view files remain in the package and customization directories remain empty.

Create module customization directories only for native layers containing files, not every possible MVC layer. `storage/gframe-installed.json` is the sole installed-module registry; do not generate or consult `config/modules.php`. Use `ModuleRuntime::isInstalled()` for installed modules including non-MVC transports, and `has()` for active MVC runtime modules. The Nginx fragment belongs in root `nginx.conf`, beside `.htaccess`, not PHP `config/`; keep it protected in Apache/Nginx and included in the updater. Preserve the panel's shared PHP include. Where it intercepts errors, document a site-specific exact `index.php` FastCGI handler in `server`, with `fastcgi_intercept_errors off` and `GFRAME_SERVER_ERROR`, using the server's real PHP-FPM endpoint. The reusable fragment must not define a socket or PHP handler. Preserve old customized files instead of deleting them during migration.

Use semantic versioning for public releases. During `0.x`, document compatibility changes and migration requirements explicitly.

Optional frontend components live in `resources/modules`. Register dependencies in the module manifest and publish them through `ModuleAssetPublisher`; do not duplicate browser libraries across framework directories.

The visual installer uses four task-focused screens: project and conditional administrator account (one password), conditional database connection, optional modules grouped by topic and filtered by profile, then review/install. Mandatory modules and technical dependencies are never presented as choices. Do not add a welcome screen or anticipate database/account instructions before their applicable step. Use the original password meter and SweetAlert feedback without abandoning the connection form. Before confirmation, only installer public assets may be published; no application configuration, routes or database tables. Keep this contract and its tests in sync with `docs/instalacion.md`.

The visual installer is a compact pre-bootstrap wizard at `resources/skeleton/install.php`, with assets under `public/css/install/` and `public/js/install/`. Its assets must work before modules are published. Keep those paths in ProjectScaffolder::UPDATE_PATHS. Distinguish shared mandatory defaults (including gf-select/gf-table), profile requirements and optional extras. Group optional modules by topic in their own step; do not ask users to select profile requirements, timezone, SEO activation or generated SEO files. SEO owns virtual sitemap/robots/llms responses. DatabasePreflight checks connectivity and emptiness without writes; final installation creates a missing database if permitted and rechecks the target. Never erase existing tables. Only final confirmation calls ProjectInstaller. Preserve server CSRF and installation-lock guards, omit secrets from restored browser data and summaries, and test all four profiles.

Installer optional topics live in resources/install/optional-modules.php. Use InstallationProfileCatalog::optionalModules() for both display and submitted-selection validation. Exclude all resolved defaults/profile requirements and incompatible transitive schema needs; module install_profiles can narrow availability. Technical dependencies are resolved automatically, not offered as extra decisions. Hide empty topic groups.
