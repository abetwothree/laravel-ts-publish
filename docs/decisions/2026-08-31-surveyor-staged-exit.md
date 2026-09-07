# ADR: Freeze Laravel Surveyor/Ranger and exit in stages

**Status:** accepted 2026-08-31, **completed 2026-09-01** — both packages are removed. Kept as the
record of why the freeze existed and what each exit stage had to show.

## Context

`laravel/surveyor ^0.1.9` and `laravel/ranger ^0.1.12` are production dependencies used by
`BroadcastEventTransformer`, `InertiaPageAnalyzer`, `InertiaSharedDataAnalyzer` and `SurveyorTypeMapper`.
Upstream moved to surveyor v0.3.0 / ranger v0.5.0 and renamed `ClassResult` to `ClassLikeResult`; the
`^0.1` carets hide this from CI. A measured bump regressed 12 committed `.ts` files and improved none.
On user-shaped input (starter-kit `share()`, Eloquent finders, `Inertia::defer`, `compact()`, computed
`broadcastAs()`) the Surveyor path ships wrong or unimportable types the workbench never exercised.

## Decision

1. Freeze at surveyor v0.1.10 / ranger v0.1.12. No bump.
2. Harden the boundary so an upstream/Surveyor failure degrades one item with a warning instead of
   aborting the run (`SurveyorTypeMapper` fallback, `AnalysisWarnings`).
3. Move broadcast events onto the native `AstEngine` first, then Inertia shared data, then page props.
4. Remove both packages once page props are native.

## Exit gates

Each stage adds user-shape fixtures to the workbench and must show, on those fixtures: zero
unimportable tokens (TS2304 + TS2307), zero known-wrong types, no real-type-to-`unknown` regressions.
Intended golden changes are listed in the PR before it lands; an unlisted diff is a defect.

## Log

- 2026-08-31 — accepted.
- 2026-09-01 — broadcast events native. `BroadcastEventTransformer` no longer takes Surveyor's
  `Analyzer`; payloads come from `AstEngine` and the Echo name from `ReturnLiteralReader`. Trait-declared
  public properties stay deliberately excluded (parity with Surveyor; `#[TsExtends]` supplies them).
  Gate: three user-shape fixtures added first under Surveyor; only golden change is
  `ComputedNameEvent`'s Echo key, `"order."` → `.Workbench.App.Events.ComputedNameEvent`. All 17 event
  interfaces byte-identical; zero unimportable tokens; no real-type-to-`unknown` regressions.
- 2026-09-01 — Inertia shared data native. `InertiaSharedDataAnalyzer` no longer takes Ranger's
  collector; props come from `AstEngine::analyzeMethod($middleware, 'share')`, imports from
  `AnalysisImports`. `errors` is deliberately not inferred: `@inertiajs/core` already declares
  `page.props.errors`, and emitting the framework middleware's own `errors: object` would have added
  an unlisted `unknown`-adjacent key to four committed trees. Gate: all four `inertia-config.d.ts`
  byte-identical; the only golden change is `NonArrayReturnResource` gaining `id`/`name` from the new
  `return array_merge(...)` support, which the plan predicted; both CI gates unchanged.
- 2026-09-01 — page props native; `laravel/surveyor` and `laravel/ranger` removed. `InertiaPageAnalyzer`
  now types every `Inertia::render()` props expression through `AstEngine`'s controller handler profile;
  `SurveyorTypeMapper`, `ControllerPaginatorAnalyzer` (callerless once the four regex passes went), the
  table-taint family on `InertiaTableAnalyzer`, the `ts-publish.inertia.analyzer` config key and the
  `upstream-drift` CI job are all deleted. The taint bypass is gone: sibling actions of a table-bearing
  controller are typed like any other. Its stated premise — that reaching a table through the analyzer
  fatals on PhpSpreadsheet — was measured against the real `inertiaui/table` package with no
  `maatwebsite/excel` installed: autoloading `InertiaUI\Table\Exporter` does throw
  `Error: Trait "Maatwebsite\Excel\Concerns\Exportable" not found`, but over all nine table fixture
  actions the engine loads only `InertiaUI\Table\Table` and its `EncryptsAndDecryptsState` trait — the
  same two the retained `InertiaTableAnalyzer::analyze()` path already loads. The per-action `Throwable`
  boundary and `AnalysisWarnings` are kept, so an unloadable application class still degrades one action
  instead of aborting the run. Gate: 16 generated files changed, 80 insertions, 68 deletions, every line
  on the parity list published with the previous stage; both CI gates unchanged at 10 / 0 / 61.
