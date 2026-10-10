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
| [`CastChannels`](../../src/Ast/CastChannels.php) | Fits each cast key's import channels to the cast in force. |
| [`ResourceTransformer::collectCastsInForce()`](../../src/Transformers/ResourceTransformer.php) | Picks the cast in force per key from the method, model and resource casts. |
| [`JsEmitter::castTargets()`](../../src/Support/JsEmitter.php) | Decides which published key a cast key retypes. |

## Sources and precedence

Each row lists a publisher's sources, lowest first. A later source wins the key.

| Publisher | Sources |
| --- | --- |
| Model | class < `$casts` < `casts()` |
| API resource | its model's casts < the resource's own: a spread helper's method < `toArray()` < class < `$casts` < `casts()` |
| Broadcast event | `broadcastWith()` < class < `$casts` < `casts()`; a key the payload lacks gains nothing |
| Inertia shared data | inference < `@return` docblock < the middleware class < `share()` |
| Inertia page | inference < the action's `#[TsCasts]` |
| Model metadata `provide()` | inference < docblock < `#[TsCasts]` |

An API resource's method casts outrank its model's, so `modelCastsOver()` skips every key a method casts.

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

The fit runs at publish time, never when the analyzer applies a cast. Dropping a key's import channels there loses the
classes an import-less cast spells, the wraps a cast writes itself, and what a later class-level cast without an import
needs (`ClassCastOverMethodCastResource`).

`dropOverriddenEnumResources()` stays as the guard for the one record the fit never sees: a later spread's key over an
earlier spread's `EnumResource`, which would make `rewriteEnumResourceTypes()` throw. `SpreadOverSpreadEnumResource`
pins it.

## Imports

- The cast's own import is kept. A displaced class whose name that import brings is dropped unless another key reads
  it. In an API resource, a custom import of that name from any other path, such as a model attribute's `#[TsType]`
  import, is dropped too, since the cast's import already binds the name.
- A class the text spells stays queued for the key, so it is imported and aliased like any package name.
- Every other displaced class is carried, self-keyed with no queue, and kept only for a text that spells its name with
  no class of its own behind it: another key's cast without import channels, an extends clause, a type alias.
  `ResourceTransformer::keepSpelled()` decides that before aliasing, so a carried class no such text keeps never
  forces an alias, and `pruneUnspelledImports()`, after the `AsEnum` rewrite, drops any import whose local name no
  published type spells.
- For a cast key, `resolveMultiEnumAccessorFqcns()` and `resolveMultiClassAccessorFqcns()` register through
  `castSpells()` only the accessor classes its text spells and its import does not bring.
