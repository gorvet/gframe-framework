# Async and Persistent Cron

## Async

`Async::create(Closure): void` needs an already booted project with `ABSPATH`. It serializes through `ClosureWrapper`, launches the package's `bin/async-worker.php` and returns no business result or task ID. The child boots the project again. Capture small verified IDs/arrays, and construct services inside the Closure; do not carry PDO, the controller, request globals or session identity into it.

`GFRAME_PHP_BINARY` selects a validated CLI executable; an invalid configured path fails instead of falling back. CLI extensions/environment can differ from the web process. Windows uses a hidden PowerShell process; Unix launches through the shell with output discarded. Large payloads use temporary files, not durable jobs. Async supplies no scheduler, retries, concurrency control or retained application-code version. Never accept serialized functions or worker file paths from a request.

Catch expected launch exceptions in the requesting operation. Later failures belong to the worker; persist business state only if the task requires progress/recovery. A worker already processing background work usually calls its service directly rather than spawning another Async process.

## Persistent Task Recipe

For opt-in content publication, use ScheduledContent on the project's versioned/history model and a project subclass of GFrame\Content\ScheduledPublicationCronHandler. The subclass supplies publicationModel(), server-verified publicationContext(task) and current per-row mayPublish(row, context). Register one recurring existing-cron task per authorized scope; dates live on content, not one task per edit. The handler rechecks due date, status, revision and trash before conditional publication, keeps history transactional, and counts conflicts/denied rows as skipped. Infrastructure failures propagate to cron's existing error policy. No session/middleware runs in the worker. See docs/content-publication.md in the effective package; virtual cron remains traffic dependent.

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

The module publishes `bin/gframe-cron.php`; `GFrame\Cron\CronExecutor` prepares the registrars under the shared execution lock and is used by CLI and virtual workers. `GFRAME_CRON_MODE=real|virtual` selects traffic activation, defaulting to real; `GFRAME_CRON_MIN_INTERVAL` is 1–86400 (default 60), and `GFRAME_CRON_BATCH_LIMIT` is 1–100 (default 10). These environment values are read directly, including existing projects. GFRAME_CRON_EXECUTOR=cli|http defaults to cli. Real mode never registers the web callback. Virtual CLI uses Async/exec; virtual HTTP sends a bounded socket request without waiting for the handler, to APP_URL/gframe-cron.php with TLS verification and no redirects. The published POST endpoint requires GFRAME_CRON_HTTP_SECRET (at least 32 printable ASCII characters without spaces) in Authorization Bearer; virtual mode additionally requires a valid internal launch ticket. No session or Router runs in this endpoint. Real HTTP is called by the external scheduler. The endpoint accepts no handler, payload or limit selection. HTTP occupies a web PHP process and requires working loopback; it does not require exec. Never log secrets or put them in URLs. CLI remains available in either mode. Configure the deployment's cron/Task Scheduler only when requested. `CronScheduler::runDue` alone does not load registrars; it blocks until handlers finish, so avoid calling it from a web action. Diagnose with `php bin/gframe-cron.php --status`; status does not run handlers. No traffic reaching PHP means no timely virtual execution.

## Results and Recovery

The published nginx.conf includes a fully commented cron HTTP location template. Configure its fastcgi_pass using the site's existing PHP destination, then uncomment the entire block. Keep it fully commented until configured: uncommenting a PHP location while leaving its pass directive commented can expose PHP source. When switching PHP, update the cron destination if the site's destination changes. Apache needs no Nginx directives; an upstream proxy still needs independent disconnect handling.

For virtual HTTP behind Nginx/PHP-FPM, the deployment's exact `/gframe-cron.php` FastCGI handler must preserve Authorization, disable interception of PHP errors, and set `fastcgi_ignore_client_abort on`. Otherwise closing the launch socket can cancel forwarding before PHP starts; PHP's ignore_user_abort cannot protect a request that never reaches it. Keep this setting scoped to the cron endpoint. Other proxies must also allow processing after client disconnect. See the package's `docs/servidores-web.md` for the handler example; the reusable Nginx fragment intentionally defines no PHP handler.

The scheduler owns an explicit worker error boundary and catches `Throwable`, including `TypeError`. It marks returned `status => error` or `failed` as failure, recording message/code and not rescheduling that task. Missing status retains legacy success; other statuses are not automatically classified. Handler results are not persisted in full. Check older package versions before relying on these contracts, and review handlers that previously expected an error result to repeat.

`reschedule => false` ends recurrence; an absent key repeats interval tasks. Next execution is interval plus the later of now/previous due date, not fixed local calendar time or catch-up of every missed turn. Batch success can include `data.failed > 0`; the published CLI exits 1 on batch or handler failure. A busy runner can exit 0 without doing work.

Since 4.3.3, CronScheduler and CronTaskService parse zone-less dates explicitly in UTC without changing the project's timezone. Never use a timezone-dependent strtotime on stored UTC values. Verify recurrence, overdue/future selection and both seasonal transitions with UTC and America/Havana using isolated repositories and harmless handlers. Earlier releases can leave existing tasks hours ahead; upgrading does not rewrite those rows. Stop triggers and wait for workers before selectively rescheduling confirmed affected keys through CronTaskService::reschedule with a UTC date. Preserve intended pauses/cancellations and review pending business effects before restarting; see the recovery recipe in docs/cron-runner.md of the effective package.

Reservations increment attempts. CLI and virtual mode share a process-lifetime lock: MySQL GET_LOCK on the same database/primary, or a SQLite transaction in a sidecar database beside the shared data file. SQLite requires local storage with reliable SQLite locks; use shared MySQL across hosts. Never delete the sidecar while running. A live long handler does not expire. Orphaned processing rows recover only after acquiring the execution lock; completion checks ownership. Launch tickets expire after 120 seconds and reject obsolete workers. Network loss can release a MySQL lock while old external work continues: no exactly-once external guarantee. Errors remain error without generic retry; explicitly reschedule after fixing the cause. Stop old runners before migrating, update the published CLI, and do not call recoverStale(0) outside coordinated execution. Custom repositories now also need a compatible CronRuntime/schema, injectable into the scheduler.

Pause disables future reservations; cancel records cancelled/inactive without interrupting a running handler. Resume can reactivate cancelled/error rows and retains the date; reschedule clears lock/error and changes date. These controls are not deletion or process termination.
