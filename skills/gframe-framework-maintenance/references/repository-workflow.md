# Repository Workflow

The GFrame repository is the source of framework code. Applications consume it with Composer and normally retain only `core/Load.php` as a bootstrap bridge.

For a shared change:

1. implement and test it in GFrame;
2. commit the framework change;
3. run `composer update gframe/framework` in the application;
4. verify application routes and adapters;
5. commit the application lock and integration changes.

Use semantic versioning for public releases. During `0.x`, document compatibility changes and migration requirements explicitly.
