# AccessorBodyAnalyzer

> User-facing docs: [README § Models](../../README.md#models) (see especially the
> [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist)). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Analyzers\Model\AccessorBodyAnalyzer` types an Eloquent accessor from
what its getter body actually returns. `Attribute::get(fn () => ['major' => $this->major, 'minor' =>
$this->minor])` publishes `{ major: number; minor: number }` rather than the `unknown[]` its `: array`
signature resolves to on its own, and an accessor with no annotation at all stops publishing `unknown`
when its body says something concrete.

It is registered as a singleton, because its cycle guard has to span every call site rather than one
instance.

## When the body is read

Never first. `Concerns\ResolvesAccessorType` — used by both `ModelAttributeResolver` and
`Transformers\ModelTransformer` — asks for the body only once the annotations have failed to say
anything specific:

1. the getter closure's own signature, if it is neither `unknown` nor vague;
2. the method's `Attribute<Get, Set>` docblock, same test;
3. **the getter body**, if it resolves to something non-vague;
4. the original fallbacks — whichever of signature or docblock is merely non-`unknown`.

So a precise annotation still wins outright and is never even parsed against the body; the body only
beats a *vague* one. The old-style `get*Attribute()` branch gained the same step, which is why its
first check is now "non-`unknown` **and** non-vague" rather than just non-`unknown`: `: array` has to
fall through to the body instead of settling for `unknown[]`.

An empty `[]` literal body is declined rather than published. `InlineArrayHandler` resolves it to
`never[]`, which is non-vague by the shared predicate but carries no element information at all —
publishing it would let the body override a `@property string[]` tag with a type that can hold
nothing. `HasLabels::getLabelsAttribute()` is exactly that case and still publishes `string[]`.

## Which getters are found

`getterClosure()` walks the accessor method's return expressions and reads the `get` argument —
positionally or by name — off whichever form the method used: `Attribute::make()`, `Attribute::get()`,
or `new Attribute()`. Only a closure or arrow function is accepted; a callable string or a first-class
callable yields nothing and the analyzer declines.

**The first getter found wins.** An accessor that returns a different `Attribute::get()` from each
branch of an `if` is typed from whichever branch comes first, not from a merge of the two. That is
deliberately unlike a method body's `return` branches, which Task 23 taught to merge: merging here is a
behaviour change that needs its own diff audit, so it is a follow-up rather than a silent extension. No
workbench fixture writes that shape today.

When no new-style getter is found, the old-style `get{Name}Attribute()` body is wrapped as a closure
and analyzed the same way, so `Release::getSummaryAttribute()` publishes `{ major: number }` from its
literal.

## Scope: the model is the subject

`AstEngine::analyzeModelClosure()` seeds the scope with `bindingsFor()`, which binds the *enclosing
method's* parameters and local variables, not the `get` closure's own. That seeding therefore does
little for a new-style accessor; it earns its keep on the old-style path, where the wrapped body
**is** the method. What `bindingsFor()` does not set is `modelClass`, which it leaves **null**, so
the load-bearing line is `$scope->modelClass = $modelClass`. Without it nothing in the body resolves
against the model and `$this->major` is `unknown`. The subject is re-asserted as the model alongside it: `resolveBody()` locates on the model
FQCN, so `MethodContext::$reflection` already *is* the model even when the body it found lives in a
trait's file, and re-asserting keeps that true for any future caller that locates on the declaring
class instead. `Release` uses `DerivesReleaseVersion` to pin the trait-declared case end to end. The
analyzer runs on `ResourceExpressionHandlers::forModelClosures()` — an accessor body is not a resource
`toArray()`, so `ConditionalMethodHandler` and `ToResourceHandler` have no business claiming its expressions.
`RelationFilterHandler` stays: the body reads the model's own relations and columns, and only that handler types a
to-many relation's filter, a map proxy, a multi-model accessor's filter, a filter on a column cast to a
`Support\Collection` (`'collection'`, `AsCollection` and their encrypted forms), which publishes
`Record<string, unknown>`, or one on an accessor holding an `Eloquent\Collection`, which publishes a list of its
models. A single relation's filter, and one on an accessor returning a `Support\Collection`, also reach
`ReceiverMethodCallHandler`, which gives the same answer. `Comment::relationPicks()` pins the handler through its
to-many members: `replies` publishes `Comment[]`, `kept_replies` `Comment[] | null` and `reply_previews`
`{ id: number; content: string }[]`. Its `Pick<User, 'id' | 'name'>` would survive without the handler.

## A reader that carries no import

The model file publishes the getter's analysis with its FQCN channels, so a filter there publishes `Pick<User, …>` or
`Comment[]`. A method body reached by the body fallback carries no import and drops any shape naming such a token; see
[receiver-types § The body fallback carries no FQCN channel](receiver-types.md#the-body-fallback-carries-no-fqcn-channel).
So when such a scope reads the accessor, `analyze()` takes `carriesImports: false` and passes it to
`AstEngine::analyzeModelClosure()`, which sets it on the getter's scope. The getter's filters then publish what they
would written in the method body itself: the inline shape, where a member naming a token is `unknown`,
`Record<string, unknown>` for a runtime key list, and `unknown[]` for a to-many relation.

`Concerns\ResolvesAccessorType::resolveAccessorBodyType()` keeps the waterfall step the model file took. A spelling
without imports can be vague where the published one is not: a getter returning `$this->comments->only([1, 2])`
publishes `Comment[]`, and `unknown[]` without imports. The fallback is the annotation the waterfall returns when the
body step declines: the closure signature unless it is `unknown`, else the `Attribute<>` docblock, or an old-style
getter's own return type or `@return` docblock, `unknown` when it has none. Where the analysis with imports is
non-vague, the vague spelling wins over just two fallbacks: one that is `unknown`, so the reader gets `unknown[]`
instead, and one that names a class the reader cannot import (`TsTypeString::shapeValueHasUnimportableToken()`), which
would cost the reader its whole shape. Any other fallback wins, exactly as it wins whenever the body is vague, and it
is never checked against the body: `Attribute<list<array<string, mixed>>, never>` gives the reader
`Record<string, unknown>[]`, and `Attribute<array<string, mixed>, never>` gives it `Record<string, unknown>` even over
a filter that returns a list. When both analyses are vague, as for a runtime key list returned whole, both fall
through, and the reader gets the fallback the model file publishes, `unknown` when the getter has no annotation.

`ModelAttributeResolver::refineAccessorType()` then refines a vague spelling from an `@property` tag. Without imports
it skips a tag naming a class only where the published type is not vague: the tag never refines `Comment[]`, so it
must not turn `unknown[]` back into `Comment[]`. A token-free tag, such as `list<array{id: int}>`, applies as it does
to any vague type.

The body step and that refinement are the only places the flag decides anything. An accessor typed by its closure
signature, its `Attribute<>` docblock, an old-style getter's own return type or `@return` docblock, or an `@property`
tag reads the same in both modes, so one naming a class, such as `Attribute<User, never>`, still costs a reading
method its shape, and so does a getter returning a class without a filter, such as `fn () => $this->author`.
`FilteringAccessorModel` in the unit fixtures pins each getter kind and read position, and `Comment::picksSummary()`
pins the rule end to end.

## Cycles

Two accessors that read each other would recurse forever: the body of `loop_a` reads `$this->loop_b`,
which resolves through the model engine straight back into `loop_b`'s body. `analyze()` keys a guard by
`model@attribute` for the duration of the call and returns `null` on re-entry, so the inner read
degrades to `unknown` and the outer one terminates. `Release::loopA()`/`loopB()` pin it: both publish
`unknown`.

This guard is deliberately separate from `AstEngine`'s own: `analyzeMethod()` guards and memoizes on
`class@method@modelClass`, while `analyzeModelClosure()` uses neither — which is exactly why this
class carries its own `model@attribute` guard on a shared singleton instead of leaning on the engine's.
The two keys are not interchangeable, so unifying them is not a tidy-up; it would break this guard.

The guard is keyed per import mode as well, `model@attribute` and `model@attribute@importless`. A getter being
analyzed with imports can call a method whose body reads the same accessor without them. A shared key cuts that read
short whenever the getter's own analysis is on the stack, so the getter and the method each published a different
type depending on which of them was read first. Each mode's key still terminates its own cycle:
`FilteringAccessorModel::loopA()`/`loopB()`, read from a method body, terminate without imports as `Release`'s do with
them, and `selfReport()`, whose `report()` reads it back, publishes the same type whichever of the two is read first.

## What stays vague

The body is not a type checker, and three shapes it cannot read are deliberate:

- **Loop-built keys.** `Release::dynamicTotals()` assigns `$totals['k'.$index]` inside a `foreach`;
  nothing about those keys is statically knowable, so it stays `unknown[]`.
- **Untyped helpers.** A body delegating to a helper with no return type resolves to whatever the
  engine can make of that call, which is often nothing.
- **Anything the handlers decline.** The body step inherits the engine's limits exactly; it adds no
  rules of its own.

This is why the annotation checklist still applies. A body that resolves to nothing publishes the same
`unknown` it did before, and the cheapest fix remains an accurate `@return Attribute<Get, Set>` —
which, being specific, is read *before* the body and skips this analyzer entirely.
