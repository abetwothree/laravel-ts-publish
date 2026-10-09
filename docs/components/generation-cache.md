# Generation cache

The generation cache lets a publish skip rebuilding a class whose inputs have not changed. The manifest holds one
entry per class and a header; a hit rehydrates the class's transformer from its stored snapshot.

## Where things live

| File | Role |
| --- | --- |
| [`CacheBootstrap`](../../src/Cache/CacheBootstrap.php) | Builds the repository and loads the manifest for a run. |
| [`GenerationManifest`](../../src/Cache/GenerationManifest.php) | The entries and header; decides hits, fingerprints, saves and prunes. |
| [`Fingerprinter`](../../src/Cache/Fingerprinter.php) | Hashes a file set plus an extra signature into one fingerprint. |
| [`DependencyRecorder`](../../src/Cache/DependencyRecorder.php) | Collects the files a build read. |
| [`BaseRunner::cachedGenerate()`](../../src/Runners/BaseRunner.php) | The hit-or-build decision for one class. |
| [`ConfigFingerprint`](../../src/Support/ConfigFingerprint.php) | Hashes the config that shapes output. |
| [`RehydratesFromCache`](../../src/Generators/Concerns/RehydratesFromCache.php) | Builds a generator from a cached snapshot. |

## How a hit is decided

The header busts the whole cache when the package version or the `ConfigFingerprint` changes. An entry hits when the
fingerprint of its recorded dependencies, plus the class's signature and the published-model signature, matches the
stored one and every output file it wrote still exists.

A hit never renders, so the header also hashes the template each cached feature renders and every view it includes by
a literal name: editing or deleting one rebuilds everything, publishing an unedited copy does not.
`BaseRunner::resetRunState()` flushes Laravel's view lookups and compile checks, so a template edited between two runs
in one process renders fresh.

`GenerationManifest::fingerprint()` hashes each dependency file once per run, however many classes depend on it, and
`save()` ends that run so the next one reads every file again.

## Partial runs

A feature skipped by a flag but enabled in config keeps its entries (`Runner::keepSkippedFeatureEntries()`); one
disabled in config is pruned, which only the interactive override can reach. `--fresh --only-*` still clears everything.

A partial run rehydrates the features it skips from their kept entries, so the globals and JSON files still list them
as the last run that published them left them. With the cache off, or with nothing cached for them (the first
publish, `--fresh`, or a config, template or package change), they are left out.

## What is recorded

See [AST engine § Dependency recording policy](ast-engine.md#dependency-recording-policy).
