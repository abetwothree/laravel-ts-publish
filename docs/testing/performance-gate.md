# Performance gate

`.github/scripts/publish-bench.sh` measures how long a full publish takes and gates a PR when it gets
materially slower. Types are gated in [`type-inference-gates.md`](type-inference-gates.md), speed here.

## The workload

The script runs one test, uncached:

```bash
php -d memory_limit=-1 vendor/bin/pest tests/Feature/Commands/TsPublishCommandTest.php --filter="writes files to disk"
```

That is `ts:publish writes files to disk` (`tests/Feature/Commands/TsPublishCommandTest.php`) — one
complete, uncached publish through the real test harness. `ts-publish.cache.enabled` is `false` in tests
(the `ts-publish.cache.enabled` override in `tests/TestCase.php`), so every run re-does the full analysis
pass, and the harness runs real migrations against a database, so the DB-introspection cost is included.

This is deliberately the *test-harness* publish, not `composer ts:publish` from the workbench. The
workbench command has no database connection and skips DB-introspection entirely (`AGENTS.md`), so it
measures a cheaper, unrepresentative path. The test-harness publish is the one that pays the full cost a
real consuming app pays on every `ts:publish` run with caching off, so it is the one worth gating.

## `composer bench` — local checkpoints

```bash
composer bench
```

Runs the workload once as a warmup (discarded), then three timed runs, and prints the median:

```
publish-bench median: 5.49s (3 runs, warmup discarded)
```

This is a local-only report — no gate, no comparison, exit code always `0`. It exists so a task that
touches the publish pipeline can record a before/after number on the same machine without needing
`hyperfine` or a second checkout. Several tasks in the unified-AST-engine plan call this at fixed
checkpoints; see the log table below.

## The CI gate — A/B against the merge-base

CI runs the same workload twice on one runner — once against the merge-base, once against the PR head —
under `hyperfine`, and fails when the head's median exceeds the base's median by more than `MAX_RATIO`
(default `1.25`, i.e. +25%):

```bash
.github/scripts/publish-bench.sh <baseDir> <headDir>
```

```
base 5.49s  head 6.10s  ratio 1.111 (max 1.25)
```

The A/B-on-one-runner design is the point: absolute CI times are noise across runners (different hosts,
different load), but the head/base **ratio** measured back-to-back on the *same* runner is stable. Five
`hyperfine` runs of a ~6s workload, times two commands, keep the job well under the timeout.

### Overriding `MAX_RATIO`

A deliberate, justified slowdown — a new analysis pass that trades speed for correctness, for example —
can raise the threshold for that run:

```bash
MAX_RATIO=1.40 .github/scripts/publish-bench.sh <baseDir> <headDir>
```

Do this only when the slowdown is understood and accepted, and say so in the PR description. Raising
`MAX_RATIO` to make a gate failure disappear without explaining why is exactly the failure mode this gate
exists to catch — treat every red gate as a real question about the change, not noise to silence.

### Missing `hyperfine`

The CI gate path (anything other than `--local`) requires `hyperfine` on `PATH`. If it is missing, the
script fails immediately with a clear message rather than letting the shell surface a raw "command not
found" error:

```
FAIL - hyperfine is not installed. Install it (e.g. 'brew install hyperfine' locally, or 'sudo apt-get install -y hyperfine' in CI) before running the A/B gate.
```

## Local baseline log

`composer bench` medians recorded at fixed checkpoints in the unified-AST-engine plan. Machine-local
numbers, not comparable across machines — they gate the branch's four bench checkpoints against each
other on the same box, never CI.

| date       | checkpoint              | local median                          |
| ---------- | ----------------------- | ------------------------------------- |
| 2026-08-31 | Branch baseline         | 5.49s (3 runs)                        |
| 2026-09-01 | Surveyor/Ranger removed | 0.94s (3 medians: 0.92 / 0.95 / 0.94) |

The last row is a **5.9x speedup**, large enough to deserve its own check, so it was measured as a
same-session A/B rather than against the recorded baseline: a worktree at the parent commit with
`laravel/surveyor` and `laravel/ranger` installed produced medians of 5.33 / 5.59 / 5.55s on the same
box minutes earlier. The workload is identical on both sides — one filtered test, 8 assertions, 324
generated files — so the difference is the second AST engine no longer running.
