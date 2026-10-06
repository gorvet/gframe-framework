# Bootstrap, Configuration and Effective Version

Use this to trace startup in an application or verify that a skill matches the package being used. Discovery is read-only; it does not authorize Composer updates, module publication or global skill synchronization.

## Locate the Loaded Framework

1. Start from the target project's `core/Load.php` and entry point. The current skeleton loads `packages/autoload.php`, then calls `GFrame\Foundation\Bootstrap::boot(ABSPATH)`. Inspect custom bridges rather than assuming every project uses that vendor directory.
2. Inspect the autoloader and its registered files before choosing an execution probe: Composer autoload files can have project side effects. When a read-only probe is appropriate, check `Composer\InstalledVersions::isInstalled('gorvet/gframe')`, then `getInstallPath`, `getPrettyVersion` and `getReference` through that autoloader, without calling application bootstrap. Otherwise use static installed metadata/lock/class-map evidence and record that loaded-class verification was not executed. The lock alone does not establish which files are currently loaded.
3. In that read-only probe, confirm the loaded class location with `ReflectionClass(GFrame\Foundation\Bootstrap::class)->getFileName()`. A path repository, development checkout or customized autoloader can point somewhere other than the default `packages/gorvet/gframe` directory. Resolve its package root before reading `docs/` or canonical `skills/`.
4. Prefer the instructions shipped with that effective package/version. A newer global skill or another checkout is not evidence that the project's runtime supports its APIs. If Composer metadata is unavailable, report that limit and use the confirmed class/package path; do not invent a release version.

`storage/gframe-installed.json` records project module installation and update information. It does not replace Composer's loaded-package evidence or prove that a newer release was installed. Do not read out environment secrets during this inspection.

## Startup Order

`Bootstrap::boot` validates the project root and defines `ABSPATH` and `GFRAME_PATH` if they are not already defined. It loads project configuration, utility files, the legacy constant bridge for structured configuration, project autoload, installed module runtime and finally routes. It records a completed boot and returns early on subsequent calls in the same process.

Configuration discovery checks `config/app.php`, `config/bootstrap.php` and legacy `core/Config.php`. With the structured `config/app.php` and framework defaults available, bootstrap loads package `config/defaults.php`, then merges the project array. Otherwise it uses the first available legacy configuration file; it does not apply all three as additive layers. Configuration files must follow the contract of their mode. Do not migrate a legacy bridge automatically as part of another task.

The current skeleton may redirect an unconfigured web application to its installer only when its configuration and module registry are absent and `install.php` exists. CLI does not take that web redirect path. Do not confuse configuration detection with a completed installation or database preflight.

## Structured Configuration and Environment

- Dotenv safely loads the project's `.env`; adding an environment variable does not automatically create a configuration key. Project PHP config must read it with `env`, `env_bool` or `env_int`.
- `ConfigRepository` recursively merges associative maps; list-valued overrides replace the list rather than append to it. Its dotted `get` lookup returns the default only for a missing path, preserving explicit null values.
- `config()` reads the repository after it has been initialized. `env()` reads environment values and recognizes its supported boolean/null/empty literals; use the typed helpers when that option needs a bool or int.
- `LegacyConfigBridge` derives compatibility constants without redefining existing constants. Changing the repository later does not rewrite constants already defined, and a second boot does not reload configuration. Do not promise live reconfiguration from a file edit in a running worker.
- Project options and credentials belong in the project's config/environment, not in installed package defaults. An authorized change to shared framework defaults is a different maintenance task.

For CLI jobs that build public URLs, configure `APP_URL`: an independent process does not inherit a prior HTTP request's host/protocol. `APP_ENV=production` does not by itself disable debug or change credentials/indexability. Inspect the actual options and bridge, not just the environment label.

## Routes and Module Runtime

Bootstrap reads `storage/gframe-installed.json` and initializes `ModuleRuntime` before loading sorted top-level `config/routes/routes_*.php` files. MVC originals and project overrides are then resolved by the runtime module contract; assets/routes/wrappers can remain managed published files. A published CSS file or an old copied view does not prove runtime module activation.

For route execution, use the [routing lifecycle](routing-lifecycle.md): declared filename type and request-prefix channel are separate decisions. For template/meta resolution and page contracts, use the matching frontend references; do not reproduce their view naming rules here.

Full local guides are `docs/configuracion.md`, `docs/rutas.md` and `docs/modulos-runtime.md` in the confirmed package root. A global skill directory is not a reliable relative base for those guides.
