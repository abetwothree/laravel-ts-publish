# Receiver types

[`ReceiverClassResolver`] tells the [AST engine](ast-engine.md) which PHP class, or classes, an expression holds.
[`ReceiverMethodCallHandler`] and [`ReceiverPropertyFetchHandler`] ask it about the receiver of `$x->m()` and
`$x->prop`, then type the member on each class it names. The answer names PHP classes, never TypeScript types, because
the next step reflects a method or property on the class, and a type string such as `StatusType` cannot be reflected.

## Where things live

These classes carry receiver inference:

| Class | Owns |
| --- | --- |
| [`ReceiverClassResolver`] | The classes an expression holds, returned as a `ReceiverType` |
| [`ReceiverType`] | The answer: `classes`, a `shortCircuits` flag after a `?->`, and the `elementModel` or `relatedModel` a collection or relation carries |
| [`ReceiverMethodCallHandler`] | `$x->m()`, `$x?->m()` and `$expr::m()`, including a bare `$this->m()` a resource forwards |
| [`ReceiverPropertyFetchHandler`] | `$x->prop` and `$x?->prop` |
| [`ReceiverMethodReturnResolver`] | The convention rules, then [the order for one class](#the-order-for-one-class) |
| [`MethodReturnTypeResolver`] | A method's declared return, then the [body fallback](#the-body-fallback-carries-no-fqcn-channel) |
| [`ReadsInstanceofChains`], [`NarrowsInstanceofSubjects`] | What an `instanceof` condition proves, and the binding its proven arm resolves under |

## Receiver resolution

`ReceiverClassResolver::resolve()` dispatches on the node class, and its class docblock points here for the rules. Any
part it cannot name makes the whole answer `null`, never a partial one. A handler reflects the next member on every
class the receiver names, so dropping one arm of `User|<unknown>` would publish a type that claims more than the code
guarantees.

| Expression | Receiver |
| --- | --- |
| `$this->resource` | The subject's own redeclaration when it names a class, such as `/** @var MediaType\|null */ public $resource`, else the backing model from `AnalysisScope::$forwardsUndeclaredMembersTo` |
| `$this->prop` the subject declares, by `SubjectPropertyTypeResolver::declaresOwnProperty()` | Its native class type, else its full `@var` type, at any visibility |
| `$this->prop` on a model-backed subject, and `$this->resource->prop` | `ModelAttributeResolver::resolveAttributeClass()`, else the relation: its `morphTo` targets or `resolveMorphToBound()`, an `Eloquent\Collection` with `elementModel` for a to-many relation, or the related model |
| `$var` | The scope's bindings, in the order under [Variables](#variables) |
| `<receiver>->prop`, `<receiver>?->prop` | A model's attribute or relation, as above. On any other class, a public, non-static property's class. |
| `$this->m()` | The subject's own method, at any visibility. On a `JsonResource` that does not declare it, the backing class's public method. |
| `<model>->relation()` | `returnClasses()`, or `Relation` when the method declares nothing, with `relatedModel` set, which `->getRelated()` then names |
| `<model>->getRelation('name')` | The relation as loaded, by the relation rule above, since an unloaded name throws |
| `$request->user()` | `AuthUserResolver::model()`, when that is not `null` |
| `<receiver>->m()`, `<receiver>?->m()` otherwise | `returnClasses()` on every class, for a public method |
| `static::m()`, `self::m()`, `parent::m()` | The subject, or its framework parent for `parent`, then `returnClasses()`, at any visibility |
| `X::m()`, `$var::m()`, `$this->resource::m()` | The class, then `returnClasses()`, for a public method |
| `new X`, `new static`, `new self`, `new parent` | `X`, the subject for `static` and `self`, or its framework parent for `parent` |
| `resolve(X::class)`, `app(X::class)` | `X` |
| `now()`, `today()`, `collect(...)` | `Illuminate\Support\Carbon`, or `Illuminate\Support\Collection` |
| A ternary, `?:` or `??` | The union of the non-`null` arms. See [A ternary's `instanceof` condition](#a-ternarys-instanceof-condition). |

A type with a builtin arm names no class, such as `: string` or `@var UrlService|string`. Neither does a docblock part
with text after its generics: `Collection<int, User>` names `Collection`, while `Collection<int, User>[]` is an array.

`$this->prop` and `$this->resource->prop` share one code path, because a `JsonResource` forwards what it does not
declare through `__get()` and `__call()`, so the two are the same read at runtime. The one asymmetry is deliberate. A
property the resource declares answers only the `$this->prop` spelling, because PHP reads a declared property before
`__get()` runs. Attribute filters follow the same rule. See
[ResourceAstAnalyzer § `$this->resource` spells the same filter][resource-filter].

### Variables

`ReceiverClassResolver::fromVariable()` reads the scope's tables in this order, and the first that binds the name
answers:

1. `varClassBindings`: a ternary's `instanceof` narrowing, or a `morphTo` `whenLoaded()` parameter's targets.
2. `varGuardBindings`: an early-exit guard's class, for a read past the guard.
3. `varDocBindings`: in an inline `@var` span, the assigned value's classes, or the `@var`'s when the value names none
   or only models with no published file. See [AST engine § Declared locals](ast-engine.md#declared-locals).
4. `varModelBindings`, `varCollectionBindings` as an `Eloquent\Collection` with `elementModel`, then `requestVarNames`.
5. `closureParamExprBindings`, else `localVarBindings`, resolved recursively under the `resolvingLocalVars` guard.

The narrowing tables come first because a narrowed variable is usually also a plain `$x = …;` local, which step 5 would
read unnarrowed. Ranking them above `varModelBindings` guards the same case for a closure parameter or loop variable, a
case no fixture exercises. An unbound variable is `null`, and the ambient `closureRelationModelClass` is never used as a
guess. [AST engine § Narrowing](ast-engine.md#narrowing) covers what writes the first two tables.

### `self` and `parent`

PHP binds `self` and `parent` to the class that declares the method body, and the engine can analyze an inherited body
under a child subject. `AnalysisScope::$declaringFileClass` cannot stand in for that class, because it tracks files for
inline `@var` imports. It names a trait for a trait's method, and the main `analyze()` path leaves it as the subject. So
`new self`, `new parent`, `self::m()` and `parent::m()` resolve only when the subject's parent is absent or under
`Illuminate\`, where every body analyzed under the subject is its own or a trait's. `static` always names the subject,
since late static binding follows the object.

### Visibility

On `$this`, or through `self::`, `static::` or `parent::`, a member counts at any visibility. On any other receiver only
a public member counts, and only a non-static property. `method_exists()` and `property_exists()` also match protected,
private and static members, but PHP sends an outside read of one to `__call()` or `__get()`, never to the declaration.

### A ternary's `instanceof` condition

`ReadsInstanceofChains::instanceofProof()` reads a ternary whose condition is an `instanceof` test, or an `||` chain of
them, on one read path. The true arm runs only when some operand holds, so it is the arm the test proves, and a negated
test proves the false arm. One read path means the same variable, or the same chain of property reads with the same
names and the same `->` or `?->` at each step. A method call never qualifies, because a second call may return a
different value from the one the test saw.

When the proven arm is the tested read itself, `narrowed()` narrows each class `R` it resolves to against the tested
classes `T`. The first case that matches decides, whatever order the tests are written in:

1. `R` is a `T`, or a subtype of one: `R` stays.
2. Some `T` is unrelated to `R` by inheritance, and `R` or that `T` is an interface: `R` stays. A subtype of `R` may
   pass that test without being any other `T`.
3. Some `T` is a subtype of `R`, as when `R` is a morph bound such as `Model`: those `T` replace `R`.
4. Otherwise no object is both, and `R` is dropped.

A test never widens a class the arm already names. When no class survives, the arm can never run and keeps what it
resolved to, and when it resolves to nothing, it holds the tested classes. When the proven arm instead reads a member
through the tested subject, as in `$this->resource instanceof SubscribedTeam ? $this->resource->subscriber : null`,
`provenArm()` resolves it under `NarrowsInstanceofSubjects::resolveNarrowed()`, the binding `TernaryHandler` uses.

These stay un-narrowed:

- A condition on another subject, or on another spelling of the same value, such as `$this->resource->x` tested against
  a `$this->x` arm.
- `&&`, a negation inside an `||` chain, or an `||` operand that is not an `instanceof` test on the arm.
- The arm the test does not prove, so `$x instanceof C ? null : $x` does not remove `C`.
- A member read through a `$this->prop` subject, or through `$this->resource` tested for more than one class.
- `?:` and `??`, which prove nothing.

The rule narrows receivers only. `TernaryHandler` narrows reads made inside the proven arm, so `$x->prop` there narrows,
while the ternary's own value and a bare `$x` do not. In `NarrowedImageableResource`, `Image::imageable` is a `morphTo`
over int-keyed and string-keyed models. `$either` is bound to
`$this->imageable instanceof Post || $this->imageable instanceof User ? $this->imageable : null`, so
`$either?->getKey()` publishes `either_id: number | null`, while `$this->imageable?->getKey()` publishes
`open_id: number | string | null`.

### Attribute classes and `morphTo` bounds

`ModelAttributeResolver::resolveAttributeClass()` names the class a model attribute holds, and its docblocks carry the
rule for each cast. It answers only for an attribute the model inspector lists, so a name only an `@property` tag types
has none. A getter with any native return type is final: `fn (): string` gives no class even when the
`Attribute<Get, Set>` docblock names one.

`resolveMorphToBound()` gives a `morphTo` the `X` of its `MorphTo<X, $this>` generic when `X` is one model class,
abstract or `Model` included, and `Model` otherwise. The bound exists only so a member can be reflected on it. It is
never emitted or imported, so it can accept the classes `morphToDocblockTargets()` rejects.

## Following a method's return type

`ReceiverMethodCallHandler` claims `MethodCall`, `NullsafeMethodCall` and `StaticCall`. It asks
`ReceiverClassResolver::resolve()` for a call's receiver, or `resolveStaticReceiver()` for the class a static call is
made on. `ReceiverMethodReturnResolver::resolve()` then types the method on every class the receiver holds and merges
the answers. When any class declines, the whole call declines.

Two receivers come from outside `resolve()`, which names nothing for a bare `$this`:

- **A resource forwarding a call**: on a `JsonResource` subject that does not declare `m`, `forwardedThisReceiver()`
  names the backing class, since `JsonResource::__call()` forwards the call. On a `Post` resource, `$this->getKey()` is
  `number` and `$this->fresh()` is `Post | null`. A method the resource declares, including every `JsonResource` helper
  such as `whenLoaded()`, keeps `SubjectMethodTypeResolver`'s answer.
- **A model's own body**: a model method or accessor body has the model as its subject, so nothing forwards.
  `ReceiverClassResolver::modelSubject()` names the model for `only()` and `except()` alone, which
  `RelationCollectionChainHandler` declines on a model-backed scope.

A receiver holding an `Illuminate\Http\Request` declines, because `KnownMethodRuleHandler` owns request calls. The call
gains `| null` for its own `?->` or an earlier one its receiver's `shortCircuits` flag records. The call never gains
`| null` twice, never on `unknown`, and never for `$this?->m()`, since `$this` is never `null`.

Earlier claimants of these node classes decline a call they cannot type, as
[AST engine § A handler declines what it cannot type][declines] requires, so this handler gets to answer.
[The honest ordering inventory][inventory] records the declines that settle each pair.

### Receiver rules

`ReceiverMethodReturnResolver` checks three convention rules on each class before
[the order for one class](#the-order-for-one-class). They read the receiver's model rather than a signature, because
Laravel declares all three loosely. `Model::getKey()` returns `mixed`, `Collection::modelKeys()` reflects to
`(string | number)[]`, `Model::only()` to `Record<string, unknown>`, and `Model::except()` to the list `unknown[]`. Each
rule answers only on the receiver it names:

- **`getKey()`**: on a concrete model that inherits `Model::getKey()`, the key type from
  `ModelAttributeResolver::keyTsType()`, so `HasUuids`, `HasUlids` and `#[Table(keyType: ...)]` count. `Model` itself
  and an abstract model get no rule, so reflection declines the inherited `mixed`. An override gets no rule either,
  because PHP holds every subclass to its declared return, which reflection types.
- **`modelKeys()`**: on an `Eloquent\Collection` with `elementModel` set, the element model's key type as a list.
- **`only()`, `except()`**: a class that runs `Support\Collection`'s own filter publishes `Record<string, unknown>` for
  any key list. A receiver holding exactly one model whose filter is `Model`'s own, or an override reflection cannot
  type, gets what `RelationFilterHandler` builds for a relation to that model. The answer is a `Pick<>` when every
  literal key is a published column, else the inline shape. A runtime key list, or a literal list that types no member,
  gives `Record<string, unknown>`.

A union, an `Eloquent\Collection` and a static call get no filter rule, so `$this->resource?->comments->only([1])`,
which `RelationFilterHandler` does not match, publishes `unknown`. A model whose `only()` or `except()` override has a
return reflection types, such as `: string`, keeps that return wherever the call is written. `RelationFilterHandler`
asks the same `typesAsModelFilter()`, so a relation, accessor or map proxy to such a model agrees.

A rule answers for the receiver's own model, never the subject's, so `getKey()` on a `UuidPost` receiver is `string`
even when the subject is backed by the integer-keyed `Post`. The filter rule names a model token, so it applies the
published-model check of step 7 itself. When no model instance can be built, the key rules do not answer, so `getKey()`
declines on `mixed` and `modelKeys()` keeps its reflected `(string | number)[]`.

### The order for one class

When no convention rule answers, `ReceiverMethodReturnResolver` types the method on one class in this order, and any
step can decline:

1. The method must exist. On a receiver other than `self::`, `static::` or `parent::`, it must be public, by the
   [visibility](#visibility) rule.
2. `Model::toArray()` declines. Its output depends on which relations are loaded, and no declaration describes that
   runtime state. See [known gaps][gap-to-array].
3. A declared return naming a class that `toTsType()` publishes as `string` but `json_encode()` does not declines, such
   as a plain `DateTime` or `CarbonInterval`. `StringSerialization::methodReturnsFalseString()` decides, and
   [Support helpers § `StringSerialization`][string-serialization] explains why.
4. When `returnClasses()` is exactly the receiver's own class, from `static`, `$this` or a `self` the class declares,
   the call keeps the receiver's own type. It adds `| null` when the return admits `null`, so `Model::fresh()` on a
   `User` is `User | null`.
5. Otherwise `MethodReturnTypeResolver::resolve()` answers: the native signature, then the `@return` docblock when the
   signature is vague, then [the body fallback](#the-body-fallback-carries-no-fqcn-channel).
6. A vague result declines. `unknown[]` would claim a list where an associative array or a `keyBy()` collection is a
   JSON object.
7. Every model the result names must have a published file, by `ValueResult::namesOnlyPublishedModels()`. A model under
   `Illuminate\`, or an abstract one, declines, so `User::resolveRouteBinding()`'s `Model | null` does too.

### The body fallback carries no FQCN channel

When a declared return is too vague to publish, such as a bare `: array`, `MethodReturnTypeResolver` analyzes the method
body once. The shape its literal return spells replaces the declaration's array arms, so `PriceQuoteService::quote()`,
which declares only `: array`, publishes `{ unit: string; minimum: number; discounted: { unit: string } }`.

The resolver's docblocks carry the bounds. The shape replaces only the array arms, so `?array` gives `{…} | null`, and a
return the analysis does not read must be a literal a declared arm covers. A `class@method` already under analysis
returns nothing, through a guard in [`AnalysisMemo`](ast-engine.md#the-run-memo-replays-what-it-recorded), so a body
that calls itself terminates. An int key publishes quoted, as `PriceQuoteService::tierLabels()`'s
`{ "1": string; "2": string }` shows. When the analyzed subject is a `JsonResource`,
`InspectsAstNodes::publishableKeyName()` drops a numeric key instead, even in a helper of the resource that the fallback
reaches.

The inline type is a plain string with no FQCN channel, so a body whose shape names an enum or a model could never
import that token. `TsTypeString::shapeValueHasUnimportableToken()` therefore drops the whole body answer, and the vague
declaration stands.

Attribute filters are the exception, because they know an answer that names no token. `bodyType()` analyzes with
`carriesImports: false`, which sets `AnalysisScope::$carriesImports`, and the filter code reads the flag:

- A literal key list publishes the inline shape, never a `Pick<>`. A member whose type names a token, such as an enum
  column or a relation, is `unknown` there, so its siblings keep their types.
- A runtime key list publishes `Record<string, unknown>`, as everywhere.
- A to-many relation filter publishes `unknown[]` instead of `Comment[]`, and a map proxy publishes a list of the inline
  shape.
- An override with a class-typed return, such as `only($attributes): static`, is spelled one top-level union arm at a
  time by `ReceiverMethodReturnResolver::serializedModelShape()`. A model arm, or a list of one, becomes the object the
  model serializes to, narrowed to the call's literal keys, from the columns and appends
  `ModelAttributeResolver::serializedAttributeNames()` lists. A `null` arm stays, and any other token makes the whole
  answer `unknown`.

A filter in the getter of an accessor the body reads is covered too. When the accessor's type comes from its getter
body, `AccessorBodyAnalyzer` analyzes that getter without imports as well, so the method keeps its shape. The model file
and every resource still publish the getter's own analysis, and `CommentRelationFiltersResource` and
`ReleaseColumnsResource` pin both kinds of body. See
[AccessorBodyAnalyzer § A reader that carries no import][reader-no-import].

One read keeps imports on purpose: `ResolvesEnumPropertyArgTypes::resolveEnumFromPropertyArg()` types
`EnumResource::make($author->role)` on a bound closure parameter from the attribute's first enum FQCN, a channel a
spelling without imports can lose. The enum it names is one the body fallback drops anyway.

An accessor still costs the method its whole shape when its type names a class any other way:

- Through its closure signature or its `Attribute<>` docblock.
- Through an old-style getter's return type or `@return` docblock.
- Through an `@property` tag.
- Through a getter that returns a class-typed value without a filter, such as `fn () => $this->author`.

## Property access on a receiver

`ReceiverPropertyFetchHandler` claims `PropertyFetch` and `NullsafePropertyFetch`, and does for a property read what
`ReceiverMethodCallHandler` does for a call. So `$post?->title` is `string | null` once `$post` is known to hold a
`Post`. A `$this->prop` leaf declines outright, since `ThisPropertyHandler` owns it and reads the subject's own
declaration first. See [AST engine § Subject mode](ast-engine.md#subject-mode).

Each class types the property one of two ways:

- **A model**: `ModelAttributeResolver::resolveAttribute()`, then `resolveRelation()`, the order `Model::__get()` uses.
  A relation carries its `modelFqcn`, and a morph union its targets as `embeddedModelFqcns`, so the emitted token keeps
  its import.
- **Any other class**: a public, non-static property, typed by `SubjectPropertyTypeResolver::resolve()` in the same
  three steps as subject mode. It must hold no false-string class and name only published models, for the reasons under
  [the order for one class](#the-order-for-one-class).

`ReceiverClassResolver::memberProperty()` is public so the false-string check can run one receiver class at a time. Take
an `A|B` receiver whose `A::$p` is a raw `DateTime` and whose `B::$p` is a `string`. `resolve()` on the whole expression
answers `null`, which reads as no false string, so `A`'s arm would publish as `string`.

Several classes merge through `ValueResult::mergeUnion()`, and one declining arm declines the read. The read gains
`| null` for its own `?->` or the receiver's `shortCircuits` flag, never twice. `PropertyChainHandler` and
`VariableHandler` claim these node classes earlier. [The inventory][inventory] records where each steps aside, and where
`VariableHandler` does not.

## Related

These pages cover the neighbors of receiver resolution:

- [AST engine](ast-engine.md): dispatch, handler ordering, the scope's binding tables and narrowing.
- [ResourceAstAnalyzer § Attribute filters on any model receiver][resource-filters]: how `RelationFilterHandler` builds
  the filter answers the receiver rules reuse.
- [AccessorBodyAnalyzer](accessor-body-analyzer.md): getter bodies, the other reader that can carry no import.
- [Known gaps § A shape whose values name a class loses those values][gap-shape]: what the body fallback costs a user.
- [API Resources § Method Return Types](https://tolki.abe.dev/ts/api-resources.html#method-return-types): what users see
  of receiver inference.
- [Type inference gates](../testing/type-inference-gates.md): the CI checks that catch a regression to `unknown` or an
  unimportable token.

[`MethodReturnTypeResolver`]: ../../src/Ast/MethodReturnTypeResolver.php
[`NarrowsInstanceofSubjects`]: ../../src/Ast/Concerns/NarrowsInstanceofSubjects.php
[`ReadsInstanceofChains`]: ../../src/Ast/Concerns/ReadsInstanceofChains.php
[`ReceiverClassResolver`]: ../../src/Ast/ReceiverClassResolver.php
[`ReceiverMethodCallHandler`]: ../../src/Ast/Handlers/ReceiverMethodCallHandler.php
[`ReceiverMethodReturnResolver`]: ../../src/Ast/ReceiverMethodReturnResolver.php
[`ReceiverPropertyFetchHandler`]: ../../src/Ast/Handlers/ReceiverPropertyFetchHandler.php
[`ReceiverType`]: ../../src/Ast/ReceiverType.php
[gap-shape]: ../known-gaps.md#a-shape-whose-values-name-a-class-loses-those-values-in-one-of-two-ways
[declines]: ast-engine.md#a-handler-declines-what-it-cannot-type
[gap-to-array]: ../known-gaps.md#modeltoarray-on-a-receiver-declines-deliberately
[inventory]: ast-engine.md#the-honest-ordering-inventory
[reader-no-import]: accessor-body-analyzer.md#a-reader-that-carries-no-import
[resource-filter]: resource-ast-analyzer.md#this-resource-spells-the-same-filter
[resource-filters]: resource-ast-analyzer.md#attribute-filters-on-any-model-receiver
[string-serialization]: support-helpers.md#stringserialization
