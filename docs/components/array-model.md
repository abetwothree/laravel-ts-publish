# Array model

[`ArrayShape`](../../src/Ast/Arrays/Shape/ArrayShape.php) is how inference reads one array: entries in first-write
order (`ShapeKey` for a named key with its `KeyValue` and `KeyPresence`, `ShapeSignature` for an index signature),
typed string `extras`, `OpaquePart`s that bring keys no name names, and `ShapeWide` for what the array holds beside its
keys (custom imports, carried classes, the read flags, the flat type alias, variants, a nested literal's spread arms,
and each channel map's order). See the [ADR](../decisions/2026-11-22-one-array-model.md).

- **Derived answers.** `certainty()`, `isSealed()`, `nonEmpty()` and `isList()` are computed from the entries, never
  stored; only a proof that the array is never empty is stored. A lifted analysis keeps its unread and unnamed keys as
  the `ShapeWide` flags, which unseal the shape as an opaque part does.
- **Presence.** `KeyPresence` splits a key's `?` into absent and removed, beside the printed `?` a cast may set alone.
  Each derivation keeps its own default for a bare `?`: `fromRow()` reads it as absent, `fromValue()` as removed.
- **Who builds it.** [`ArrayShapes::ofAnalysis()`](../../src/Ast/Arrays/Read/ArrayShapes.php) lifts a
  `MethodAnalysis`; nothing else builds one yet.
- **Who reads it.** [`ShapeLowering::into()`](../../src/Ast/Arrays/Print/ShapeLowering.php) writes a shape back into
  the `MethodAnalysis` publishers read; nothing else reads one yet.
- **Print rules.** `Arrays/Print` is their home. `ShapeLowering` holds the top-level one: entries become rows, extras
  become `holdsUnreadKeys`, and the spread arms stay as the analysis held them.
- **The guard.** `BranchJoin::joinInto()`, `LiteralWrites::applyTo()` and the walk hand each analysis to the
  `ShapeObserver`. The package binds `NullShapeObserver`; `tests/Pest.php` binds `RoundTripShapeObserver`, which lifts,
  lowers and fails on any field that differs, for `tests/Unit/Analyzers`, `tests/Unit/Ast` and the publish command's
  tests except the one `publish-bench.sh` times. It fails a test whose read caught the failure too.
- **Layering.** `Arrays/Shape`, `Arrays/Ops` and `Arrays/Print` use no expression node, `Arrays/Print` reads nothing
  from `Arrays/Build` or `Arrays/Read`, and no file under `src/Ast` reads an environment variable
  (`tests/Architecture/ArraysLayeringTest.php`).
