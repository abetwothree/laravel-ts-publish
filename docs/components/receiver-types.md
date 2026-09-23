# Receiver types

> User-facing docs: [README § API resources](../../README.md#api-resources). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver` answers one question for the AST engine: which
PHP class, or classes, does this expression hold? `ReceiverType` carries the answer.
`ReceiverMethodCallHandler` asks it for a method call's receiver, then `ReceiverMethodReturnResolver` types the
method on each class it names; see [Following a method's return type](#following-a-methods-return-type).

## What a receiver type is

A receiver type names PHP classes, never TypeScript types. The engine needs both. To type
`$this->source->label()`, it first has to know that `$this->source` holds `Workbench\App\Enums\Status`,
and only then can `LaravelTsPublish::methodOrDocblockReturnTypes()` reflect `label()` on that class and
turn its return into TypeScript. A TypeScript type string such as `StatusType` cannot be reflected, so the
class has to be tracked on its own.

`ReceiverType` holds four fields:

| Field | Holds |
| --- | --- |
| `classes` | Every class, interface, or enum the value may be an instance of, as a non-empty list. |
| `shortCircuits` | `true` once the chain has passed a `?->` step, so the whole expression can end as `null`. |
| `elementModel` | The model of an Eloquent collection, for example `Comment` for `$post->comments`. |
| `relatedModel` | The related model of a relation instance, for example `Comment` for `$post->comments()`. |

`models()` returns the classes that are Eloquent models.

## Receiver resolution

`ReceiverClassResolver::resolve()` dispatches on the node class. This table is the source of truth for its
rules. The class docblock points here.

| Expression | Receiver |
| --- | --- |
| `$this->resource` | The subject's own redeclaration when it names a class (`/** @var MediaType\|null */ public $resource`), else `$scope->modelClass ?? $scope->instanceOfWrappedClass`. `JsonResource::$resource` is `@var mixed`, which names nothing, so an ordinary resource takes the backing class. |
| `$this->resource->prop`, `$this->resource?->prop` | The model-member rules `$this->prop` uses on a model-backed subject. The subject-declared property rule never applies. |
| `$this->prop`, where `SubjectPropertyTypeResolver::declaresOwnProperty()` holds — declared on the subject below any `Illuminate\` ancestor, non-static, and not a framework name it inherits — at any visibility | Its native class type, else its full `@var` type |
| `$this->prop` on a model-backed subject | `ModelAttributeResolver::resolveAttributeClass()`, else the relation: its morph targets or `resolveMorphToBound()`, `[EloquentCollection]` with `elementModel` for to-many, or the related model |
| `$var` | `varClassBindings` first — an `instanceof` narrowing outranks every other binding. The ordering that is load-bearing today is that it sits above the `closureParamExprBindings ?? localVarBindings` fallback, because a guarded variable is normally bound by a plain `$x = …;` assignment; sitting above `varModelBindings` is the same concern for a narrowed closure param or loop variable, and is motivating rather than currently proven. Then `varModelBindings`, then `varCollectionBindings` (a collection with `elementModel`), then `requestVarNames`, then `closureParamExprBindings` and `localVarBindings` resolved recursively under `resolvingLocalVars`. An unbound variable is `null`. See [AST engine § Narrowing](ast-engine.md#narrowing) for what writes `varClassBindings`. |
| `<receiver>->prop`, `<receiver>?->prop` | For a model class, the attribute and relation rules above. For any other class, its declared **public** property's class. |
| `$this->method()` | The subject's own method, at any visibility. When the subject is a `JsonResource` that does not declare it, the backing class's **public** method, on the class `forwardedThisReceiver()` names. |
| `<receiver>->relationMethod()` on a model where `resolveRelation()` knows the name | `ReceiverType([<return class>], relatedModel: <related model>)`. A relation method with no declared return names `Relation`. |
| `<relation receiver>->getRelated()` | `ReceiverType::of($receiver->relatedModel)` |
| `$request->user()`, where the receiver class is a `Request` | `AuthUserResolver::model()`, when that model is not `null` |
| `<receiver>->method()`, `<receiver>?->method()` otherwise | `returnClasses()` for every receiver class, for a **public** method. Any `null` makes the whole answer `null`. |
| `static::m()` | The subject, then `returnClasses()`, at any visibility |
| `self::m()`, `parent::m()` | The subject, or its framework parent, then `returnClasses()`, at any visibility. Only when the subject has no user-land ancestor; see [`self` and `parent`](#self-and-parent). |
| `X::m()`, `$var::m()`, `$this->resource::m()` | The class, then `returnClasses()`, for a **public** method |
| `new X(...)` | `X` |
| `new static` | The subject |
| `new self`, `new parent` | The subject, or its framework parent. Only when the subject has no user-land ancestor; see [`self` and `parent`](#self-and-parent). |
| `resolve(X::class)`, `app(X::class)` | `X` |
| `now()`, `today()` | `Illuminate\Support\Carbon` |
| `collect(...)` | `Illuminate\Support\Collection` |
| A ternary, `?:`, or `??` | The union of the non-`null` arms. Any unresolved non-`null` arm makes the whole answer `null`, with one exception. A ternary's `instanceof` condition on its own true arm narrows that arm, and an unresolved true arm then holds the tested classes; see [A ternary's `instanceof` condition](#a-ternarys-instanceof-condition). |

`$this->prop` and `$this->resource->prop` share one code path. A `JsonResource` forwards what it does not
declare to its model through `__get()` and `__call()`, so the two spellings are the same read at runtime.
The one asymmetry is deliberate: a property the resource declares itself answers only the `$this->prop`
spelling, because PHP reads a declared property before `__get()` runs, and `$this->resource->prop` always
reads the model.

Attribute filters follow the same rule. `$this->resource->only([...])` publishes what `$this->only([...])`
publishes, and `$this->resource->author?->except([...])` what `$this->author?->except([...])` does, in spread and
value position alike. The pieces that make that hold are in
[ResourceAstAnalyzer § `$this->resource` spells the same filter](resource-ast-analyzer.md#this-resource-spells-the-same-filter).

### `self` and `parent`

PHP resolves `self` and `parent` against the class that declares the method body, not the class the method
runs on. The engine can analyze an inherited body under a child subject: `MethodLocator::locateOwn()`
returns an ancestor's body when that ancestor is declared in the same file, and
`ResourceAstAnalyzer::analyzeThisMethodSpread()` uses `MethodLocator::locate()`, which returns bodies from
any parent or trait. `AnalysisScope` does not record which class declared the body.

Take `class P extends G`, `class C extends P`, and `P::build()` called on a `C`. There `new self` builds a
`P`, `new static` builds a `C`, `self::m()` runs `P::m()`, and `parent::m()` runs `G::m()`. Naming the
subject `C` for `new self` would claim a `P` is a `C`.

So `self` and `parent` resolve only when the subject has no user-land ancestor, meaning its parent class is
absent or lives under `Illuminate\`. Then every body the engine can analyze under the subject was written
in the subject itself, or in a trait it uses, where `self` is also the subject. `new self(...)` in a
resource that extends `JsonResource` directly, such as `FluentSelfResource`, still names the resource. Any
other subject declines. `static` always names the subject, because late static binding follows the object
the method runs on.

### Visibility

A member counts at any visibility when it is read on `$this` or through `self::`, `static::`, or `parent::`.
On any other receiver only a public member counts. `method_exists()` and `property_exists()` also match
protected and private members, but PHP sends an outside read of one of those to `__call()` or `__get()`,
never to the declaration. `Post::titleDisplay()` is protected, so `$this->titleDisplay()` on a `Post`
subject names `Attribute`, while `$post->titleDisplay()` and `$this->resource->titleDisplay()` decline.

### Return and property types

`returnClasses(string $class, string $method)` reads the method's native return type first. `static` names
`$class`, the class the call is read through. `self` names the class that declares the method, so
`ChildDto::copy()` inherited from `BaseDto::copy(): self` names `BaseDto`. For a trait method, that is the
class using the trait. A union of classes and `null` names those classes. Any builtin arm makes the answer
`null`.

A method with no native type falls back to its `@return` docblock, and a property with no native class type
falls back to its `@var` docblock. `PropertyDocblockTypeReader::extractReturnType()` and `extractVarType()`
read the whole type, including spaces inside generics and around `|`, and any `[]` after a generic's closing
`>`. `extractReturnType()` requires the tag at the start of a line and tries `@return`, `@phpstan-return`,
then `@psalm-return`, as `LaravelTsPublish::extractReturnTypeFromDocblock()` does. That shared extractor is
not used here because it stops at the first closing `>`. The resolver then splits the union and drops `null`.
It maps `$this` and `static` to the receiver class and `self` to the declaring class. A part must be exactly
a name or a name with generic arguments: `Collection<int, User>` resolves to `Collection`, while
`Collection<int, User>[]` is an array and declines. Each name resolves against
`LaravelTsPublish::methodDeclaringFileClass()`'s imports. Every name must be a loadable class, interface, or
enum. `@var Collection<int, User>|string` and `@var UrlService | string` both decline, because `string` is
not a class.

`returnClasses()` itself ignores visibility. Its callers apply the visibility rule above.

A ternary and a `??` differ in how they treat `shortCircuits`. A full ternary keeps an arm's flag, since
`$flag ? $a?->b : $c` can still end as `null`. A `??` or `?:` replaces a `null` first arm with the second,
so the first arm's flag never reaches the result.

### A ternary's `instanceof` condition

`ReceiverClassResolver::testedClasses()` reads a ternary's condition when it is an `instanceof` test, or an `||` chain
of them, and every operand tests the true arm's own read path. `fromArms()` then narrows the true arm through
`narrowed()`. The true arm runs only when some operand holds, so its value is an instance of one of the tested classes.
`NarrowedImageableResource` pins the rule. `Image::imageable` is a `morphTo` over int-keyed
and string-keyed models, and its `$either` is bound through
`$this->imageable instanceof Post || $this->imageable instanceof User ? $this->imageable : null`. So
`$either?->getKey()` publishes `either_id: number | null`, while `$this->imageable?->getKey()` publishes
`open_id: number | string | null`.

The same read path means the same variable, or the same chain of property reads, such as `$this->a->b` or
`$x?->y`, with the same names and the same `->` or `?->` at each step. A method call never qualifies, because a
second call may return a different value from the one the test saw. `ReadsInstanceofChains` splits the `||` chain.
The early-exit guard pass in `CollectsInstanceofGuards` uses the same grammar.

The tests narrow each class `R` the arm resolves to, one class at a time. The first row that matches decides,
whatever order the tests are written in:

| `R` against the tested classes `T` | The arm holds |
| --- | --- |
| 1. `R` is a `T`, or a subtype of one | `R` |
| 2. Some `T` is neither a subtype nor a supertype of `R`, and `R` or that `T` is an interface | `R`. A subtype of `R` may pass that test without being any other `T`, and no single class names that intersection. |
| 3. Some `T` is a subtype of `R`, as when `R` is a morph bound such as `Model` | Those `T` |
| 4. Otherwise: every `T` is a class unrelated to `R` by inheritance, and `R` is not an interface | Nothing, because no object is both |

So `Model` tested against `Post || Authenticatable` stays `Model`, in either order: a `User` passes the interface
test without being a `Post`.

A test never widens a class the arm already names, so `$this->author instanceof Model ? $this->author : null`
still holds `User`. When no class survives, the arm can never run, and it keeps what it resolved to. When the arm
resolves to nothing, as an unbound variable does, it holds the tested classes. Only the classes change: the value
is the one the arm resolved, so it keeps its `shortCircuits` flag, its `elementModel`, and its `relatedModel`.

These stay un-narrowed:

- A condition on another subject, or on another spelling of the same value, such as `$this->resource->x` tested
  against a `$this->x` arm.
- `&&`, a negation, or an `||` operand that is not an `instanceof` test on the arm.
- The false arm. `$x instanceof C ? null : $x` does not remove `C`.
- `?:`, whose true arm is the condition itself, and `??`, which has no condition.

This rule covers receiver resolution only: a ternary's own value is never narrowed, whatever its subject's spelling.
The value comes from `TernaryHandler`, which narrows only reads made inside the true arm. For a variable subject it
writes `varClassBindings`, which only `ReceiverClassResolver::fromVariable()` reads, so `$x->prop` in the arm narrows
while a bare `$x` does not. For `$this->resource` it narrows `modelClass`, which `$this->prop` reads resolve against.
So `$r instanceof Post ? $r : null` and `$this->imageable instanceof Post ? $this->imageable : null` both publish the
whole morph union as values, and so does a bare variable bound to either. See
[AST engine § Narrowing](ast-engine.md#narrowing).

## Attribute classes and morphTo bounds

`ModelAttributeResolver::resolveAttributeClass()` answers only for an attribute that `ModelInspector`
lists. A name typed only by an `@property` tag has no class to return. The answer is memoized per model
and attribute, including a `null` answer.

| Cast | Class |
| --- | --- |
| `attribute`, `accessor` | The getter closure's native return class. A getter with any native type is final, so `fn (): string` gives no class even when the `Attribute<Get, Set>` docblock names one. An untyped getter reads the docblock's `Get` class. An old-style `getXAttribute()` gives its native return class. |
| `date`, `datetime`, `custom_datetime` | `Illuminate\Support\Carbon`, because `Model::asDateTime()` returns it through the `Date` facade by default |
| `immutable_date`, `immutable_datetime`, `immutable_custom_datetime` | `Carbon\CarbonImmutable` |
| `timestamp` | None. `Model::asTimestamp()` returns an integer. |
| A `Castable` class, checked first as `HasAttributes::resolveCasterClass()` does | The `get()` native return class of the caster that `castUsing()` returns |
| A `CastsAttributes` class | Its `get()` native return class |
| An enum | The enum |

In every row, a native `static` names the class the value is read through and `self` names the class that
declared the type. For a getter closure, `static` is `ReflectionFunction::getClosureCalledClass()`, falling
back to the model, and `self` is `ReflectionFunction::getClosureScopeClass()`. The docblock `Get` class
follows the same generic rule as `returnClasses()`: `Attribute<Collection<int, User>[], never>` gives no
class.

`resolveMorphToBound()` reads the first argument of a `MorphTo<X, $this>` generic and returns `X` when it
is a single model class, abstract or `Model` included. Every other shape returns `Model::class`. The bound
exists only so a member can be reflected on it. The resolver never emits or imports it, which is why it
accepts the classes `morphToDocblockTargets()` rejects.

## What stays unresolved

The resolver returns `null` rather than a partial answer in these cases:

- An unbound variable. The ambient `closureRelationModelClass` is never used as a guess.
- A bare `$this`, which no rule above names. `ReceiverMethodCallHandler` handles a bare `$this->m()` on a resource
  through `forwardedThisReceiver()`, not through `resolve()`.
- A method or property whose type has a builtin arm, such as `: string`, `: array`, or `UrlService|string`.
- A protected or private member read on a receiver other than `$this`.
- `new self`, `new parent`, `self::m()`, or `parent::m()` when the subject has a user-land ancestor.
- A docblock part with text after its generic arguments, such as `Collection<int, User>[]`.
- A union where any arm cannot be resolved, such as `$this->author ?? $nobody`.
- A first-class callable such as `now(...)`, which holds a `Closure`.

A partial answer is refused because a handler reflects the next call on every class the receiver names.
If one arm of `User|<unknown>` is dropped, `->label()` is reflected on `User` alone. The published type
then claims more than the code guarantees, and nothing downstream can tell the missing arm existed.

## Following a method's return type

`AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler` claims `MethodCall`,
`NullsafeMethodCall`, and `StaticCall`. It asks `ReceiverClassResolver::resolve()` for the receiver of
`<receiver>->m()` and `<receiver>?->m()`, and `ReceiverClassResolver::resolveStaticReceiver()` for the class a
static call is made on. `resolveStaticClass()` answers a different question: the classes that call returns.
`ReceiverMethodReturnResolver::resolve()` then types the method on every class the receiver holds.

A bare `$this->m()` or `$this?->m()` has no receiver under `resolve()`, which names nothing for `$this`. On a
`JsonResource` subject that does not declare `m`, the handler asks `ReceiverClassResolver::forwardedThisReceiver()`
instead. That names the backing class, the same decision `$this->m()->next()` receiver resolution makes, because
`JsonResource::__call()` forwards the call to `$this->resource`. The call then types exactly as
`$this->resource->m()`: on a `Post` resource, `$this->getKey()` is `number` and `$this->fresh()` is `Post | null`.
A method the resource declares, including every `JsonResource` helper such as `whenLoaded()`, gets no forwarded
receiver and keeps `SubjectMethodTypeResolver`'s answer.

A model's own method or accessor body is analyzed with the model as its subject, so `$this` is the model and
nothing forwards. There the handler asks `ReceiverClassResolver::modelSubject()` for one kind of call only: an
`only()`/`except()`, which `RelationCollectionChainHandler` declines on a model-backed scope. It names the model
itself, so the [receiver rules](#receiver-rules) type `$this->only(...)` and `$this->except(...)` as they type the
filter on any other model receiver. What the rules may publish depends on the body:

- **An accessor getter body** carries its FQCN channels into the model file through `ResultTypeInfoBridge`, so it
  gets the full answer: `Pick<Release, 'major' | 'minor'>` for a list of columns, the inline shape otherwise, and
  `Record<string, unknown>` for a runtime key list.
- **A method body reached by the body fallback** carries none, so the rules publish the most specific answer that
  names no token: the inline shape, where a member whose type names a token is `unknown`. See
  [The body fallback carries no FQCN channel](#the-body-fallback-carries-no-fqcn-channel).

`Release::columnSummary()` and its `column_picks` accessor pin both bodies, read through `ReleaseColumnsResource`:
the method's `named` publishes `{ major: number; minor: number }` and the getter's publishes
`Pick<Release, 'major' | 'minor'>`.

`ReceiverMethodResource` in the workbench shows the owner's example, `$this->source->label()`, on each
receiver kind:

| Expression | Receiver | Published type |
| --- | --- | --- |
| `$this->priority->label()` | The `Priority` enum cast | `string` |
| `$this->resource?->priority?->label()` | The same, through two `?->` steps | `string \| null` |
| `$this->published_at->setTimezone('UTC')->toDateString()` | `Illuminate\Support\Carbon`, kept by `setTimezone(): static` | `string` |
| `$author?->getMorphClass()`, where `$author = $this->author` | `User`, through a local variable | `string \| null` |
| `$record::className()`, where `$record = $this->resource` | `Post`, as a static call's class | `string` |
| `Priority::from(1)->label()` | `Priority`, returned by `from(): static` | `string` |

### Receiver rules

`ReceiverMethodReturnResolver` checks three convention rules for each class before the order below. They read
the receiver's model rather than a signature, because Laravel declares all three loosely: `Model::getKey()`
returns `mixed`, `Collection::modelKeys()` returns `array<int, array-key>`, which reflects to
`(string | number)[]`, `Model::only()` returns `array<string, mixed>`, which reflects to the vague
`Record<string, unknown>`, and `Model::except()` returns a bare `array`, which reflects to the list `unknown[]`.
The filter rule also covers `Illuminate\Support\Collection`, whose `only()` and `except()` return `static`, which
reflects to the vague `unknown[] | Record<string, unknown>`.

| Call | Receiver | Published type |
| --- | --- | --- |
| `getKey()` | A concrete model that inherits `Model::getKey()` | `number` when `getKeyType()` is `int` or `integer`, else `string` |
| `getKey()` | `Model` itself, or an abstract model that inherits `Model::getKey()` | No rule. Reflection declines the inherited `mixed`. |
| `getKey()` | Any model that declares `getKey()` itself | No rule. Reflection publishes the override's own return, such as `getKey(): string`. |
| `only()`, `except()` | Any model that declares the filter itself with a return reflection types | No rule. Reflection publishes the override's own return, such as `only($attributes): string`, in a resource and an accessor body. Where the scope carries no import, `ReceiverMethodReturnResolver` spells it without a token, one top-level union arm at a time: a model, or a list of one, becomes the object that model serializes to, its columns and appended accessors, narrowed to the call's literal keys, or `Record<string, unknown>` for a runtime key list, a `null` arm stays, and any other token, such as an enum, leaves `unknown` (see [The body fallback carries no FQCN channel](#the-body-fallback-carries-no-fqcn-channel)). `RelationFilterHandler` declines a single relation or accessor to such a model for the same reason, and asks this resolver for the return it publishes on a map proxy's elements and a multi-model accessor's arm. An override whose return reflection cannot type is not this row: no declared return, as in `only($attributes)` returning `parent::only($attributes)`, or one too vague to publish, which is `: array`, `: ?array`, `: mixed`, `: iterable`, `: array\|string` or a docblock `@return array<string, mixed>`. It gets the two filter rows below, and so does a relation, accessor or map proxy to it. |
| `modelKeys()` | An Eloquent collection with `elementModel` set | The element model's key type as a list, `number[]` or `string[]` |
| `only([...])`, `except([...])` | A receiver holding exactly one model class, where the call carries a literal key list | Exactly what `RelationFilterHandler` builds for a relation to that model: `Pick<Model, …>` when every key is a published column, else the inline shape. Where the scope carries no import (`AnalysisScope::$carriesImports`), no `Pick<>`: the inline shape, where a member whose type names a token, such as an enum column, is `unknown`. A list that types no member, such as `only(['nope'])`, publishes `Record<string, unknown>`, because `Model::only()` still returns an attribute-keyed array (`['nope' => null]`); `RelationFilterHandler` declines that call on a single relation, so this row answers it there too |
| `only($keys)`, `except($keys)` with no literal key list, such as `only($request->input('fields'))` | A receiver holding exactly one model class, including a model subject's bare `$this` | `Record<string, unknown>` from `ResolvesFilteredRelationTypes::attributeRecordResult()`: whatever keys arrive at runtime, either filter returns an array keyed by attribute name. In `ProxyFilterDirectResource` and `ProxyFilterWrappedResource` this rule answers the own-model cells `fields_own` and `except_own` in both spellings; the relation cells `fields_author` and `except_author` get the same answer from `RelationFilterHandler`, which runs first and calls the same helper. |
| `only()`, `except()` | A class that runs `Illuminate\Support\Collection`'s own filter (`FiltersAttributeKeys::runsCollectionFilter()`), such as a method returning `Collection`, `collect([...])`, or an accessor typed `Attribute<Collection<string, int>, never>` or `Attribute<Collection<int, User>, never>` | `Record<string, unknown>` for any key list and any element type, from `attributeRecordResult()`: both filters keep the entries whose keys are listed. `RelationFilterHandler` answers a member holding one first, asking the same check before any element model the accessor names, and a column cast with `'collection'`, `'encrypted:collection'`, `AsCollection` or `AsEncryptedCollection` included, which no reflection names. `Eloquent\Collection` overrides both filters and is not this row: this resolver declines it, and `RelationFilterHandler` publishes an accessor holding one as a list of its models. |
| `only()`, `except()` | Any other receiver — a union, an Eloquent collection, or a `StaticCall`, which carries no key list this can read | No rule. Reflection then declines the vague `array`. `RelationFilterHandler` answers a many-relation filter spelled `$this->comments->only(...)` or `$this->resource->comments->only(...)` before this rule runs, but it matches only a plain property fetch: `$this->resource?->comments->only([1])` reaches this row and publishes `unknown`. |

The key type comes from `ModelAttributeResolver::getInstance()`, so `HasUuids`, `HasUlids`, and a
`#[Table(keyType: ...)]` attribute all count. Both `int` and `integer` map to `number`, because
`HasAttributes::getCasts()` casts an incrementing key as `getKeyType()`, and `castAttribute()` treats `int` and
`integer` alike. `AppliesKnownMethodRules::knownMethodRule()` and `ResolvesAuthHelperCalls::authMethodResult()` map
the key type the same way.

The `getKey()` rule applies only while `Model::getKey()` is the declaration that runs. A model that overrides it
declares its own return, and PHP holds every subclass to that return, so reflection types it soundly even on an
abstract model. The filter rules check much the same way, through
`ReceiverMethodReturnResolver::typesAsModelFilter()`, which `RelationFilterHandler` asks too. It answers yes when
`FiltersAttributeKeys::runsModelFilter()` finds `Model`'s own declaration, and also for an override whose return
reflection cannot type. So a model that overrides `only()` or
`except()` with a return reflection types, such as `: string`, keeps that return wherever the call is written,
spelled without a token where the scope carries no import. An override with no return reflection can read keeps the
filter answers instead, in a resource and in either body: one returning `parent::only($attributes)` with no declared
return, and one declaring a return too vague to publish, such as `: array`, which reflects to `unknown[]`.
When no instance can be built, no rule answers and the order below runs: `getKey()` then declines on `mixed`,
and `modelKeys()` keeps its reflected `(string | number)[]`.

A rule answers for the receiver's own model, never the subject's: `getKey()` on a `UuidPost` receiver is
`string` even when the subject is backed by the integer-keyed `Post`. The `getKey()`/`modelKeys()` rules name no
model token, so steps 3 and 7 have nothing to check. The filter rule does name one — `Pick<Post, …>` — so it
applies step 8's published-model check (`ValueResult::namesOnlyPublishedModels()`) itself, and declines rather
than emit a token no file backs.

| Expression in `ReceiverMethodResource` | Published type |
| --- | --- |
| `$author?->getKey()`, where `$author = $this->author` | `number \| null` |
| `$this->resource->author?->getKey()` | `number \| null` |
| `$this->comments->modelKeys()`, `$this->resource->comments->modelKeys()` | `number[]` |

`$this->resource->getKey()` still reaches `RelationCollectionChainHandler` first, whose
`AppliesKnownMethodRules::knownMethodRule()` reads the subject model's key type. For that receiver the subject
model and the receiver model are the same class, so the two answers agree.

### The order for one class

1. The method must exist. On a receiver other than `self::`, `static::`, or `parent::`, it must be public,
   by the same [visibility](#visibility) rule the resolver applies.
2. `Model::toArray()` declines. It serializes whichever relations happen to be loaded, which is runtime state
   that no declaration describes. Its `array<string, mixed>` docblock is also vague, so step 6 would decline it
   too; the explicit check keeps that true for any fallback that accepts a vague type.
3. When the declared return names a class that `toTsType()` publishes as `string` but `json_encode()` does not
   emit as a string, the call declines. `StringSerialization::isFalseString()` answers that for two kinds of class:
   - A `DateTimeInterface` implementation that is not `JsonSerializable`. `toTsType()` maps `DateTime` to
     `string`, but `json_encode()` writes it as a `{date, timezone_type, timezone}` object, so
     `$this->published_at->toDateTime()` stays `unknown`. Carbon implements `JsonSerializable` and serializes
     as its ISO string, so it keeps `string`.
   - A class `toTsType()` reads as `string` through `__toString()`. `json_encode()` ignores `__toString()`: a
     `CarbonInterval` serializes as an object. A class whose `jsonSerialize()` is declared `: string`, such as
     `Illuminate\Support\Stringable`, is exempt. A model or an `Arrayable` is never read through `__toString()`.

   `StringSerialization::methodReturnsFalseString()` checks every class the native type and the `@return` docblock
   spell, a list element such as `list<CarbonInterval>` included, with `self`, `static`, and `$this` resolved.
   `KnownMethodRuleHandler`'s request rule and `RelationCollectionChainHandler`'s date-cast arm use the same check.
4. When `returnClasses()` is exactly `[$class]`, from `static`, `$this`, or a `self` the class itself
   declares, the call keeps the receiver's own type: `toTsType($class)`, plus `| null` when the native return
   or the `@return` docblock admits `null`. `Model::fresh()` on a `User` is `User | null`. A `self` return
   inherited from a parent names the parent, so it is not treated as the receiver's type.
5. Otherwise `MethodReturnTypeResolver::resolve()` answers. It reads the native signature, then the
   `@return` docblock when the signature is vague, through
   `LaravelTsPublish::methodOrDocblockReturnTypes()`.
6. When that declaration is still vague — a bare `: array` reflects to `unknown[]` — the method body is
   analyzed once and the shape its literal return spells is used instead. `PriceQuoteService::quote()`
   declares only `: array`, and its body publishes
   `{ unit: string; minimum: number; discounted: { unit: string } }`. Four rules bound it:
   - The body must spell at least one property; otherwise the vague declaration is kept unchanged. A method
     that returns another call rather than a literal therefore stays `unknown[]`.
   - The built inline type must name no token an import would have to bring in
     (`TsTypeString::shapeValueHasUnimportableToken()`). The inline type carries no FQCN channel, so a
     token needing an import could never be emitted with one. See
     [Follow-ups](#the-body-fallback-carries-no-fqcn-channel).
   - A `class@method` already being analyzed returns nothing, so a method whose body calls itself
     terminates instead of recursing. The resolver is a container singleton so that guard is shared by
     every call site rather than per instance.
   - The result is still subject to steps 7 and 8, exactly as a reflected one is.
7. The type must not be vague. A vague type such as `unknown[]` would claim a list where an associative
   array, or a `keyBy()` collection, is a JSON object. An inline object type from step 6 is never vague.
8. Every model the result names must be one the package publishes a file for. `ReflectedTypeAcceptor` accepts
   any `Model` subclass, so a model under the `Illuminate\` namespace or an abstract model declines:
   `User::resolveRouteBinding()` reflects to `Model | null` and `newPivot()` to `Pivot`.

An int or class-constant array key is a real JSON object key, not a list index: `[1 => 'Basic']` encodes as
`{"1":"Basic"}`. `InspectsAstNodes::resolveKeyName()` reads both, resolving `self`, `static` and `parent` in a
constant key against the subject under analysis, and the key emits quoted because `1` is not a bare JS
identifier. So `PriceQuoteService::tierLabels()` publishes `{ "1": string; "2": string }`.

A **numeric** key is still dropped whenever the analyzed subject is a `JsonResource`. So `42 => $this->total`
in a resource publishes nothing, and `quirky-resource` pins that it must not. A published member name cannot
be numeric: `ResourceTransformer` keys its property maps by name as `array<string, …>`, and PHP stores a
numeric string array key as an `int`, so such a name arrives as an `int` where a `string` is declared. The
test is the *subject*, not whether that particular key becomes a member — a numeric key nested in an inline
array inside a resource, or in a resource's own helper reached by the body fallback, is dropped too. A helper
on a plain class keeps them, which is the only reason `PriceQuoteService` can publish `{ "1": string }` at all.

### The body fallback carries no FQCN channel

The inline type step 6 builds is a string with no accompanying enum/model FQCN channel, so a body whose shape
names an enum or a model cannot have that token imported. Rather than emit a token nothing imports, the
`shapeValueHasUnimportableToken()` rule drops the whole body answer and the vague declaration stands.

Attribute filters are the one kind of value that knows a token-free answer, so they give one instead of costing
the shape. `MethodReturnTypeResolver::bodyType()` calls `AstEngine::analyzeMethod()` with `carriesImports: false`,
which sets `AnalysisScope::$carriesImports` on the scope (an inherited body's parent analyzer copies it, and the
engine caches the analysis apart from one that keeps its channels). The filter code reads the flag:

- a literal key list on one model publishes its inline shape, never a `Pick<>`, and a member whose type names a
  token, such as an enum or class cast column or a relation to a model, is `unknown` there, so the other members keep
  their types. This covers `RelationFilterHandler` and the receiver rules alike;
- a runtime key list publishes `Record<string, unknown>`, as it does everywhere;
- a to-many relation filter publishes `unknown[]` instead of the relation read `Comment[]`;
- a map proxy publishes a list of that inline shape, spelled the same way;
- an override with a class-typed return, such as `only($attributes): static`, is spelled one top-level union arm at a
  time. An arm that is a model, or a list of one, becomes the object that model serializes to, narrowed to the call's
  literal keys. `ReceiverMethodReturnResolver::serializedModelShape()` takes the names
  `ModelAttributeResolver::serializedAttributeNames()` lists, those `toArray()` writes: the published columns, then the
  appended accessors (`$appends` or `#[Appends]`), each kept only when `$visible` lists it, if it lists any, and
  `$hidden` does not, whatever `exclude_hidden` says. So a hidden column or append, one `$visible` leaves out, and an
  accessor the model does not append are never named. It keeps the names `only()` lists, or every one but those
  `except()` lists, and spells a member naming a token `unknown`. A runtime key list, or keys selecting no such name,
  give `Record<string, unknown>`. A `null` arm stays; any other arm naming a token,
  such as an enum, makes the whole answer `unknown`. A model nested deeper is not mapped: a docblock
  `array{owner: User}` already reflects to `{ owner: unknown }`. `ReceiverMethodReturnResolver::resolve()` does this
  for every `only()`/`except()` it answers, so a relation, a map proxy (a list of the same spelling) and a
  multi-model accessor arm to such a model agree. A `?->` adds no `| null` to `unknown`, which TypeScript already
  reads as holding `null`.

`Comment::relationSummary()`, read through `CommentRelationFiltersResource`, pins the first three beside a typed
`id: number` sibling that the whole-shape rule used to drop with them, including `author_role: { id: number; role:
unknown } | null` for a `User` enum column; its `relation_picks` accessor holds the same filters and publishes the
full `Pick<User, …>` and `Comment[]` answers. `RelationFilterHandlerTest` pins the map proxy and a class cast column.
`ReceiverHandlersTest` pins the override through `ClassTypedFilterOverrideModel::filterFields()`, whose
`only(): ?static` and `except(): Priority` publish
`{ own: { id: number; name: string } | null; twin: { id: number } | null; rest: unknown; id: number }`, and pins the
serialized names through `HiddenFilterOverrideModel` (`$hidden` columns and a hidden append, an enum and a class
cast, appended accessors), `VisibleFilterOverrideModel` (`$visible` beside `$hidden`, a visible append, one outside
`$visible`, one named like a column), `UserFilterOverrideModel` (an override returning `User`) and
`AppendingModelFilterOverrideModel` (an override returning a model that appends).
Every other value in a body keeps the whole-shape rule.

A filter in the getter of an accessor the body reads is covered too. A model attribute read a scope makes for its value
passes the scope's flag to `ModelAttributeResolver::resolveAttribute()`: `$this->accessor` and its camelCase alias, a
relation chain, a local variable or closure parameter holding a model, a key that `only()` selects, and an appended
accessor of a spread or filtered model. One read keeps imports:
`ResolvesEnumPropertyArgTypes::resolveEnumFromPropertyArg()` resolves `EnumResource::make($author->role)` on a closure
parameter bound to a model, inside a `whenLoaded()` closure or a collection's `map()` closure, from the attribute's
first enum FQCN, a channel a spelling without imports can lose, and the value it builds names an enum the body
fallback drops anyway. When the accessor's type comes from its getter body,
`AccessorBodyAnalyzer` analyzes that getter without imports as well, so its filters publish exactly what they would
written in the method body, and the method keeps its shape. The model file and every resource still publish the getter's own analysis, with its
`Pick<User, …>` and `Comment[]`; see
[accessor-body-analyzer § A reader that carries no import](accessor-body-analyzer.md#a-reader-that-carries-no-import).
Those answers name a class, so the read that publishes one hands on the attribute's `classFqcns`, `enumFqcns` and
`customImports` through `ValueResult::withAttributeChannels()`, and its file imports the class. That covers
`$this->accessor` and its camelCase alias, a relation chain, with or without `?->` and rooted at `$this->resource` or
not, a `$resource` property a docblock types, a closure parameter in `map()` or `whenLoaded()`, typed or not, `pluck()`,
the attribute `whenHas()` or `whenAppended()` reads when it has no value to type, and every column and appended
accessor that `ResourceAstAnalyzer::analyzeModelSpreadArm()` flattens when a resource spreads a model's `toArray()`
among its top-level keys, unless the model's `#[TsCasts]` overrides that name. A local variable holding a model already
carried them. A key the resource's own `only()` or `except()` selects keeps only part of them:
`FiltersModelAttributes::filterAnalysisByKeys()` rebuilds the analysis from its properties and the name-keyed
`directEnumFqcns` and `modelFqcns`, so a multi-class attribute's FQCNs, every enum after the first and every
`#[TsType]` import are gone there. `ResourceTransformer::resolveMultiClassAccessorFqcns()` and
`resolveMultiEnumAccessorFqcns()` import the classes and enums back by name, because each key that filter keeps is an
attribute's own name. The first of them also restores each such key's `#[TsType]` import, before it skips a key whose
model FQCN the filter kept, and only when the key's type still spells the name. The `Stockroom` resources pin that for
an attribute naming one model and a `#[TsType]` class, through `$this->resource->only()`, `$this->resource->except()`,
`$this->only()`, a spread of it and `$this->except()`. The `Bulletin` resources pin each read beside a `Comment` or
`User` token, `WarehouseSettingsResource` pins `$this->menu_config` under a key of its own, whose `MenuSettingsType`
only the accessor's `#[TsType]` import supplies, and `WarehouseReviewHasResource` and `WarehouseReviewAppendedResource`
pin both enums of `review_priority`; see
[accessor-body-analyzer § A getter that reads another model's accessor](accessor-body-analyzer.md#a-getter-that-reads-another-models-accessor).
A resource `#[TsCasts]` override can replace the type such a read published. `ResourceTransformer::pruneOverriddenAnalysisImports()`
then drops each model and `#[TsType]` import the analysis carried whose name no property type or extends clause spells
any more, `pruneOverriddenEnumImports()` does the same for each enum, and `registerModelAttributeCustomImports()` keeps
a filtered key's `#[TsType]` import on the same test. All three ask `TsTypeString::typeNameOccursIn()`, a plain token
match over each type: the name counts wherever its token stands, inside a string, a template literal or a comment as
much as outside, because an import kept for a name only a literal spells is unused at worst, while a name a lexer hid
from the prune dropped an import the generated file needed. That cost is accepted: an override that spells an imported
class's name inside a quoted string, template text or comment keeps that import unused, which TypeScript reports only
in a project compiling with `noUnusedLocals`, as TS6196 for each unused name, or as one TS6192 when the line holds two
or more names and none is used; see
[known-gaps](../known-gaps.md#a-tscasts-value-that-spells-an-imported-name-inside-a-string-template-or-comment-keeps-the-import).
The token's boundaries are a keep-biased reading of TypeScript's: a character TypeScript reads as part of an identifier
(`$`, ZWNJ, ZWJ or Unicode ID_Continue, as the running PCRE2's tables know it) on either side of the name joins it, so
`CrmUser`, `a1User` and `User\u{e9}` are longer names and `foo.User`, `a1.User` and `Api.V2.User` member accesses, none
of them the type, while `[string, ...User[]]` references it. Which characters are ID_Continue follows the Unicode
tables of the PCRE2 that PHP runs on, plus U+30FB and U+FF65, which Unicode 15.1 added; a PCRE2 older than 10.40 has
no `\p{ID_Continue}` and gets the same set spelled from its general categories and the Other_ID code points. Every
`\u` escape is decoded to the character it names before the match, whatever those tables hold, so `\u{55}ser`
references `User` and `Us\u{30FB}er` spells `Us・er`; only an escape that names no code point (a surrogate, or past
U+10FFFF) stays as written. The departures that remain:

- **An import kept, unused at worst.** `import('x').User` counts, since only an identifier character before the dot
  marks a member access. A character TypeScript reads in an identifier but the running tables do not hold yet joins
  nothing, so it leaves the name beside it counted.
- **Only in syntax TypeScript rejects, so the file fails either way.** A name right after a numeric literal (`1User`,
  `0xUser`) is missed. A character the running tables hold but TypeScript rejects where it stands (TS1127) joins the
  name beside it: one Unicode 16.0 added, on a PCRE2 whose tables have it, or ZWNJ, ZWJ, a combining mark or an
  Other_ID_Continue character such as U+00B7 at the start of a name. A four-digit surrogate escape, or a `\u` with
  fewer than four digits, runs its digits into the name after it. An escape of a character no identifier holds, or
  of an identifier character TypeScript rejects where it stands, is decoded all the same, so the names TypeScript's
  recovery makes of it are missed: `u002EUser` for `\u002EUser`, `u00B7User` for `\u00B7User`, and `u` and `B7`
  for `\u{B7}`.

`BulletinCastResource` overrides a `Comment` read and a `User` read and keeps only `User`, which its unoverridden
`owner_list` still names. `ResourceTransformerTest` pins, over a model, an enum and a `#[TsType]` read each, a name
after a line continuation, after a `//` comment ended by CR, U+2028 or U+2029, in a variadic tuple element, through an
identifier escape, after a multi-line template literal or a block comment holding a quote, and across two values
TypeScript lexes as one; a name an extends clause alone spells, over a model read and an enum read, and over a
`#[TsType]` read that `$this->only()` selects; and a name a string literal alone spells, over a model read and a
`#[TsType]` read. The prune reads class basenames before aliasing, so two models that share a basename both stay
imported while either is still spelled, and overriding the only read of one of them leaves its aliased import unused.
That leftover predates the prune; it is the same at
`7a55703d`.
`Comment::picksSummary()` reads the `relation_picks` accessor, and `CommentRelationFiltersResource` publishes its
`picks` as the same object `relationSummary()` publishes. `FilteringAccessorModel` pins each getter kind and read
position in `MethodReturnTypeResolverTest`.

The rule reaches the getter body step and the `@property` refinement of its spelling. An accessor whose type names a
class any other way still costs the reading method its whole shape: one typed by its closure signature, its
`Attribute<>` docblock such as `Attribute<User, never>`, an old-style getter's own return type or `@return` docblock
such as `/** @return list<Comment> */ getReplyListAttribute(): array`, or an `@property` tag, and one whose getter
returns a value that names a class without a filter, such as `fn () => $this->author`.

### Unions, `?->`, and requests

A receiver holding several classes types the method on each one and merges the answers with
`ValueResult::mergeUnion()`. When any class declines, the whole call declines, for the reason given under
[What stays unresolved](#what-stays-unresolved).

The call gains `| null` when it is itself `?->`, or when the receiver's `shortCircuits` flag records an
earlier `?->` in the chain. `$this?->m()` is the exception: `$this` is never null, so it adds nothing. So `$this->resource?->author->getMorphClass()` is `string | null`. `null` is
never added twice, and never added to `unknown`.

A receiver that holds an `Illuminate\Http\Request` declines. `KnownMethodRuleHandler` owns request calls:
it reads a form request's rules for `validated()`, the auth guard's model for `user()`, and refuses returns
that do not serialize as reflected.

### Which handlers step aside

The handler sits last before `KnownMethodRuleHandler`, so every more specific handler answers first. These
earlier claimants used to floor or misread calls it can answer, and now decline instead:

- `RelationCollectionChainHandler`'s `$this->anyProp->method()` branch declines when it would answer `unknown`.
  On a model-backed scope every branch of the handler also declines `only()`/`except()`, whatever the key list:
  reflection reads `except()`'s `@return array` as `unknown[]`, and a many-relation's filter keeps models by
  primary key.
- `RelationFilterHandler` does not claim a filter on `$this->resource` itself, and its relation arm declines a
  filter it cannot type. It used to claim both as `unknown`.
- `VariableHandler`'s `$variable->method()` arm skips every `only()`/`except()`, for the same reasons.
- `MethodChainHandler` declines every `only()`/`except()`. It also declines when its answer is only `unknown` once
  `null` arms are removed. A `static|null` docblock such as `Model::fresh()`'s reflects to `unknown | null`, and
  flooring there made `$this->author?->fresh()` disagree with `$this->author->fresh()`; both are now
  `User | null`. It also declines when the last step of the chain is not a relation. It used to reflect the method on the model that declares
  the step, which is the wrong receiver: `$this->imageable?->getTable()` read `Image::getTable()`.
- `StaticCallHandler` declines a static call whose class is an expression, such as `$record::className()`.

## Property access on a receiver

`AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler` claims `PropertyFetch` and
`NullsafePropertyFetch`, and does for a property read what `ReceiverMethodCallHandler` does for a call: it
asks `ReceiverClassResolver::resolve()` what the receiver holds, then types the property on every class that
answer names. So `$post?->title` is `string | null` once `$post` is known to hold a `Post`, where it used to
be `unknown`.

A `$this->prop` leaf declines outright. That read is `ThisPropertyHandler`'s, which consults the subject's own
declaration before the model; `$this->resource->prop` is a receiver read like any other, and reaches here.
Which properties count as the subject's own, why a framework name such as `resource` never does, and the
resolution order behind them are in [AST engine § Subject mode](ast-engine.md#subject-mode).

How one class types the property:

| Receiver class | Rule |
| --- | --- |
| A `Model` | `ModelAttributeResolver::resolveAttribute()` first, then `resolveRelation()` — attributes before relations, the order `Model::__get()` itself uses. A relation carries its `modelFqcn`, and a morph union its `morphFqcns`, so the emitted token keeps its import. |
| Anything else | `SubjectPropertyTypeResolver::resolve()` — the `@var` docblock first, the native declared type second (accepted through `ReflectedTypeAcceptor`), an untyped property's literal default third. The same three steps, in the same order, as [AST engine § Subject mode](ast-engine.md#subject-mode). |

A reflected property then faces the same two declines a method return does, for the same reasons given under
[the order for one class](#the-order-for-one-class): the property must not hold a class
`StringSerialization::isFalseString()` rejects, so a plain `DateTime` property is not published as the `string`
`toTsType()` maps it to, and it must name only models the package publishes a file for, so a property typed
`Model` declines rather than emitting a token nothing imports.

Both declines are decided **per receiver class**, on the classes `ReceiverClassResolver::memberProperty()`
reports for that one class's property — which is why that method is public. Asking `resolve()` about the whole
expression would answer `null` for a union as soon as one arm's property holds a builtin, and `null` reads as
"no false string here", so an `A|B` receiver whose `A::$p` is a raw `DateTime` and whose `B::$p` is a `string`
would publish `A`'s arm as `string`.

A receiver holding several classes types the property on each and merges the answers with
`ValueResult::mergeUnion()`; one declining arm declines the whole read, for the reason under
[What stays unresolved](#what-stays-unresolved). The read gains `| null` when it is itself `?->` or when the
receiver's `shortCircuits` flag records an earlier `?->`, and never twice.

`ReceiverPropertyResource` in the workbench writes each expression through a local variable bound to
`$this->post` and again through one bound to `$this->resource->post`, and the two spellings publish the same
type:

| Expression, where `$post = $this->post` | Twin, where `$resourcePost = $this->resource->post` | Published type |
| --- | --- | --- |
| `$post?->title` | `$resourcePost?->title` | `string \| null` |
| `$post?->published_at` | `$resourcePost?->published_at` | `string \| null` |
| `$post?->author?->name` | `$resourcePost?->author?->name` | `string \| null` |
| `$post->title` | `$resourcePost->title` | `string` |
| `$this->post?->title` | `$this->resource->post?->title` | `string \| null` |

### Which handlers step aside for it

`PropertyChainHandler` claims both node classes ahead of it and used to floor what it could not type at
`unknown`: its `NullsafePropertyFetch` arm returned that answer unconditionally, and its
`$this->anyProp->subProp` arm ended in an unguarded return. Both now decline when the answer is only `unknown`
once its `null` arms are removed — `TsTypeString::isUnknownOnly()`, the same test `MethodChainHandler` applies
to a nullsafe call chain. A chain the handler really types, including every enum `->name`/`->value` read on a
wrapped resource, still answers first and is unaffected.

`VariableHandler` claims `PropertyFetch` earlier still, but only for a variable bound to a model in
`varModelBindings` — a `whenLoaded` closure parameter, a `map()` parameter, a `foreach` value. A variable bound
by a plain `$post = $this->post;` assignment lives in `localVarBindings`, which that branch does not read, so it
declines and the receiver rules above answer.
