---
name: gframe-orchestrator
description: Select the GFrame skills needed for a development or review task, resolve framework versus application scope and the effective package version, and coordinate work across relevant specialists without expanding the request.
---

# GFrame Orchestrator

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Use this when a GFrame task crosses responsibilities or needs specialist selection. Use the relevant specialist directly when the task is already confined to it. This skill routes instructions; it does not schedule agents, replace specialist contracts or require loading every GFrame skill.

## Resolve the Target

Identify the actual target from the user's request and project instructions. Distinguish standalone framework maintenance, application business work, and integration/update of an identified application. The current directory alone does not authorize editing another project or its installed package. Preserve existing changes and pending tasks.

For an application, inspect its entry point, `core/Load.php` and Composer configuration to locate its real autoloader/vendor directory. Compare Composer installed metadata (package path/version/reference) with the lock and, when a read-only autoload probe is appropriate, the loaded `GFrame\Foundation\Bootstrap` class file. Path repositories and development checkouts can differ from the lock's expected location. Do not boot the full application merely to select skills; boot can load project routes/registrars. Never print environment secrets.

When the identified package provides it, start with `php <package>/bin/gframe-context.php --project=<target-root>` to obtain package metadata, canonical skill paths, registered modules and CLI extensions in one read-only JSON response. This command neither loads Composer PHP nor reads .env; metadata is not proof of a custom bridge's effective runtime. Keep unknown versions unknown and investigate only unresolved facts relevant to the task. Older packages without this command retain the inspection recipe above.

Read canonical `skills/<name>/SKILL.md` and relevant references from the effective package when present. In this standalone repository, use its own `skills/`. A global skill with the same name may be newer than the target runtime. If the package lacks a specialist, use its local code/docs to establish compatibility before applying external instructions, and record the gap. Do not substitute another checkout, install/sync global skills or update Composer implicitly. Unknown version/path stays unknown; ask only when unresolved target/scope prevents the requested work.

The routing matrix uses canonical folder names. When invoking a skill exposed by a plugin, use its exact client-visible qualified name; Codex exposes this package as `gframe-skills:gframe-backend`, for example. Keep namespaces when invoking and locate the matching package reference when reading; do not assume a bare global skill is the same installed source.

For deeper bootstrap/version investigation, select `gframe-core-architecture` and its `references/bootstrap-configuration-version.md`; do not load all core references for ordinary task selection.

## Select and Work

Read the [specialist routing matrix](references/specialist-routing.md), then load only the entrypoints and conditional references needed for the requested work. General read-order lists apply within that selected mode; they do not require unrelated references when using a focused version-discovery or integration recipe. Preserve applicable hard contracts. Dependencies already installed do not automatically require another specialist. Add one when the change actually reaches its responsibility; remove irrelevant selections rather than treating the matrix as a fixed pipeline.

Resolve shared boundaries before editing: source versus published asset/override, trusted actor/tenant, request/persistence fields and the immediate versus eventual result. Specialists own the actual contracts. Follow their existing nomenclature references; do not rename public symbols or duplicate conventions here.

For work across layers, trace the real producer/consumer and implement in dependency order. A typical request runs route/controller → service/model → response/view/JS; a queued flow separates scheduling, preparation and transport. Do not invent those layers if the task only changes one. Keep the implementation in the user's authorized scope, including already authorized follow-up work.

Verify the affected behavior with the selected specialists' checks. Documentation-only edits need structural/link/recipe evidence, not automatic releases or a full runtime suite. Record skipped integration checks and material limits. Expected business errors, outer success envelopes, queue persistence and transport completion are distinct; do not broaden a contract based on its name.

This router does not require delegation, stage documents, UI redesign, plugin installation, MCP, commits, releases or synchronization. Apply such workflows only when the actual request and available project instructions call for them. When blocked, name the missing target, evidence or authorization without repeating a permission already granted.
