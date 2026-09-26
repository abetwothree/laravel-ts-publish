# Performance gate

[`publish-bench.sh`](../../.github/scripts/publish-bench.sh) times a full publish, and in CI it fails a push that makes
the publish more than 25 percent slower than its merge-base. [Type inference gates](type-inference-gates.md) cover
correctness.

## The workload

The script times one test, `ts:publish writes files to disk` in `tests/Feature/Commands/TsPublishCommandTest.php`:

```bash
php -d memory_limit=-1 vendor/bin/pest tests/Feature/Commands/TsPublishCommandTest.php --filter="writes files to disk"
```

`tests/TestCase.php` turns `ts-publish.cache.enabled` off, so every run repeats the whole analysis, and the harness
migrates a real database, so the time includes schema introspection. `composer ts:publish` would measure a cheaper path
than a real application pays, because the workbench has no database connection and skips introspection.

## Local timing with `composer bench`

`composer bench` runs the script with `--local`, which discards one warmup run and times three more. It gates nothing,
so it exits `0` whatever the timing, and non-zero only when the workload test fails. It prints the median:

```text
publish-bench median: 0.92s (3 runs, warmup discarded)
```

Use it to compare a change with its parent on one machine. The numbers do not carry across machines.

## The CI gate

The `publish-bench` job in [`run-tests.yml`](../../.github/workflows/run-tests.yml) checks out the merge-base with
`origin/main` into `/tmp/base`, or `HEAD~1` when that merge-base is missing or is `HEAD`. It installs both sides from
the same `composer.lock`, prints each side's `laravel/framework` version, and runs:

```bash
bash .github/scripts/publish-bench.sh /tmp/base "$PWD"
```

`hyperfine` times each side with one warmup and five runs, and the script fails when the head median divided by the
base median exceeds `MAX_RATIO`, `1.25` by default. Its `PASS` or `FAIL` line prints both medians and the ratio. Both
sides run back to back on one runner, because absolute times vary between runners while their ratio on one runner
holds. The gate never checks a speedup, and [Known gaps](../known-gaps.md#the-publish-speed-gate-is-one-sided)
explains what that allows.

To run the comparison yourself, pass two checkouts with their Composer dependencies installed. Without `hyperfine` on
`PATH`, the script exits `1` with an install hint before timing anything:

```bash
.github/scripts/publish-bench.sh base_checkout head_checkout
```

### Overriding `MAX_RATIO`

Raise the threshold only for a slowdown you understand and accept, such as an analysis pass that buys correctness, and
explain it in the pull request. Raising it to clear a red gate without that explanation silences the failure the gate
exists to catch. `MAX_RATIO` is an environment variable. Locally, set it for one run:

```bash
MAX_RATIO=1.40 .github/scripts/publish-bench.sh base_checkout head_checkout
```

The workflow sets no `MAX_RATIO`, so raising it in CI means adding it to the environment of the
`Gate - publish speed vs merge-base` step:

```yaml
- name: Gate - publish speed vs merge-base
  env:
    MAX_RATIO: "1.40"
  run: bash .github/scripts/publish-bench.sh /tmp/base "$PWD"
```
