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

The header busts the whole cache when the package version or the `ConfigFingerprint` changes. The fingerprint includes
each macro registered on `JsonResource`, by name and by what it runs: an invokable's class, a function, or a class and
method. A closure counts as `Closure`, since its file is already a recorded dependency. Adding, removing or re-pointing
a macro changes what a bare `$this->m()` in any resource publishes. It also holds each `$wrap` slot the booted app
changed from its declared default (`ResourceWrapping::runtimeOverrides()`), since a provider's `withoutWrapping()` or
`wrap()` reshapes every collection and page prop that wraps by it. An entry hits when the fingerprint of its recorded
dependencies, plus the class's signature and the published-model signature, matches the stored one and every output
file it wrote still exists.

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

## Composed class modules

A value reaching a composed class registers it in `ComposedClassTypes`, but a hit runs no transform and reaches nothing.
Each transformer therefore answers `composedClassTypes()` from the custom imports it keeps, whose marker keys its
snapshot carries; a publisher resolves a marker into a path only as it writes its import lines, so the marker stays. The
class-type phase writes the union of the scanned classes, the registered ones and every built, cached or retained
generator's answer, each through `cachedGenerate()`. It runs after the skipped features are retained, so their files'
modules are written too, but a run retains them only while globals or JSON is on; otherwise a skipped feature's files
import the modules the last run that reached them wrote. A module's entry records the class with its traits,
interfaces and parents, so editing an ancestor's `#[TsType]` rebuilds the module and every file whose value is the
class. A module names no model but caches under the published set too, so a class-types-only run builds that set.

## What is recorded

See [AST engine § Dependency recording policy](ast-engine.md#dependency-recording-policy).
