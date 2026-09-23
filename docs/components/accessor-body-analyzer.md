# AccessorBodyAnalyzer

> User-facing docs: [README § Models](../../README.md#models) (see especially the
> [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist)). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Analyzers\Model\AccessorBodyAnalyzer` types an Eloquent accessor from
what its getter body actually returns. `Attribute::get(fn () => ['major' => $this->major, 'minor' =>
$this->minor])` publishes `{ major: number; minor: number }` rather than the `unknown[]` its `: array`
signature resolves to on its own, and an accessor with no annotation at all stops publishing `unknown`
when its body says something concrete.

Its cycle guard and its memo for the run live in `AnalysisMemo`, a container singleton, keyed per model,
attribute and import mode, so every call site shares them; see
[AST engine § The run memo](ast-engine.md#the-run-memo-replays-what-it-recorded).

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

Two more results are declined:

- **A bare `null` left once an arm was dropped.** A ternary or `??` drops the arm it cannot type, so
  `fn ($value, array $attributes) => $attributes['title'] ?? null` resolves to `null`, while the runtime value is
  the title. `analyze()` compares `DroppedUnionArms::dropped()` before and after the body and declines that `null`,
  so the accessor publishes `unknown`, in the model and in every resource that reads it. A getter that only ever
  returns `null` drops nothing, and still publishes `null` (`Image::no_docblock_accessor`). A real arm left beside
  the `null`, `string | null` for `$this->title ?? null`, stays.
- **A class or enum the model file cannot name.** The aliasing pass gives same-named classes or enums their own
  aliases one occurrence at a time, so the type must spell that token once per FQCN.
  `$this->status ?? $this->crmAuthor?->status` names `App\Status` and `Crm\Status` under one `StatusType`, and
  `$this->author ?? $this->crmAuthor` names two `User` models under one `User`. Each would publish the first alias
  alone and import the second unused, so the body declines, as it did before this step existed.
  `['author' => $this->author, 'lead' => $this->crmAuthor]` spells `User` twice and publishes
  `{ author: AuthorUser; lead: CrmAuthorUser }`. `ResultTypeInfoBridge` carries no resource channel either, so a
  body naming a resource it returns (`new UserResource($this->author)`) declines too.

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

## A getter that reads another model's accessor

A getter that reads another model's accessor spells that accessor's type inside its own, so it needs the same imports.
`ModelAttributeResolver::resolveAttribute()` returns them as `classFqcns`, `enumFqcns` and `customImports`, and the
read hands all three to its engine result through `ValueResult::withAttributeChannels()`. One FQCN of a kind rides the
single-entry channel (`modelFqcn`, `directEnumFqcn`) and several ride the embedded one, as a `$this->accessor` read's do.
`ResultTypeInfoBridge` carries them into the getter's `TypeScriptTypeInfo`, and the model file imports each class.

The workbench `Bulletin` model holds one accessor of each kind: a to-many relation's filter
(`comment_list: Comment[]`), a single relation's filter (`author_pick: Pick<User, 'id' | 'name'>`), a filter on the
model itself (`own_pick`) and an `Attribute<User, never>` docblock (`owner`). Three models read them, each in a file
whose only other import is `Bulletin`:

| Model | Getter | Read | Published |
| --- | --- | --- | --- |
| `BulletinBoard` | `comment_lists` | a typed closure parameter, `$this->bulletins->map(fn (Bulletin $bulletin) => $bulletin->comment_list)` | `Comment[][]` |
| `BulletinBoard` | `lead_author` | a nullsafe relation chain, `$this->lead?->author_pick` | `Pick<User, 'id' \| 'name'> \| null` |
| `BulletinFeed` | `lead_comments` | a relation chain, `$this->lead->comment_list` | `Comment[]` |
| `BulletinFeed` | `owners` | an untyped closure parameter over the docblock accessor | `User[]` |
| `BulletinArchive` | `comment_lists` | `$this->bulletins->pluck('comment_list')` | `Comment[][]` |
| `BulletinArchive` | `author_rows` | a shape a closure parameter builds, `['author' => $bulletin->author_pick]` | `({ author: Pick<User, 'id' \| 'name'> })[]` |

Each file imports `Bulletin, Comment, User`. A read that dropped the channels would leave `Bulletin` the only import
beside the same types: `User` would not compile, and `Comment` would compile against the DOM's `Comment` node, which is
why the token gate also type-checks each tree without the DOM lib (see
[type-inference gates](../testing/type-inference-gates.md)). A method body makes these reads without imports, as the
next section describes, so what it publishes names no class: `BulletinBoard::summary()` publishes
`{ comment_lists: unknown[][]; lead_author: { id: number; name: string } | null; id: number }`.

## A reader that carries no import

The model file publishes the getter's analysis with its FQCN channels, so a filter there publishes `Pick<User, …>` or
`Comment[]`. A method body reached by the body fallback carries no import and drops any shape naming such a token; see
[receiver-types § The body fallback carries no FQCN channel](receiver-types.md#the-body-fallback-carries-no-fqcn-channel).
So when such a scope reads the accessor, `analyze()` takes `carriesImports: false` and passes it to
`AstEngine::analyzeModelClosure()`, which sets it on the getter's scope. The getter's filters then publish what they
would written in the method body itself: the inline shape, where a member naming a token is `unknown`,
`Record<string, unknown>` for a runtime key list, and `unknown[]` for a to-many relation.

`Concerns\ResolvesAccessorType::resolveAccessorBodyType()` then chooses between the getter body and the annotation, and
the reader does not always take the step the model file took. A spelling
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
which resolves through the model engine straight back into `loop_b`'s body. `analyze()` enters an
`AnalysisMemo` guard, `accessor-body:model@attribute`, for the duration of the call and returns `null` on
re-entry, so the inner read degrades to `unknown` and the outer one terminates. `Release::loopA()`/`loopB()`
pin it: both publish `unknown`. The same key memoizes the answer for the run, and a cut-short answer is never
stored.

This key is deliberately separate from `AstEngine`'s own: `analyzeMethod()` guards and memoizes on
`analysis:class@method@modelClass`, while `analyzeModelClosure()` uses neither — which is exactly why this
class keys its own `accessor-body:model@attribute` guard instead of leaning on the engine's.
The two keys are not interchangeable, so unifying them is not a tidy-up; it would break this guard.

The key carries the import mode as well, `accessor-body:model@attribute` and
`accessor-body:model@attribute@importless`. A getter being
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
- **Anything the handlers decline.** The body step inherits the engine's limits exactly; beyond the
  declines above, it adds no rules of its own.

This is why the annotation checklist still applies. A body that resolves to nothing publishes the same
`unknown` it did before, and the cheapest fix remains an accurate `@return Attribute<Get, Set>` —
which, being specific, is read *before* the body and skips this analyzer entirely.
