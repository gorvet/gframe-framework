# Documentation and Skills

Every framework change should review:

- README and architecture documentation;
- focused module documentation;
- `CHANGELOG.md`;
- tests;
- canonical skills under `skills/`.

Skills are versioned with GFrame and installed into Codex or Claude Code from that source. Do not maintain unrelated manual copies. If code changes invalidate a path, contract, configuration key, or workflow, update the corresponding skill in the same change.

Editing canonical skills in `skills/` and installing them into an assistant are different actions. A skill review does not authorize running `bin/install-skills.ps1` or overwriting global copies. Use the task's existing authorization when installation is requested, and preserve unrelated skills and explicit personalizations. For copied references, verify the matching-version dependencies in a temporary installation; a valid repository-relative link alone is not proof of portability.

For an authorized project install, use `bin/install-skills.ps1 -ProjectPath <project-root> -Target Codex|Claude|All -DryRun` first, inspect its source/version and conflicts, then run the same project/target without DryRun within the existing authorization. This mode resolves Composer installed metadata without booting PHP, records hashes, preserves custom/unregistered content and rejects symlinks/junctions; a custom bootstrap still needs effective-package verification. It does not remove global skills or build a plugin. Without ProjectPath, the legacy global overwrite mode remains. Verify changes to this installer with `tests/InstallProjectSkillsTest.ps1` and temporary projects, not real user skill directories.

For plugin packaging, use `bin/build-skills-plugin.ps1` from the identified framework checkout, with an explicit distribution version and a new output directory outside that checkout. Review `-DryRun` before building. It copies only the fourteen canonical GFrame skills, creates portable and Claude manifests, checks local links and hashes, and records source evidence including uncommitted skill changes. It neither installs nor publishes; interrupted output is not a validated artifact. Verify with `tests/BuildSkillsPluginTest.ps1`, then check client loading separately. Keep stages and UX external.

Match verification to the edit. A wording change needs contract/link/encoding review; an executable example needs its relevant syntax or behavior check. Runtime, schema, updater or frontend changes need the corresponding functional and integration checks. Existing passing results do not verify later changes, but do not rerun an unrelated full suite for every editorial adjustment.

For skill distribution checks, `php bin/validate-skills.php --root <copied-skills-directory>` checks local file links throughout Markdown, including cross-skill companions, without loading PHP application code or visiting remote links. It does not validate heading fragments or full Markdown semantics. Run `python bin/validate-skills-metadata.py --root <copied-skills-directory>` separately with Python 3.9+ and PyYAML for YAML syntax and known optional metadata fields; `{}` remains valid. Unknown metadata fields are not certified. The PHP/Composer check has no new Python dependency. Verify these tools with `tests/SkillsValidatorTest.php` and `python -m unittest discover -s tests -p test_skill_metadata.py` using temporary copies. Neither structural check proves runtime recipes or client loading.

Async keeps Opis Closure 4 through `ClosureWrapper`; use its public serialize/unserialize functions and preserve reading existing Opis 3 tasks through `v3_unserialize()`. Do not replace the serialization provider merely to address a warning from an older release. Verify captured objects, nested closures and a separate PHP worker process when updating this dependency. GFrame requires PHP 8.1.9 or newer because earlier PHP 8.1 patches have a WeakMap reference bug that breaks Opis 4 deserialization; check both web and CLI runtimes.

Keep `docs/` for current usage, contracts, configuration, extension and migration guides. Keep internal plans, task queues and historical audit evidence under `maintenance/`, outside user-facing help. Do not include conversational notes, progress reports, local project provenance or instructions directed at the agent in usage guides. Preserve pending work when moving internal documents. Check relative links and module/profile claims against manifests. Minor editorial corrections do not require changelog entries unless the user requests them; never rewrite published release history to describe later behavior.

Review documentation one topic at a time: installation, configuration, then each module. Write for a developer starting from zero, not for the agent or the implementation discussion. Explain the task, prerequisites, exact file locations and a working example, followed by the expected result and relevant limits. Verify examples against source. State each rule once and link to its canonical guide rather than repeating it in every module. Separate historical migrations and contributor test instructions from everyday usage. Never report a complete documentation review when only some topics have been checked.
