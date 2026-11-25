# ADR: One array model for spreads, walks and maps

**Status:** accepted 2026-11-22; Phase 1 under way in #161.

## Context

Inference read arrays in four places, each with its own representation: the returned-variable walk wrote rows into a
`MethodAnalysis` and kept side tables per key (`keyPaths`, `optionalValueKeys`, the untyped writes), a literal's fold
kept its writes, sources and arms in `LiteralWrites`, a branch join unioned rows in `BranchJoin`, and five text readers
re-split a printed value to read its keys back. A fact one of them knew, such as a key's presence, extras beside named
keys or the site of an unread spread, could not reach the others, which is the mechanism behind the #161 family.

## Decision

One model, `ArrayShape` in `src/Ast/Arrays/Shape/`, after PHPStan's constant array: ordered entries (named keys with
their value and presence, and index signatures), typed string extras, opaque parts that carry their sites, a derived
`nonEmpty()` and `isList()`, and what an array holds beside its keys (`ShapeWide`). Builders (`Arrays/Build`),
operations (`Arrays/Ops`), readers (`Arrays/Read`) and the printer (`Arrays/Print`) work on it; `MethodAnalysis` stays
the edge contract publishers read, reached through `ShapeLowering`. Each refactor lands byte-identical behind a
round-trip guard the tests bind (`ShapeObserver`), and each domain then takes its fixes.

## Fallbacks that remain

Where a component cannot reach parity, it keeps today's representation behind the new API. The prototype built before
Phase 1 recorded two; the returned-variable walk reached parity on an `ArrayShape` in Phase 1, so one remains:

- **A signature's raw value.** `ShapeSignature` holds its value as the row prints it, `| undefined` included. The print
  adds no `undefined` where the value already held one, so a lifted row cannot give the raw value back; a builder that
  writes a signature itself can keep it raw.

## Consequences

- Every array fact has one home, so a fix to presence, extras or a list part lands once for the walk, the fold and
  the join.
- `MethodAnalysis` and `ShapeLowering` remain until publishers read shapes; the lift and the lowering cost a little
  on each join, measured per phase against the speed bars.
- Until then `ShapeWrites` and `ShapeJoin` restate `MethodAnalysis`'s per-map write and merge rules, such as a whole
  write keeping a reassigned key's held object and a signature's queues appending; a property test holds `ShapeWrites`
  to those methods, map order included.
- `Analysis/Fold/` stays where it is until a follow-up moves it under `Arrays/Build/`.
