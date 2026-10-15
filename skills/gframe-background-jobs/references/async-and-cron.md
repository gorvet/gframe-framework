# Async and Persistent Cron

## Async

`Async::create(Closure): void` needs an already booted project with `ABSPATH`. It serializes through `ClosureWrapper`, launches the package's `bin/async-worker.php` and returns no business result or task ID. The child boots the project again. Capture small verified IDs/arrays, and construct services inside the Closure; do not carry PDO, the controller, request globals or session identity into it.

`GFRAME_PHP_BINARY` selects a validated CLI executable; an invalid configured path fails instead of falling back. CLI extensions/environment can differ from the web process. Windows uses a hidden PowerShell process; Unix launches through the shell with output discarded. Large payloads use temporary files, not durable jobs. Async supplies no scheduler, retries, concurrency control or retained application-code version. Never accept serialized functions or worker file paths from a request.

Catch expected launch exceptions in the requesting operation. Later failures belong to the worker; persist business state only if the task requires progress/recovery. A worker already processing background work usually calls its service directly rather than spawning another Async process.

## Persistent Task Recipe

For an existing authorized, autoloadable handler, schedule once after project bootstrap:

```php
$scheduled = $tasks->schedule(
    'project.summary.' . $tenantID,
    $handlerClass,
    gmdate('Y-m-d H:i:s'),
    ['tenant_id' => $tenantID],
    3600
);
```

`$tasks` is a `CronTaskService` with the project's repository. `$handlerClass` is server-selected, extends global `Cron`, and has no required constructor arguments. `$tenantID` is verified server context. The handler's `handle(array $task = []): array` receives the saved payload, not the database row. Apply tenant filters and business permissions in that handler; the scheduler supplies neither.

Keys are globally unique, up to 120 characters, using letters/numbers and `_.:-`. An existing key returns `cron_task_exists`; it does not update configuration. Inspect the result or guard with the repository's `findByKey`. Store UTC dates. Omit the interval for one execution; recurrence requires at least 60 seconds. Registration does not validate the handler class or start a server process.

The module publishes `bin/gframe-cron.php`; `GFrame\Cron\CronExecutor` prepares the registrars under the shared execution lock and is used by CLI and virtual workers. `GFRAME_CRON_MODE=real|virtual` selects traffic activation, defaulting to real; `GFRAME_CRON_MIN_INTERVAL` is 1–86400 (default 60), and `GFRAME_CRON_BATCH_LIMIT` is 1–100 (default 10). These environment values are read directly, including existing projects. Virtual mode uses Router web/ajax shutdown and Async, without a public execution endpoint or browser polling. CLI remains available in either mode. Configure the deployment's cron/Task Scheduler only when requested. `CronScheduler::runDue` alone does not load registrars; it blocks until handlers finish, so avoid calling it from a web action. Diagnose with `php bin/gframe-cron.php --status`; status does not run handlers. No traffic reaching PHP means no timely virtual execution.

## Results and Recovery

The scheduler owns an explicit worker error boundary and catches `Throwable`, including `TypeError`. It marks returned `status => error` or `failed` as failure, recording message/code and not rescheduling that task. Missing status retains legacy success; other statuses are not automatically classified. Handler results are not persisted in full. Check older package versions before relying on these contracts, and review handlers that previously expected an error result to repeat.

`reschedule => false` ends recurrence; an absent key repeats interval tasks. Next execution is interval plus the later of now/previous due date, not fixed local calendar time or catch-up of every missed turn. Batch success can include `data.failed > 0`; the published CLI exits 1 on batch or handler failure. A busy runner can exit 0 without doing work.

Reservations increment attempts. CLI and virtual mode share a process-lifetime lock: MySQL GET_LOCK on the same database/primary, or a SQLite transaction in a sidecar database beside the shared data file. SQLite requires local storage with reliable SQLite locks; use shared MySQL across hosts. Never delete the sidecar while running. A live long handler does not expire. Orphaned processing rows recover only after acquiring the execution lock; completion checks ownership. Launch tickets expire after 120 seconds and reject obsolete workers. Network loss can release a MySQL lock while old external work continues: no exactly-once external guarantee. Errors remain error without generic retry; explicitly reschedule after fixing the cause. Stop old runners before migrating, update the published CLI, and do not call recoverStale(0) outside coordinated execution. Custom repositories now also need a compatible CronRuntime/schema, injectable into the scheduler.

Pause disables future reservations; cancel records cancelled/inactive without interrupting a running handler. Resume can reactivate cancelled/error rows and retains the date; reschedule clears lock/error and changes date. These controls are not deletion or process termination.
