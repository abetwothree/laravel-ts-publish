# BarrelWriter

> User-facing docs: [README § Modular publishing](../../README.md#modular-publishing) and
> [README § Publishing types](../../README.md#publishing-types) for the `--only-*` flags.

`AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter` writes the `index.ts` barrel files that re-export every
generated file in a namespace directory. Barrels are **generated files**: their content is exactly the
sorted, de-duplicated set of `export * from './file';` lines the writer decides on, and nothing else in the
file survives.

## API

| Method | Semantics |
| --- | --- |
| `write(Collection $transformers, string $filename, string $outputDirectory)` | One flat barrel at a fixed path (the non-modular layout; used by no runner path today). |
| `writeModular(Collection $generators, ?string $outputBase = null)` | Groups generators by `namespacePath()` and **rewrites** each namespace's `index.ts` from the generators alone. |
| `writeModularPreserving(Collection $generators, Closure $keepExisting, ?string $outputBase = null)` | Same grouping, plus every export already in that `index.ts` whose filename `$keepExisting(string): bool` approves. |

Both modular methods return the barrel contents keyed by namespace path, and both delegate to one private
`writeModularBarrels()` that differs only in whether it merges the approved existing exports in before
sorting. `$outputBase` falls back to `ts-publish.output_directory` when null or empty; it must match the
directory the per-file writer targeted, or the barrel lands beside nothing.

Only namespaces that received at least one generator this run are touched — a namespace with no generator
keeps its file untouched, which is also why a phase that publishes nothing cannot prune anything. Existing
exports are read from disk even when `output_to_files` is false, so a `--preview` shows what a real run
would write. `putIfChanged()` skips the write when the content is byte-identical, keeping the mtime stable
so a watching Vite server does not reload for an unchanged barrel.

An existing barrel is parsed line by line against `EXPORT_LINE`
(`export * from './x';` — either quote style, optional semicolon, any surrounding whitespace, and `preg_split`
on `\R` so CRLF files parse). Comments, blank lines, and anything hand-written are not carried over: a
Prettier pass over a barrel is harmless, a header comment is not preserved.

The preserving behavior is a separate method rather than a third parameter on `writeModular()`, because PHP
fatals when a subclass declares **fewer** parameters than its parent: widening `writeModular()` would break
every `barrel_writer_class` override in the wild at load time, while a new method they simply inherit breaks
none.

## Phase ownership of model barrels

Model interfaces and metadata companions live in the same namespace directory and therefore share one
barrel. Every export in a model barrel belongs to exactly one phase, decided by
`ModelMetadataTransformer::isMetadataFilename()` — a suffix test, which is sound because `Str::kebab()` never
*introduces* an underscore, so a model interface filename can only end in `_meta` if the class name itself
carried the underscore (`User_meta`, which [known-gaps.md](../known-gaps.md) accepts rather than guards). See
[Model metadata § Filenames and barrels](model-metadata.md#filenames-and-barrels).
`Runner::preservedModelBarrelExports()` turns the run's state into the `keepExisting` predicate:

| Phase state this run | Its exports in the rewritten barrel |
| --- | --- |
| Ran | Exactly what it generated this run — stale exports are dropped |
| Enabled in config, skipped by an `--only-*` flag (or by the runner flags a caller set) | Carried over from the existing file |
| Disabled in config | Dropped — turning a phase off prunes it |
| Ran, but a model's provider threw | That model's existing `_meta` export is kept (last known good) |

When neither phase needs preserving and nothing failed, the predicate is `null` and the runner calls
`writeModular()` instead. That is the path a full run on the default config takes, and it is what makes
deleting a model drop its export on the next publish.

"Skipped by a flag" is derived, not tracked: a phase is preserved when its `should*` flag is false **and**
its `*.enabled` config is true. `--only-models`, `--only-model-metadata`, `--only-functional`, and the
interactive config-override prompt all fall out of that one rule with no special cases — including the
prompt's inverse, where a phase runs while its config says disabled and so preserves nothing.

The predicate resolves the **configured** `model_metadata.transformer_class`, not the base class. A custom
transformer that redefines `FILENAME_SUFFIX` names its own companion files, so only it can say which exports
the metadata phase owns; `tests/Fixtures/SuffixedModelMetadataTransformer.php` pins a run where ownership
follows `user.meta` rather than `user_meta`.

Failed models are matched by filename, not by class: `preservedModelBarrelExports()` maps every entry in
`Runner::$modelMetadataFailures` through `$transformerClass::filenameFor()` and approves those filenames
individually, so one model's failure preserves one export and no more.

`RunnerForSource` writes no barrels at all, so a `--source` run never prunes or preserves anything — the
barrel is whatever the last full run left.

## Custom writers

A `barrel_writer_class` subclass inherits `writeModularPreserving()` and needs no opt-in for partial runs to
stay correct (`tests/Fixtures/CustomBarrelWriter.php` — a subclass that overrides nothing — pins this). A
subclass that overrides `writeModular()` to change the output *format* should override
`writeModularPreserving()` the same way: the parent implementation is what partial runs call, and it would
otherwise emit the base format for exactly the runs that preserve. `writeModularBarrels()` and
`existingExports()` are `protected` so such a subclass can reuse the merge instead of reimplementing it —
`tests/Fixtures/HeaderedBarrelWriter.php` overrides both entry points that way and pins a preserving run
that keeps its header.

## Tests

- `tests/Unit/Writers/BarrelWriterTest.php` — rewrite versus preserve, the predicate, non-export lines, quote
  styles, CRLF, and preview parity.
- `tests/Unit/RunnerTest.php` — the ownership table row by row: flag-skipped models, flag-skipped metadata,
  config-disabled metadata, a failed model, a custom `transformer_class`, and both custom barrel writers.
- `tests/Feature/Commands/TsPublishCommandTest.php` — *a full publish drops barrel exports for models that no
  longer exist*, the `--only-*` sequences over a real temp directory, and the same sequences under a custom
  barrel writer.
