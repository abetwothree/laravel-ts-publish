# BarrelWriter

[`BarrelWriter`](../../src/Writers/BarrelWriter.php) writes the `index.ts` barrel in each namespace directory, with one
`export * from './file';` line per generated file, sorted and de-duplicated. [`Runner`](../../src/Runners/Runner.php)
resolves it through the `barrel_writer_class` config key, and calls it for the enum, model, resource, form request and
broadcast event phases, after each writes its files. Barrels are generated files, so a write keeps only the export
lines the writer decides on.

## Where things live

Barrel writing spans these classes:

- [`BarrelWriter`](../../src/Writers/BarrelWriter.php): `writeModular()` rebuilds each barrel from the run's
  generators, and `writeModularPreserving()` also keeps the existing exports a predicate approves. Its flat `write()`
  has no caller in the runners.
- [`Runner::preservedModelBarrelExports()`](../../src/Runners/Runner.php): builds that predicate for the model barrel,
  the one barrel two phases share.
- [`ModelMetadataTransformer`](../../src/Transformers/ModelMetadataTransformer.php): `filenameFor()` names a metadata
  companion and `isMetadataFilename()` recognizes one.
- [`RouteWriter::writeRouteBarrels()`](../../src/Writers/RouteWriter.php): writes route barrels instead, because they
  re-export each controller's default export rather than `export *`.

## How a barrel is written

Every write follows these rules:

- **Scope**: a write touches only the namespaces that received a generator this run. Every other barrel keeps its
  file, which is also why a phase that publishes nothing prunes nothing.
- **Output base**: `$outputBase` must match the directory the per-file writer targeted, or the barrel lands beside
  nothing.
- **Preview**: a preserving write reads the existing barrel even when `output_to_files` is false, so `--preview` shows
  what a real run would write.
- **What survives**: only export lines carry over, matched loosely enough that a Prettier pass or CRLF endings are
  harmless. Comments and hand-written lines are dropped.

`writeModularPreserving()` is a separate method, not a new parameter on `writeModular()`. PHP fatals when a subclass
declares fewer parameters than its parent, so widening `writeModular()` would break every `barrel_writer_class`
override at load time. A new method they inherit breaks none.

## Phase ownership of model barrels

Model interfaces and metadata companions live in one namespace directory, so they share one barrel. Every export in it
belongs to exactly one phase, decided by `ModelMetadataTransformer::isMetadataFilename()`. The suffix test is sound
because `Str::kebab()` never introduces an underscore, so a model interface's filename ends in `_meta` only when the
class name itself carries the underscore.
[Model metadata § Filenames and barrels](model-metadata.md#filenames-and-barrels) covers the naming side.

`Runner::preservedModelBarrelExports()` turns the run's state into the predicate:

| Phase state this run | Its exports in the rewritten barrel |
| --- | --- |
| Ran | Exactly what it generated this run. Stale exports are dropped |
| Enabled in config, skipped by an `--only-*` flag or by runner flags a caller set | Carried over from the existing file |
| Disabled in config | Dropped, so turning a phase off prunes it |
| Ran, but a model's provider threw | That model's existing `_meta` export is kept as last known good |

When nothing needs preserving and nothing failed, the predicate is `null` and the runner calls `writeModular()`. A full
run on the default config takes that path, which is how deleting a model drops its export on the next publish.

"Skipped by a flag" is derived, not tracked. A phase is preserved when its `should*` flag is false and its `*.enabled`
config is true. `--only-models`, `--only-model-metadata`, `--only-functional` and the interactive config-override
prompt all follow from that one rule. The prompt's inverse, a phase that runs while its config says disabled, preserves
nothing.

The predicate asks the configured `model_metadata.transformer_class`, not the base class, because a custom transformer
names its own companions. Its `filenameFor()` and `isMetadataFilename()` must agree, and `filename()` dispatches
through `static::`, so an override of the pair changes the written file and its barrel ownership together. Failed
models are matched through `filenameFor()`, so one model's failure preserves one export and no more.
`tests/Unit/RunnerTest.php` pins the table row by row, including the custom transformer fixtures.

`RunnerForSource` writes no barrels, so a `--source` run neither prunes nor preserves. The barrel stays as the last full
run left it.

## Custom barrel writers

A `barrel_writer_class` subclass inherits `writeModularPreserving()`, so partial runs stay correct with no opt-in.
A subclass that overrides `writeModular()` to change the output format must override `writeModularPreserving()` the
same way. Partial runs call the preserving method, and the inherited one emits the base format. `writeModularBarrels()`
and `existingExports()` are `protected` so such a subclass can reuse the merge, as
`tests/Fixtures/HeaderedBarrelWriter.php` does.

## Related

These pages hold the user docs and the accepted gaps:

- [Modular Publishing](https://tolki.abe.dev/ts/modular-publishing.html) in the tolki docs, for the barrel layout users
  see, and [Publishing](https://tolki.abe.dev/ts/publishing.html) for the `--only-*` flags.
- [Known gaps: a model class name containing an
  underscore](../known-gaps.md#a-model-class-name-containing-an-underscore-can-collide-with-a-metadata-companion),
  accepted rather than guarded.
- [Known gaps: a `transformer_class` that overrides only
  one](../known-gaps.md#a-transformer_class-that-overrides-only-one-of-the-two-filename-methods-orphans-its-companions)
  of the two filename methods, which orphans its companions.
