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
| `$var` | `varModelBindings`, then `varCollectionBindings` (a collection with `elementModel`), then `requestVarNames`, then `closureParamExprBindings` and `localVarBindings` resolved recursively under `resolvingLocalVars`. An unbound variable is `null`. |
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
| A ternary, `?:`, or `??` | The union of the non-`null` arms. Any unresolved non-`null` arm makes the whole answer `null`. |

`$this->prop` and `$this->resource->prop` share one code path. A `JsonResource` forwards what it does not
declare to its model through `__get()` and `__call()`, so the two spellings are the same read at runtime.
The one asymmetry is deliberate: a property the resource declares itself answers only the `$this->prop`
spelling, because PHP reads a declared property before `__get()` runs, and `$this->resource->prop` always
reads the model.

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

`ReceiverMethodReturnResolver` checks two convention rules for each class before the order below. They read
the receiver's model rather than a signature, because Laravel declares both methods loosely: `Model::getKey()`
returns `mixed`, and `Collection::modelKeys()` returns `array<int, array-key>`, which reflects to
`(string | number)[]`.

| Call | Receiver | Published type |
| --- | --- | --- |
| `getKey()` | A concrete model that inherits `Model::getKey()` | `number` when `getKeyType()` is `int` or `integer`, else `string` |
| `getKey()` | `Model` itself, or an abstract model that inherits `Model::getKey()` | No rule. Reflection declines the inherited `mixed`. |
| `getKey()` | Any model that declares `getKey()` itself | No rule. Reflection publishes the override's own return, such as `getKey(): string`. |
| `modelKeys()` | An Eloquent collection with `elementModel` set | The element model's key type as a list, `number[]` or `string[]` |

The key type comes from `ModelAttributeResolver::getInstance()`, so `HasUuids`, `HasUlids`, and a
`#[Table(keyType: ...)]` attribute all count. Both `int` and `integer` map to `number`, because
`HasAttributes::getCasts()` casts an incrementing key as `getKeyType()`, and `castAttribute()` treats `int` and
`integer` alike. `AppliesKnownMethodRules::knownMethodRule()` and `ResolvesAuthHelperCalls::authMethodResult()` map
the key type the same way.

The `getKey()` rule applies only while `Model::getKey()` is the declaration that runs. A model that overrides it
declares its own return, and PHP holds every subclass to that return, so reflection types it soundly even on an
abstract model.
When no instance can be built, no rule answers and the order below runs: `getKey()` then declines on `mixed`,
and `modelKeys()` keeps its reflected `(string | number)[]`.

A rule answers for the receiver's own model, never the subject's: `getKey()` on a `UuidPost` receiver is
`string` even when the subject is backed by the integer-keyed `Post`. The rules name no model token, so steps 3
and 7 have nothing to check.

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
5. Otherwise `LaravelTsPublish::methodOrDocblockReturnTypes()` reads the native signature, then the
   `@return` docblock when the signature is vague.
6. `ReflectedTypeAcceptor::accept()` must accept the type, and it must not be vague. A vague type such as
   `unknown[]` would claim a list where an associative array, or a `keyBy()` collection, is a JSON object.
7. Every model the result names must be one the package publishes a file for. `ReflectedTypeAcceptor` accepts
   any `Model` subclass, so a model under the `Illuminate\` namespace or an abstract model declines:
   `User::resolveRouteBinding()` reflects to `Model | null` and `newPivot()` to `Pivot`.

### Unions, `?->`, and requests

A receiver holding several classes types the method on each one and merges the answers with
`ValueResult::mergeUnion()`. When any class declines, the whole call declines, for the reason given under
[What stays unresolved](#what-stays-unresolved).

The call gains `| null` when it is itself `?->`, or when the receiver's `shortCircuits` flag records an
earlier `?->` in the chain. `$this?->m()` is the exception: `$this` is never null, so it adds nothing. So `$this->resource?->author->getMorphClass()` is `string | null`. `null` is
never added twice.

A receiver that holds an `Illuminate\Http\Request` declines. `KnownMethodRuleHandler` owns request calls:
it reads a form request's rules for `validated()`, the auth guard's model for `user()`, and refuses returns
that do not serialize as reflected.

### Which handlers step aside

The handler sits last before `KnownMethodRuleHandler`, so every more specific handler answers first. Three
earlier claimants used to floor these calls at `unknown`, and now decline instead:

- `RelationCollectionChainHandler`'s `$this->anyProp->method()` branch declines when it would answer `unknown`.
- `MethodChainHandler` declines when its answer is only `unknown` once `null` arms are removed. A `static|null`
  docblock such as `Model::fresh()`'s reflects to `unknown | null`, and flooring there made
  `$this->author?->fresh()` disagree with `$this->author->fresh()`; both are now `User | null`. It also declines
  when the last step of the chain is not a relation. It used to reflect the method on the model that declares
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
| Anything else | `SubjectPropertyTypeResolver::resolve()` — the `@var` docblock first, the native declared type second, accepted through `ReflectedTypeAcceptor`. |

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
