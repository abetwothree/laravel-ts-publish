# AccessorBodyAnalyzer

[`AccessorBodyAnalyzer`](../../src/Analyzers/Model/AccessorBodyAnalyzer.php) types an Eloquent accessor from what its
getter body returns, so `Attribute::get(fn () => ['major' => $this->major])` publishes `{ major: number }` rather than
the `unknown[]` an `: array` signature gives. [`ResolvesAccessorType`](../../src/Concerns/ResolvesAccessorType.php)
asks for the body only after the getter's signature and its `Attribute<Get, Set>` docblock have both proven vague. The
rest of the attribute waterfall is on the [ModelAttributeResolver](model-attribute-resolver.md) page, and usage is on
the tolki [Models](https://tolki.abe.dev/ts/models.html) page.

## Where things live

The body step spans these classes:

- [`AccessorBodyAnalyzer`](../../src/Analyzers/Model/AccessorBodyAnalyzer.php): finds the getter, analyzes it once per
  model, attribute and import mode, and declines a result the model file cannot publish.
- [`Concerns\ResolvesAccessorType`](../../src/Concerns/ResolvesAccessorType.php): the accessor step of the waterfall,
  shared by `ModelAttributeResolver` and `ModelTransformer`, including the choice between body and annotation.
- [`AstEngine`](../../src/Ast/AstEngine.php): `analyzeModelClosure()` seeds the scope and runs the getter on the
  `ResourceExpressionHandlers::forModelClosures()` profile.
- [`AnalysisMemo`](../../src/Ast/AnalysisMemo.php): the cycle guard and the memo for the run.

## When the body is read

For a new-style accessor, the waterfall takes the first of these:

1. the getter closure's signature, if it is neither `unknown` nor vague;
2. the `Attribute<Get, Set>` docblock, on the same test;
3. the getter body, if it resolves to something non-vague;
4. whichever of the signature and the docblock is non-`unknown`, the signature first.

An old-style `get{Name}Attribute()` runs the body step when `methodOrDocblockReturnTypes()` gives nothing specific.

`getterClosure()` reads the `get` argument, positional or named, of each `Attribute::make()`, `Attribute::get()` or
`new Attribute()` the method returns, and takes the first returned getter that is a closure or an arrow function.
Getters returned from different branches are not merged, because merging changes published types and needs its own
audit. A method with no readable getter, such as a same-named relation, falls through to the old-style body, wrapped as
a closure.

## Results the body step declines

`analyze()` returns null, and the waterfall falls back, for three kinds of result:

- **An empty `[]` literal**: it resolves to `never[]`, which passes the vague test but carries no element type.
  Publishing it would override a `@property string[]` tag, as on `HasLabels::getLabelsAttribute()`.
- **A bare `null` left after a dropped arm**: a ternary or `??` drops an arm it cannot type, so
  `$attributes['title'] ?? null` resolves to `null` while the runtime value is the title. `analyze()` compares
  `DroppedUnionArms::dropped()` before and after the body. A real arm beside the `null` stays, as in `string | null`
  for `$this->title ?? null`, and a getter that only ever returns `null` drops nothing and publishes `null`.
- **A token the model file cannot name**: the aliasing pass aliases same-named classes and enums one occurrence at a
  time, so the type must spell each token once per FQCN. `$this->author ?? $this->crmAuthor` puts two `User` models
  under one `User` and declines, while `['author' => $this->author, 'lead' => $this->crmAuthor]` spells it twice and
  publishes both aliases. A resource token declines too, because `ResultTypeInfoBridge` has no resource channel.

## The model is the subject

`AstEngine::analyzeModelClosure()` seeds the scope with `bindingsFor()`, which binds the enclosing method's parameters
and locals, and that matters for a wrapped old-style body. `bindingsFor()` leaves `modelClass` null, so
`analyzeModelClosure()` sets it to the model, and without that line `$this->major` is `unknown`. It also resets the
subject to the model. `resolveBody()` locates on the model, so the context already names it even for a trait-declared
accessor, and the reset keeps that true for a caller that locates on the declaring class. `Release` pins the trait case
through `DerivesReleaseVersion`.

The closure profile drops `ConditionalMethodHandler` and `ToResourceHandler`, because a getter body is not a resource
`toArray()`. It keeps `RelationFilterHandler`, which looks redundant on a single relation's filter, where
`ReceiverMethodCallHandler` gives the same answer. Only `RelationFilterHandler` types these filters:

- a to-many relation's filter, a map proxy, and a multi-model accessor's filter;
- a filter on a column cast to a `Support\Collection`, which publishes `Record<string, unknown>`;
- a filter on an accessor holding an `Eloquent\Collection`, which publishes a list of its models.

`Comment::relationPicks()` pins the handler through its to-many members.

## A getter that reads another model's accessor

A getter that reads another model's accessor spells that accessor's type, so it needs the same imports. The read hands
the `classFqcns`, `enumFqcns` and `customImports` of `ModelAttributeResolver::resolveAttribute()` to its engine result
through `ValueResult::withAttributeChannels()`, and `ResultTypeInfoBridge` carries them into the getter's type info.
Without them the model file imports only the other model, so `User` would not compile and `Comment` would compile
against the DOM's `Comment` node. That second failure is why the token gate also type-checks each tree without the DOM
lib.

The `BulletinBoard`, `BulletinFeed` and `BulletinArchive` models pin each read position against `Bulletin`'s
accessors: typed and untyped closure parameters, a relation chain with and without `?->`, `pluck()`, and a shape built
in a closure.

## A reader that carries no import

A method body reached by the body fallback carries no import, so it drops any shape that names a token; see
[The body fallback carries no FQCN channel](receiver-types.md#the-body-fallback-carries-no-fqcn-channel). When such a
scope reads an accessor, `analyze()` takes `carriesImports: false`, and `analyzeModelClosure()` sets it on the
getter's scope. The getter's filters then publish what they would inside the method body: an inline shape with
`unknown` for a member naming a token, `Record<string, unknown>` for a runtime key list, and `unknown[]` for a to-many
relation. The model file and every resource still publish the analysis with imports.

`ResolvesAccessorType::resolveAccessorBodyType()` then chooses between that spelling and the annotation. Without
imports, a spelling can be vague where the published one is not: a getter returning `$this->comments->only([1, 2])`
publishes `Comment[]` but reads `unknown[]`. The vague spelling beats the fallback annotation only when two things
hold: the analysis with imports is non-vague, and the fallback is `unknown` or names a class the reader cannot import.
`TsTypeString::shapeValueHasUnimportableToken()` detects that class, which would cost the reader its whole shape. Any
other fallback wins without a check against the body, as it does whenever the body is vague.

`ModelAttributeResolver::refineAccessorType()` then refines from an `@property` tag. Without imports it skips a tag
naming a class where the published type is not vague. The tag never refines `Comment[]`, so it must not turn
`unknown[]` back into `Comment[]` either. A token-free tag applies as it does to any vague type.

Those two steps are the only places the flag decides anything. An accessor typed by its signature, its docblock or an
`@property` tag reads the same in both modes, so `Attribute<User, never>` or `fn () => $this->author` still costs a
reading method its shape. `FilteringAccessorModel` in the unit fixtures pins each getter kind and read position, and
`Comment::picksSummary()` pins the rule end to end.

## Cycles

Two accessors that read each other would recurse forever. `analyze()` enters an `AnalysisMemo` guard keyed
`accessor-body:{model}@{attribute}`, with `@importless` appended for a read without imports, and returns null on
re-entry. The inner read is `unknown` and the outer one terminates, as `Release::loopA()` and `loopB()` pin. The same
key memoizes the answer for the run, and a cut-short answer is never stored.

`AccessorBodyAnalyzer` keys its own guard because `analyzeModelClosure()` guards and memoizes nothing, unlike
`AstEngine::analyzeMethod()` with its `analysis:` key. The two keys are not interchangeable, so don't merge them. Each
import mode has its own key because a getter analyzed with imports can call a method that reads the same accessor
without them. A shared key would cut that read short and make the published type depend on which was read first.

## What stays vague

The body step inherits the engine's limits and adds only the declines above. Loop-built keys, as in
`Release::dynamicTotals()`, stay `unknown[]`, and a body that delegates to an untyped helper gets whatever the engine
makes of that call. The fix for those is an accurate `@return Attribute<Get, Set>`, which is read before the body; see
the tolki [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist).

## Related

These pages hold the neighboring rules:

- [ModelAttributeResolver](model-attribute-resolver.md), for the attribute waterfall and `@property` refinement
- [AST engine § The run memo replays what it recorded](ast-engine.md#the-run-memo-replays-what-it-recorded)
- The known gap where a shape whose values name a class loses those values:
  [known-gaps.md](../known-gaps.md#a-shape-whose-values-name-a-class-loses-those-values-in-one-of-two-ways)
- [Type inference gates](../testing/type-inference-gates.md), including the token gate's DOM-free program
