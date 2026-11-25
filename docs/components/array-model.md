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
  Each derivation keeps its own default for a bare `?`: `fromRow()` reads it as absent, `fromValue()` as removed. A
  `ShapeKey`'s own presence is the key's; its value's row keeps the one it was read with, and no reader takes that.
- **Who builds it.** [`ArrayShapes::ofAnalysis()`](../../src/Ast/Arrays/Read/ArrayShapes.php) lifts a
  `MethodAnalysis`, `ShapeJoin` joins shapes, [`ShapeWrites`](../../src/Ast/Arrays/Ops/ShapeWrites.php) writes into
  one, and `ArrayWalk::shape()` gives the walk's.
- **The walk.** [`ArrayWalk`](../../src/Ast/Arrays/Build/ArrayWalk.php) holds the returned-variable walk as an
  `ArrayShape` and lands its whole writes, `+=`, key writes, `??=`, `unset()`s, loosenings and exclusive joins on it,
  with what each path leaves a key holding in `WalkKeyState`; `ReadsReturnedVariables` decides what each statement
  writes and reads the walk's lowered `state()` only where a written value reads the variable. A join is lowered for
  the observer and the host's drop recorder, which may retype a key, then read back. Every cause of unread keys the
  walk meets itself records an `OpaquePart` with its site; an unnamed `unset()` records none.
- **Write rules.** `ShapeWrites` lands each write as `MethodAnalysis::merge()`, `addProperty()`,
  `addSignatureEntry()`, `forgetChannels()` and `dropKeys()` land it, per channel map and in each map's key order, so
  the walk lowers to the analysis those methods would build; `ShapeWritesTest` holds it to them, and its joins to
  `BranchJoin`, over random write sequences. A seed's or a write's extras and opaque parts fold into the read flags
  as `ShapeLowering::folded()` folds them; a written shape keeps its own, and no never-empty proof. A new channel map
  is added there as in `ShapeJoin`.
- **Who reads it.** [`ShapeLowering::into()`](../../src/Ast/Arrays/Print/ShapeLowering.php) writes a shape back into
  the `MethodAnalysis` publishers read; `BranchJoin::joinInto()` lifts its branches, joins and lowers them.
- **The per-key join.** [`KeyValue::joined()`](../../src/Ast/Arrays/Shape/KeyValue.php) joins one key's values across
  branches: their union and slots, the cast they agree on, their classes split or read by class, and their presence
  through `KeyPresence::join()`. `ShapeJoin::exclusive()` calls it per key, and `ShapeJoin::value()` for a literal's
  key with several sources.
- **The exclusive join.** [`ShapeJoin::exclusive()`](../../src/Ast/Arrays/Ops/ShapeJoin.php) joins shapes field by
  field, as PHPStan unions constant arrays; its extras and non-empty rules are inert until a builder makes them. A dropping join first reads every
  write of a key another branch spells unalike as untyped, or as a gap in the gap mode, the one such rule for branches
  and a fold's sources. The wide data joins as `MethodAnalysis::merge()` merges it, each channel map in merge order.
- **Print rules.** `Arrays/Print` is their home. `ShapeLowering` holds the top-level one: entries become rows, extras
  and the walk's opaque parts become `holdsUnreadKeys` (`folded()`), and the spread arms stay as the analysis held
  them. An opaque kind no rule lowers yet throws rather than vanish, and no field holds the never-empty proof.
- **The guard.** `BranchJoin::joinInto()`, `LiteralWrites::applyTo()` and the walk hand each analysis to the
  `ShapeObserver`. The package binds `NullShapeObserver`; `tests/Pest.php` binds `RoundTripShapeObserver`, which lifts,
  lowers and fails on any field that differs, for `tests/Unit/Analyzers`, `tests/Unit/Ast` and the publish command's
  tests except the one `publish-bench.sh` times. It fails a test whose read caught the failure too. While
  `KEY_JOIN_PARITY` names a directory, `KeyJoinParityRecorder` wraps the observer and records every analysis per test,
  so two runs compare join by join; CI never sets it.
- **Layering.** `Arrays/Shape`, `Arrays/Ops` and `Arrays/Print` use no expression node, `Arrays/Print` reads nothing
  from `Arrays/Build` or `Arrays/Read`, and no file under `src/Ast` reads an environment variable
  (`tests/Architecture/ArraysLayeringTest.php`).
