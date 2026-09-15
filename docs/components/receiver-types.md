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
| `$this->resource` | `$scope->modelClass ?? $scope->instanceOfWrappedClass` |
| `$this->resource->prop`, `$this->resource?->prop` | The model-member rules `$this->prop` uses on a model-backed subject. The subject-declared property rule never applies. |
| `$this->prop`, with `prop` declared on the subject below any `Illuminate\` ancestor, at any visibility | Its native class type, else its full `@var` type |
| `$this->prop` on a model-backed subject | `ModelAttributeResolver::resolveAttributeClass()`, else the relation: its morph targets or `resolveMorphToBound()`, `[EloquentCollection]` with `elementModel` for to-many, or the related model |
| `$var` | `varModelBindings`, then `varCollectionBindings` (a collection with `elementModel`), then `requestVarNames`, then `closureParamExprBindings` and `localVarBindings` resolved recursively under `resolvingLocalVars`. An unbound variable is `null`. |
| `<receiver>->prop`, `<receiver>?->prop` | For a model class, the attribute and relation rules above. For any other class, its declared **public** property's class. |
| `$this->method()` | The subject's own method, at any visibility. When the subject is a `JsonResource` that does not declare it, the backing class's **public** method. |
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
- A bare `$this`, which no rule above names.
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

### The order for one class

1. The method must exist. On a receiver other than `self::`, `static::`, or `parent::`, it must be public,
   by the same [visibility](#visibility) rule the resolver applies.
2. `Model::toArray()` declines. It serializes whichever relations happen to be loaded, which is runtime state
   that no declaration describes. Its `array<string, mixed>` docblock is also vague, so step 6 would decline it
   too; the explicit check keeps that true for any fallback that accepts a vague type.
3. When the declared return names a class that `toTsType()` would publish as `string` only because it has
   `__toString()`, the call declines. `json_encode()` ignores `__toString()`: a `CarbonInterval` serializes
   as an object. Two kinds of class are exempt, because `json_encode()` does produce a string for them:
   `DateTimeInterface` classes that implement `JsonSerializable` (Carbon), and classes whose `jsonSerialize()`
   is declared `: string` (`Illuminate\Support\Stringable`). Both the native type and the `@return` docblock
   are checked, with `self`, `static`, and `$this` resolved.
4. When `returnClasses()` is exactly `[$class]`, from `static`, `$this`, or a `self` the class itself
   declares, the call keeps the receiver's own type: `toTsType($class)`, plus `| null` when the native return
   or the `@return` docblock admits `null`. `Model::fresh()` on a `User` is `User | null`. A `self` return
   inherited from a parent names the parent, so it is not treated as the receiver's type.
5. Otherwise `LaravelTsPublish::methodOrDocblockReturnTypes()` reads the native signature, then the
   `@return` docblock when the signature is vague.
6. `ReflectedTypeAcceptor::accept()` must accept the type, and it must not be vague. A vague type such as
   `unknown[]` would claim a list where an associative array, or a `keyBy()` collection, is a JSON object.

### Unions, `?->`, and requests

A receiver holding several classes types the method on each one and merges the answers with
`ValueResult::mergeUnion()`. When any class declines, the whole call declines, for the reason given under
[What stays unresolved](#what-stays-unresolved).

The call gains `| null` when it is itself `?->`, or when the receiver's `shortCircuits` flag records an
earlier `?->` in the chain. So `$this->resource?->author->getMorphClass()` is `string | null`. `null` is
never added twice.

A receiver that holds an `Illuminate\Http\Request` declines. `KnownMethodRuleHandler` owns request calls:
it reads a form request's rules for `validated()`, the auth guard's model for `user()`, and refuses returns
that do not serialize as reflected.

### Which handlers step aside

The handler sits last before `KnownMethodRuleHandler`, so every more specific handler answers first. Three
earlier claimants used to floor these calls at `unknown`, and now decline instead:

- `RelationCollectionChainHandler`'s `$this->anyProp->method()` branch declines when it would answer `unknown`.
- `MethodChainHandler` declines when it would answer `unknown`. It also declines when the last step of the
  chain is not a relation. It used to reflect the method on the model that declares the step, which is the
  wrong receiver: `$this->imageable?->getTable()` read `Image::getTable()`.
- `StaticCallHandler` declines a static call whose class is an expression, such as `$record::className()`.
