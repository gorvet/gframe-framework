---
name: gframe-background-jobs
description: Implement and review GFrame background execution with Async, persistent cron tasks and workers, or extend heartbeat channels while preserving scheduling, session and failure contracts.
---

# GFrame Background Jobs

Choose execution by the requested lifetime and observable result:

| Need | Existing mechanism | Result to verify |
| --- | --- | --- |
| Finish before responding | Direct service call | Business result |
| Launch a small independent operation now | Global `Async` | Launch only; no durable retry/tracking |
| Persist a future or recurring task | `CronTaskService` and `CronScheduler` | Task state and per-batch counters |
| Process queued notifications | Channel-specific queue processor | Persisted jobs and transport outcomes |
| Refresh a protected browser summary | Heartbeat channel | Channel response; requires browser polling |

Read [Async and persistent cron](references/async-and-cron.md) for jobs/workers. Read [heartbeat channels](references/heartbeat-channels.md) only for browser polling. Campaign and mail specialists own their business payloads and delivery policy; do not recreate them here.

Identify the loaded package/version and project bootstrap. Full guides live in that package at `docs/async.md`, `docs/cron-runner.md`, `docs/procesos-segundo-plano.md` and `docs/heartbeat.md`. Implement application handlers/overrides for project needs; modify standalone originals only for an authorized framework task.

Authorize and resolve IDs/tenant before dispatch, then recheck mutable business state inside delayed work. Workers do not run route middleware or inherit a browser session. Commit dependent database writes before launching another process. Scheduling, queueing and an outer success envelope do not establish completion or exactly-once external effects.

For diagnostics use temporary repositories, fake launchers and bounded jobs without external effects. Existing tests include `AsyncTest`, `CronRunnerTest`, `CronDocumentationTest` and `HeartbeatTest`. Check the requested path and record real OS scheduling, deployment CLI, browser coordination and crash recovery as unverified unless exercised. Creating a skill or testing serialization does not authorize registering production tasks.
