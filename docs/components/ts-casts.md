# `#[TsCasts]` overrides

A `#[TsCasts]` entry is an override: it replaces the type the package infers for one key, and can bring a custom
import. This page owns how the sources rank, what a cast does to the key's import channels, and which imports follow.

## Where things live

| File | Role |
| --- | --- |
| [`TsCastsReader`](../../src/Ast/TsCastsReader.php) | Unpacks `#[TsCasts]` instances into types, import paths and optional flags, a later one winning. |
| [`ParsesTsCasts`](../../src/Concerns/ParsesTsCasts.php) | Reads a class's three locations: the class, `$casts` and `casts()`. |
| [`ResourceAstAnalyzer::applyTsCastsFromMethod()`](../../src/Analyzers/ResourceAstAnalyzer.php) | Applies a method's own casts during analysis and records each one. |
| [`MethodAnalysis::$casts`](../../src/Ast/MethodAnalysis.php) | Each method cast's text and its import path, if any; `merge()` lets the later one win. |
| [`MethodAnalysis::$carried`](../../src/Ast/MethodAnalysis.php) | The classes a cast displaced and its text does not spell, outside every import channel. |
| [`CastChannels`](../../src/Ast/CastChannels.php) | Fits each cast key's import channels to the cast in force. |
| [`ResourceTransformer::collectCastsInForce()`](../../src/Transformers/ResourceTransformer.php) | Picks the cast in force per key from the method, model and resource casts. |
| [`JsEmitter::castTargets()`](../../src/Support/JsEmitter.php) | Decides which published key a cast key retypes. |

## Sources and precedence

Each row lists a publisher's sources, lowest first. A later source wins the key, whatever spelling either gives it.

| Publisher | Sources |
| --- | --- |
| Model | class < `$casts` < `casts()` |
| API resource | its model's casts < the resource's own: a spread helper's method < `toArray()` < class < `$casts` < `casts()` |
| Broadcast event | `broadcastWith()` < class < `$casts` < `casts()`; a key the payload lacks gains nothing |
| Inertia shared data | inference < `@return` docblock < the middleware class < `share()` |
| Inertia page | inference < the action's `#[TsCasts]` |
| Model metadata `provide()` | inference < docblock < `#[TsCasts]` |

An API resource's method casts outrank its model's, so `modelCastsOver()` skips every key a method casts.

## Matching a key

`JsEmitter::castTargets()` decides which published key each cast key of one location retypes
([support helpers § `JsEmitter`](support-helpers.md#jsemitter)). `TsCastsReader::castTargets()` runs it on each
`#[TsCasts]` location alone, in the order above, and a later location's claim on a key nulls every earlier cast key
that took it, so the later location outranks the earlier whatever either spells.
`ResourceTransformer::castsOverAnalysisKeys()` (the resource and its model apart),
`BroadcastEventTransformer::transformProperties()` and `InertiaSharedDataAnalyzer::buildResult()` call it once the keys
are known, then re-key the merged maps with `JsEmitter::retargetCasts()`. On a resource and an event, a key no
attribute names, such as one a transformer subclass injects, decides last, as one more location. An Inertia page has
one location and decides per rendered component. A page's and shared data's imports follow the surviving casts, so a
losing spelling brings none.

A numeric cast key keeps its key: PHP stores `'42'` as an int, which `array_merge()` or a spread renumbers, so every
merge of cast maps uses `array_replace()`. An API resource's class-level cast on `42` then adds `"42": T`, as a method
cast does.

## A cast is final for its key

A key a `#[TsCasts]` entry retypes publishes that entry's text: of the classes the displaced value carried, the key
keeps only those its text spells and its own import does not bring, to import them and, where two classes share a
name, to alias them, never to reshape the text. Every other displaced class is imported only while some other
published text still needs it.

`CastChannels::fit()` applies the rule once, over the casts in force, so no `AsEnum` rewrite, `Partial<>` or alias
queue meets the displaced value. `ResourceTransformer::runAstAnalysis()` and
`BroadcastEventTransformer::transformProperties()` call it after the reconcile, `AnalysisComposer::compose()` first,
for `AstEngine::analyze()`, and `InlineArrayHandler` over an inline array's members, whose casts no outer cast can
retype. An event cast whose key names no payload property adds neither the key nor its import. The Inertia analyzers
still drop a cast key's import channels with `forgetChannels()`.

A cast entry describes one value of its key. `merge()` drops it where a later source sets the key without a cast, and
`mergeReturnBranches()` keeps it only where every branch that sets the key casts it alike.

The fit runs at publish time, never when the analyzer applies a cast. Dropping a key's import channels there loses the
classes an import-less cast spells, the wraps a cast writes itself, and what a later class-level cast without an import
needs (`ClassCastOverMethodCastResource`).

`dropOverriddenEnumResources()` stays as the guard for the one record the fit never sees: a later spread's key over an
earlier spread's `EnumResource`, which would make `rewriteEnumResourceTypes()` throw. `SpreadOverSpreadEnumResource`
pins it.

## Imports

- The cast's own import is kept, and it binds each name it brings. A displaced class of that name is dropped unless
  another key reads it, and `fit()` drops a custom import of that name from any other path, such as a displaced
  `#[TsType]` import, on every publisher. `ResourceTransformer` also skips a model attribute's import of it.
- A class the text spells stays queued for the key, so it is imported and aliased like any package name.
- Every other displaced class is carried in `MethodAnalysis::$carried`, which an inline array passes on as its own
  result key. Each publisher registers a carried class only where `CastChannels::spelledUnclaimed()` finds a text
  that spells its name with no class of its own behind it: another key's cast without import channels, an extends
  clause, a type alias. So a carried class no such text keeps never forces an alias.
  `ResourceTransformer::keepSpelled()` applies the same test to the classes it holds, and `pruneUnspelledImports()`,
  after the `AsEnum` rewrite, drops any import whose local name no published type spells.
- For a cast key, `resolveMultiEnumAccessorFqcns()` and `resolveMultiClassAccessorFqcns()` register through
  `castSpells()` only the accessor classes its text spells and its import does not bring.
