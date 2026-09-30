# Repository Workflow

The GFrame repository is the source of framework code. Applications consume it with Composer and normally retain only `core/Load.php` as a bootstrap bridge.

For a shared change:

1. implement and test it in GFrame;
2. commit the framework change;
3. run `composer update gframe/framework` in the application;
4. verify application routes and adapters;
5. commit the application lock and integration changes.

For an installed project, update the package and then run the managed project updater:

1. `composer update gframe/framework`;
2. `composer gframe:update -- --dry-run`;
3. review the managed files that will be replaced;
4. `composer gframe:update`;
5. run the application integration checks and commit `composer.lock` plus `storage/gframe-installed.json`.

Module schema changes must be delivered as ordered MySQL and SQLite migrations in the module manifest. Fresh installations baseline those migrations after installing the current schema.

Treat framework and published module files as managed code. Applications extend them through external services, adapters, composition, and public contracts; direct edits may be overwritten by the updater.

Use semantic versioning for public releases. During `0.x`, document compatibility changes and migration requirements explicitly.

Optional frontend components live in `resources/modules`. Register dependencies in the module manifest and publish them through `ModuleAssetPublisher`; do not duplicate browser libraries across framework directories.
