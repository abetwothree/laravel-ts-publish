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

`GenerationManifest::fingerprint()` hashes each dependency file once per run, however many classes depend on it, and
`save()` ends that run so the next one reads every file again.

## What is recorded

See [AST engine § Dependency recording policy](ast-engine.md#dependency-recording-policy).
