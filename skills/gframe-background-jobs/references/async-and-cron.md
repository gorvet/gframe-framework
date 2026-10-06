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

The module publishes `bin/gframe-cron.php`; that command boots `core/Load.php`, loads `config/cron/*.php` and runs handlers sequentially. Configure the deployment's cron/Task Scheduler only when requested. Its optional task limit is 1–100 (default 10). `CronScheduler::runDue` alone does not load registrars; it blocks until the handlers finish, so avoid calling it from a web request.

## Results and Recovery

The current scheduler catches `Exception`; it does not classify returned `status => error` as failure or persist handler results. An expected business failure that must mark a cron task failed should be converted to a controlled exception by the owning handler. Do not change existing handlers' contracts incidentally; the proposed runtime change is separate. Do not promise that `TypeError` is converted to a failed row.

`reschedule => false` ends recurrence; an absent key repeats interval tasks. Next execution is interval plus the later of now/previous due date, not fixed local calendar time or catch-up of every missed turn. Batch success can include `data.failed > 0`, and CLI exit 0 can include handler failures.

Reservations increment attempts. Stale processing tasks recover after 900 seconds, without a renewable lease or fencing token; a long-running handler can overlap a later reservation. Exceptions mark error with no generic automatic retry. After correcting the cause, explicitly reschedule. Design business idempotency around external effects; database reservation is insufficient.

Pause disables future reservations; cancel records cancelled/inactive without interrupting a running handler. Resume can reactivate cancelled/error rows and retains the date; reschedule clears lock/error and changes date. These controls are not deletion or process termination.
