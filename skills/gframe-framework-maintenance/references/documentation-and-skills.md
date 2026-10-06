# Documentation and Skills

Every framework change should review:

- README and architecture documentation;
- focused module documentation;
- `CHANGELOG.md`;
- tests;
- canonical skills under `skills/`.

Skills are versioned with GFrame and installed into Codex or Claude Code from that source. Do not maintain unrelated manual copies. If code changes invalidate a path, contract, configuration key, or workflow, update the corresponding skill in the same change.

Async keeps Opis Closure 4 through `ClosureWrapper`; use its public serialize/unserialize functions and preserve reading existing Opis 3 tasks through `v3_unserialize()`. Do not replace the serialization provider merely to address a warning from an older release. Verify captured objects, nested closures and a separate PHP worker process when updating this dependency. GFrame requires PHP 8.1.9 or newer because earlier PHP 8.1 patches have a WeakMap reference bug that breaks Opis 4 deserialization; check both web and CLI runtimes.

Keep `docs/` for current usage, contracts, configuration, extension and migration guides. Keep internal plans, task queues and historical audit evidence under `maintenance/`, outside user-facing help. Do not include conversational notes, progress reports, local project provenance or instructions directed at the agent in usage guides. Preserve pending work when moving internal documents. Check relative links and module/profile claims against manifests. Minor editorial corrections do not require changelog entries unless the user requests them; never rewrite published release history to describe later behavior.

Review documentation one topic at a time: installation, configuration, then each module. Write for a developer starting from zero, not for the agent or the implementation discussion. Explain the task, prerequisites, exact file locations and a working example, followed by the expected result and relevant limits. Verify examples against source. State each rule once and link to its canonical guide rather than repeating it in every module. Separate historical migrations and contributor test instructions from everyday usage. Never report a complete documentation review when only some topics have been checked.
