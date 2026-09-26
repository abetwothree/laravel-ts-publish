# ADR: Freeze Laravel Surveyor/Ranger and exit in stages

**Status:** accepted 2026-08-31, completed 2026-09-01. Both packages are removed. This record keeps why the freeze
existed, what each exit stage had to show, and what the exit left behind.

## Context

`laravel/surveyor ^0.1.9` and `laravel/ranger ^0.1.12` were production dependencies of `BroadcastEventTransformer`,
`InertiaPageAnalyzer`, `InertiaSharedDataAnalyzer` and `SurveyorTypeMapper`. Upstream had moved to surveyor v0.3.0 and
ranger v0.5.0 and renamed `ClassResult` to `ClassLikeResult`, and the `^0.1` carets hid that from CI. A trial bump
regressed 12 committed `.ts` files and improved none. On user-shaped input (starter-kit `share()`, Eloquent finders,
`Inertia::defer`, `compact()`, computed `broadcastAs()`), the Surveyor path shipped wrong or unimportable types that
the workbench never exercised.

## Decision

The decision has four parts:

1. Freeze at surveyor v0.1.10 and ranger v0.1.12. No bump.
2. Harden the boundary, so a Surveyor failure degrades one item with a warning instead of aborting the run
   (`SurveyorTypeMapper` fallback, `AnalysisWarnings`).
3. Move broadcast events onto the native `AstEngine` first, then Inertia shared data, then page props.
4. Remove both packages once page props are native.

## Exit gates

Each stage first adds user-shaped fixtures to the workbench, and must show on them: zero unimportable tokens
(`TS2304` and `TS2307`), zero known-wrong types, and no real-type-to-`unknown` regressions. The PR lists its intended
golden changes before it lands, and an unlisted diff is a defect.

## Consequences

Every stage met its gates, and both packages are removed. The native paths keep these deliberate choices:

- **Broadcast events**: `BroadcastEventTransformer` takes payloads from `AstEngine` and the Echo name from
  `ReturnLiteralReader`. A `broadcastAs()` that is not a whole literal falls back to the class-name convention, so
  `ComputedNameEvent` publishes `.Workbench.App.Events.ComputedNameEvent` instead of the literal prefix `"order."`.
  Trait-declared public properties stay excluded, as they were under Surveyor, because a `#[TsExtends]` trait supplies
  them.
- **Inertia shared data**: `InertiaSharedDataAnalyzer` types `share()` through `AstEngine::analyzeMethod()` and imports
  through `AnalysisImports`. It does not infer `errors`, because `@inertiajs/core` already declares `page.props.errors`
  and the framework middleware's own `errors: object` would only weaken it.
- **Inertia page props**: `InertiaPageAnalyzer` types every `Inertia::render()` props expression through `AstEngine`'s
  controller handler profile. Sibling actions of a table-bearing controller are typed like any other action. The old
  bypass assumed that reaching a table through the analyzer fatals on PhpSpreadsheet. Without `maatwebsite/excel`,
  autoloading `InertiaUI\Table\Exporter` does throw, but the engine loads only `InertiaUI\Table\Table` and its
  `EncryptsAndDecryptsState` trait, the same classes `InertiaTableAnalyzer::analyze()` already loads.
- **Failure boundary**: the per-action `Throwable` boundary and `AnalysisWarnings` stay, so an unloadable application
  class degrades one action instead of aborting the run.
- **Removed**: `SurveyorTypeMapper`, `ControllerPaginatorAnalyzer`, the table-taint family on `InertiaTableAnalyzer`,
  the `ts-publish.inertia.analyzer` config key, and the `upstream-drift` CI job.
