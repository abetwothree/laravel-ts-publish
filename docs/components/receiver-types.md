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
| [`DeclaredClasses`] | The classes a declaration names: a method's native return or `@return`, a native type, a docblock union's class arms |
| [`CollectionSteps`] | What a collection step does to the items and their keys, for the resolver and the relation-chain and `collect()` handlers |
| [`QueryForwarding`] | The class a relation or Eloquent builder forwards a call it does not declare to |
| [`ReceiverType`] | The answer: `classes`, a `shortCircuits` flag after a `?->`, the `elementModel` a collection carries and `keyed` once a step leaves its keys out of `0..n-1`, or the `relatedModel` a relation, query or factory builds; `unsaved` for a model no row may back yet and `attributeless` for a `new` one holding no attributes (see [Receiver rules](#receiver-rules)); and `collectedItems` with `collectedElement` for a resource collection's items |
| [`ReceiverMethodCallHandler`] | `$x->m()`, `$x?->m()` and `$expr::m()`, including a bare `$this->m()` a resource forwards |
| [`ReceiverPropertyFetchHandler`] | `$x->prop` and `$x?->prop` |
| [`ReceiverMethodReturnResolver`] | The convention rules, then [the order for one class](#the-order-for-one-class) |
| [`MacroCallResolver`] | The macro a `Macroable` subject's `__call()` runs for a name it does not declare, and what that macro returns |
| [`MethodReturnTypeResolver`] | A method's declared return, then the [body fallback](#the-body-fallback-carries-no-fqcn-channel): the literal shape, else [the value its returns hold](#a-method-read-as-a-value-carries-import-channels) |
| [`ReadsInstanceofChains`], [`NarrowsInstanceofSubjects`] | What an `instanceof` condition proves, and the binding its proven arm resolves under |

## Receiver resolution

`ReceiverClassResolver::resolve()` dispatches on the node class, and its class docblock points here for the rules. Any
part it cannot name makes the whole answer `null`, never a partial one. A handler reflects the next member on every
class the receiver names, so dropping one arm of `User|<unknown>` would publish a type that claims more than the code
guarantees. Every class a receiver or chain link names is recorded as a generation-cache dependency before a rule or
reflection answers, a decline included.

| Expression | Receiver |
| --- | --- |
| `$this->resource` on an API resource only | `ReceiverClassResolver::proxyHeld()`: the classes an `instanceof` proof holds it to inside the ternary arm or `if` block it proves (`AnalysisScope::$provenProxy`), and in a block past a member or key write that keeps its class (`CollectsInstanceofGuards::keepsProvenClass()`), else every class the subject's own redeclaration names, such as `/** @var MediaType\|null */ public $resource`, else `AnalysisScope::proxyReceiver()`: the backing model from `AnalysisScope::$forwardsUndeclaredMembersTo`, or on a `ResourceCollection` its items, `Support\Collection \| LengthAwarePaginator \| Paginator \| CursorPaginator` with `collectedItems` set and `collectedElement` the element `$this->collection` lists. A redeclaration holds the backing class instead when every class it names admits it, as `@var Model` or `@var Post\|string` admits a `Post`; otherwise one with an arm that names no class names nothing. On a collection a proof or a redeclaration keeps the items, naming only the class they arrive in. Read whole it publishes the items as `heldItems()` spells them; under a proof, the proven classes' own published types, or `unknown` for the whole ternary when they have none; else the backing model's interface when the subject does not redeclare `$resource` or one class it names admits it (`proxyModel()`), with `\| null` where that redeclaration admits null and no `instanceof` guard in `toArray()` tests it (`redeclaresNullableProxy()`); else the redeclaration |
| `$this->prop` the subject declares, by `SubjectPropertyTypeResolver::declaresOwnProperty()` | Its native class type, else its full `@var` type, at any visibility |
| `$this->prop` on a model-backed subject, and `$this->resource->prop` | An attribute the model lists under the name, or its snake_case accessor: `ModelAttributeResolver::resolveAttributeClass()`, else the one model its `resolveAttribute()` type names, such as an untyped getter's body return. It is read before the relation, as `Model::__get()` reads it, so an attribute holding no class names none. Else the relation: its `morphTo` targets or `resolveMorphToBound()`, an `Eloquent\Collection` with `elementModel` for a to-many relation, or the related model. Else the one model an `@property` tag names, which a dynamic or set relation fills |
| `$var` | The scope's bindings, in the order under [Variables](#variables) |
| `<receiver>->prop`, `<receiver>?->prop` | A model's attribute or relation, as above. On any other class, a public, non-static property's class. |
| `E::Case`, `self::Case`, `static::Case` | The enum `E` when it is an enum with that case, so a local holding one, a ternary of cases and `E::from()` read as the enum; any other class constant declines |
| `$this->m()` | The method `thisMemberClass()` reaches, the runtime class's or the declaring class's private one ([AST engine § AnalysisScope](ast-engine.md#analysisscope)), at any visibility. On a `JsonResource` that does not declare it, what a macro registered under that name returns, else the public method of the class `proxyCallTarget()` names, as below, or of a collection's items. |
| `<model>->relation()` | `DeclaredClasses::returnClasses()`, or `Relation` when the method declares nothing, with `relatedModel` set, which `->getRelated()` then names |
| `<query, relation or factory>->m()` | Its `relatedModel` where the method's docblock returns the class's model template, `TModel` or `TRelatedModel`, alone or beside `null`, marked unsaved for `UNSAVED_QUERY_MODELS`; a trait's template reads as its `@use` tag binds it, so `first()`'s `TValue\|null` names the model. The receiver drops that `null`, since a `?->` read adds it and a plain read of a missing model throws; the call's value keeps it, by the `first()` rule under [Receiver rules](#receiver-rules). The same query where it returns itself, as `where()` does. A method the relation or builder does not declare runs on the class `QueryForwarding::forwardedClass()` names, and a relation hands itself back where its builder returns `$this`. A factory's `make()` and `create()` name the model, and a count step declines |
| `<collection>->first()`, `->last()`, `->firstWhere()` | The `elementModel` of a `Support\Collection` or an `Eloquent\Collection`, unless the call passes a `$default`, which Laravel returns where no item matches |
| `<Eloquent collection>->find(1)` | The `elementModel` for one `int` or `string` literal key and no `$default`, where the class runs `Eloquent\Collection`'s own `find()`; a list, an `Arrayable` or a variable may return a collection |
| `<collection>->sortBy()`, `->filter()`, `->values()`, `->load()` and the other steps that keep its items | The receiver's classes, or `Support\Collection` for `collect()` and `toBase()`, with its `elementModel`, since several of these steps inherit a docblock that names nothing. `values()` clears `keyed`, as an `Eloquent\Collection`'s `ELOQUENT_RELISTS` do for most arguments, since they re-index the models they keep; `SAME_ITEMS`, a `take()` of a literal count and a bare `sortKeys()` keep it; the other `SAME_ITEMS_KEYED` steps set it, since `json_encode()` then writes an object. A resource collection's items go to the receiver rules |
| `<query or relation>->get()` | An `Eloquent\Collection` with the query's model as `elementModel`, wherever the docblock lists the model template, as `Collection<int, TModel>` does. A relation's `makeMany()` names no element, whose models no row backs yet |
| `X::all()` | An `Eloquent\Collection` with `X` as `elementModel` on a concrete model, from `Model::all()`'s `Collection<int, static>` |
| `<model>->getRelation('name')` | The relation as loaded, by the relation rule above, since an unloaded name throws |
| `<model>->getRelationValue('name')` | The relation by the same rule, since it loads a name that is not loaded |
| `$request->user()` | `AuthUserResolver::model()`, when that is not `null` |
| `<receiver>->m()`, `<receiver>?->m()` otherwise | `DeclaredClasses::returnClasses()` on every class, for a public method |
| `static::m()`, `self::m()`, `parent::m()` | The runtime class for `static`, the declaring class for `self`, or its parent for `parent`, then `DeclaredClasses::returnClasses()`, at any visibility, a declared `static` read as the runtime class, which each forwards. A name the subject lacks, which a macro or the resource proxy answers, reads as `$this->m()`, since PHP sends it to `__call()` |
| `X::m()`, `$var::m()`, `$this->resource::m()` | The class, then `DeclaredClasses::returnClasses()`, for a public method. A concrete model's call to a method it does not declare runs on a new `Builder` with the model as `relatedModel`, as `Model::__callStatic()` does, and a model's `Builder<static>` return keeps the model too |
| `X::factory()` | The factory class `X::factory()` returns when the package calls it while publishing, with `X` as `relatedModel`. A count argument, or a factory that cannot be built, declines |
| `new X`, `new static`, `new self`, `new parent` | `X`, the runtime class for `static`, the declaring class for `self`, or its parent for `parent`, but nothing for Laravel's own JsonResource or ResourceCollection; marked unsaved, and `attributeless` for a model built with no constructor argument |
| `resolve(X::class)`, `app(X::class)` | `X` |
| `now()`, `today()`, `collect(...)` | `Illuminate\Support\Carbon`, or `Illuminate\Support\Collection`, from `ReceiverClassResolver::HELPER_CLASSES` |
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

1. `varClassBindings`: a `morphTo` `whenLoaded()` parameter's targets.
2. `varDocBindings`: in an inline `@var` span, the assigned value's classes, or the `@var`'s when the value names none
   or only models with no published file, with the one concrete model a collection generic such as
   `Collection<int, Comment>` lists as `elementModel`, and `keyed` for a `string` or `array-key` key. See
   [AST engine § Declared locals](ast-engine.md#declared-locals).
3. `varModelBindings`, then `varDeclaredBindings`, a called method's parameter's declared classes, or the enum of an
   overridden enum's case a closure parameter defaults to, whose value names no enum, then
   `varCollectionBindings` as an `Eloquent\Collection` with `elementModel`, which a call passing such a collection not
   `keyed` binds too, then `requestVarNames`, then a `varValueBindings` value bound to one enum case, such as a
   parameter's default, as that enum.
4. `closureParamExprBindings`, where a readable `new X` default binds as unsaved, else `localVarBindings`, resolved
   recursively under the `resolvingLocalVars` guard.

Steps 1, 3 and 4's `closureParamExprBindings` answer only while `AnalysisScope::holdsBinding()` does: past the body's
first write that replaces a parameter, step 4's `localVarBindings` answers.

Then the `instanceof` proof `AnalysisScope::guardAt()` finds for the read, in `varGuardBindings`, narrows the answer
class by class, as [a ternary's tested read](#a-ternarys-instanceof-condition) is. The value keeps its element and keys
and loses its short circuit; where no class it holds can pass, or it holds none, the proven classes stand. So a plain
`$x = …;` local, a closure parameter and a loop variable narrow alike. An unbound variable is `null`. No reader of a
variable's members guesses its model from an enclosing relation closure, and a `...$x->toArray()` spread no
binding names spreads the one model `$x` holds. [AST engine § Narrowing](ast-engine.md#narrowing) covers what writes
`varGuardBindings`.

### `self` and `parent`

[AST engine § AnalysisScope](ast-engine.md#analysisscope) names the classes `self`, `static` and `parent` read as, and
the private rule `$this->` takes. On a receiver, `self::`, `static::` and `parent::` forward the runtime class to their
callee as `static`, so a declared `static` return, and JsonResource's own `make()` and `collection()`, which build
`new static`, name the runtime class; `new self` and `new parent` build the declaring class and its parent exactly. Any
other `parent::` call into a method Laravel declares types only where the analyzer reads it, as `parent::toArray()`
(`StaticCallHandler::callsFrameworkParent()`). A `$this->m()` declared to return `static`, a private one included,
returns the runtime class, so a body read on that value knows the class where the caller does
(`ReceiverType::$runtimeInstance`).

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
2. Some `T` is an interface unrelated to `R` by inheritance: `R` stays. A subtype of `R` may pass that test without
   being any other `T`.
3. Some `T` is a subtype of `R`, as when `R` is a morph bound such as `Model`, or `R` is an interface and `T` a class
   unrelated to it, which names a value that is both: those `T` replace `R`.
4. Otherwise no object is both, and `R` is dropped.

A test never widens a class the arm already names. When no class survives, the arm can never run and keeps what it
resolved to, and when it resolves to nothing, it holds the tested classes. When the proven arm instead reads a member
through the tested subject, as in `$this->resource instanceof SubscribedTeam ? $this->resource->subscriber : null`,
`provenArm()` resolves it under `NarrowsInstanceofSubjects::resolveNarrowed()`, the binding `TernaryHandler` uses.
On `$this->resource` the classes narrowed are the proof already in force, else the classes `$resource` is redeclared
as with the backing model standing in for each it is or extends, so `@var Model` over a `Post` narrows to `Post` under
`instanceof Model` or `instanceof Arrayable`. A test that rules out all of those narrows the bare redeclaration, so
`instanceof Image` there narrows to `Image`. A test that rules out none of them, as `instanceof Model` does on
`@var Post|Image`, records no proof (`ReceiverClassResolver::excludesNoNamedClass()`), so every read in the arm keeps
its unproven answer. A redeclaration that admits null is the exception: the test rules the `null` out, so
`instanceof Post` on `@var Post|null` records `Post`.

On a `$this->prop` subject, a read in the arm of the same property names on `$this`, through `->` or `?->`, holds what
the test leaves of the subject, its collection's element and keys included (`AnalysisScope::$narrowedReads`): under
`$this->imageable instanceof Post`, the arm's `$this->imageable->title` publishes `string`.

An `if` test narrows a variable the same way, through [`fromVariable()`](#variables), so a guard narrows a `morphTo`
parameter's targets: past `if (! $o instanceof Post) { return null; }` in a `whenLoaded('imageable', …)` closure, `$o`
holds `Post`, not every target. On an API resource it narrows `$this->resource` for each value inside a block it
proves, so a collection's items read as the paginator `if ($this->resource instanceof LengthAwarePaginator)` names.

A subject method the proven arm or block calls as a value reads its body with `$this->resource` backed by the one model
the proof leaves: `SubjectMethodTypeResolver` passes it as `MethodReturnTypeResolver::resolve()`'s `$backing`, and the
`method-return:` key gains `@backing:<model>`. Two or more proven models back nothing.

These stay un-narrowed:

- A condition on another subject, or on another spelling of the same value, such as `$this->resource->x` tested against
  a `$this->x` arm.
- `&&`, a negation inside an `||` chain, or an `||` operand that is not an `instanceof` test on the arm.
- The arm the test does not prove, so `$x instanceof C ? null : $x` does not remove `C`.
- `?:` and `??`, which prove nothing.

The rule narrows receivers only. `TernaryHandler` narrows reads made inside the proven arm, so `$x->prop` there narrows,
while the ternary's own value and a bare `$x` do not, except a model-bound `$x`, which publishes the subclass a proof
names (`VariableHandler::narrowedModel()`). In `NarrowedImageableResource`, `Image::imageable` is a `morphTo`
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
the answers. When any class declines, the whole call declines. The one exception is a resource collection's items: a
class that neither declares the method nor, as a paginator, forwards it to `Support\Collection` is skipped, since the
call throws there and no response carries that arm. So `total()` publishes `number` from `LengthAwarePaginator` alone,
and a method no class runs declines.

Two receivers come from outside `resolve()`, which names nothing for a bare `$this`:

- **A resource forwarding a call**: on a `JsonResource` subject that does not declare `m`, for `$this->m()` and for the
  `static::m()`, `self::m()` and `$this::m()` PHP sends to `__call()`, `forwardedThisReceiver()`
  names the class `ReceiverClassResolver::proxyCallTarget()` finds, since `JsonResource::__call()` forwards the call to
  what `$this->resource` holds. It takes the first class that declares `m` of: the classes a proof holds it to inside
  the arm it proves, then the classes `$resource` is redeclared as in declaration order, each the backing model is or
  extends standing for it, then the `instanceof` guard's class, then the backing class. A class other than a model that
  the redeclaration names leaves the guard and the backing class out, so the model never answers for it; a proven class
  never does. A collection's items go to the receiver rules. On a `Post` resource, `$this->getKey()` is `number` and
  `$this->fresh()` is `Post | null`. A method the resource declares, including every `JsonResource` helper such as
  `whenLoaded()`, keeps `SubjectMethodTypeResolver`'s answer. A macro registered under the name runs first, so
  `MacroCallResolver` types the call from the macro's callable: its native return, its `@return`, then a closure's body
  read with `$this` bound to the resource and its captured variables read where it is declared.
  `forwardedThisReceiver()` names nothing for a macro, and `$this->resource->m()` keeps the model, which it calls
  directly.
- **A model's own body**: a model method or accessor body runs on the model, so nothing forwards.
  `ReceiverClassResolver::modelSubject()` names the runtime class, that model, for `only()` and `except()` alone, which
  `RelationCollectionChainHandler` declines on a model-backed scope.

A receiver holding an `Illuminate\Http\Request` declines, because `KnownMethodRuleHandler` owns request calls. The call
gains `| null` for its own `?->` or an earlier one its receiver's `shortCircuits` flag records. The call never gains
`| null` twice, never on `unknown`, and never for `$this?->m()`, since `$this` is never `null`.

Earlier claimants of these node classes decline a call they cannot type, as
[AST engine § A handler declines what it cannot type][declines] requires, so this handler gets to answer.
[The honest ordering inventory][inventory] records the declines that settle each pair.

`RelationCollectionChainHandler` claims a `$this->prop->m()` call only through `$this->resource`; on a date-cast column;
or on a model-less subject's own typed property. Through `$this->resource` it reads the class `proxyCallTarget()` finds,
final even when it types nothing, by `SubjectMethodTypeResolver::resolveForwarded()`, as `$this->resource::m()` does,
and the bare `$this->m()`, `$this::m()`, `static::m()` and `self::m()` do when no macro has the name: only a public
method, with the receiver rules' answer for a model method they type (`typesModelMethod()`), such as `toArray()` or
`getCasts()`, else `MethodReturnTypeResolver::resolve()`. On a subject's own property it reads `forwardedOn()` and
leaves those methods, and a filter on a held model, to the receiver rules, as it leaves a resource collection's items.
Every other `$this->prop->m()` is `ReceiverMethodCallHandler`'s, inside a relation's closure too, so
`$this->checks->toArray()` reads `PublishChecks`, never the resource's model.

### Receiver rules

`ReceiverMethodReturnResolver` checks nine convention rules on each class before
[the order for one class](#the-order-for-one-class). They read the receiver's model rather than a signature, because
Laravel declares all nine loosely. `Model::getKey()` and `getAuthIdentifier()` return `mixed`,
`Collection::modelKeys()` reflects to `(string | number)[]`, `Model::only()` to `Record<string, unknown>`,
`Model::except()`, `Model::toArray()` and other `Model` arrays to the list `unknown[]`, `Collection::all()` declares
`array<TKey, TValue>`, `resolveRouteBinding()` declares `Model|null`, and `first()` the template `TValue|null`. Each
rule answers only on the receiver it names:

- **`getKey()`**: on a concrete model that inherits `Model::getKey()`, the key type from
  `ModelAttributeResolver::keyTsType()`, so `HasUuids`, `HasUlids` and `#[Table(keyType: ...)]` count. `Model` itself
  gets no rule. A model with no instance, abstract or one that cannot be built, gets the key type its class declares
  (`ModelAttributeResolver::declaredKeyTsType()`), and an abstract one adds each published concrete subclass's, so
  `number | string`. Where code decides the key, through an overridden `getKeyType()`, `getKeyName()` or a
  `uniqueIds()` that returns no literal list, or a constructor or initializer naming it, the rule declines. A model
  whose `$primaryKey` is `null` publishes `null`.
  `ReceiverType::$unsaved`, set by `new` and by `replicate()`, `replicateQuietly()` and `newInstance()` (through a
  local, a ternary or a `??` too), and by a query's, relation's or factory's `make()`, `makeOne()`,
  `newModelInstance()`, `firstOrNew()` and the rest of `UNSAVED_QUERY_MODELS`, adds `| null`: `number | null`. A fluent
  call that returns the model itself, such as `fill()`, keeps the mark. A helper parameter declared as one concrete
  model keys as `| null` only where its call passes a model no row backs yet.
  `ReceiverType::$attributeless` is narrower: only a `new` model with no constructor argument holds no attributes, a
  callback default `= new X` included, so its column reads add `| null` too, as
  [Property access on a receiver](#property-access-on-a-receiver) says. A `replicate()` copy, a `make()`,
  `firstOrNew()` and `new X([...])` hold the attributes they were given, so their columns keep the plain type, and any
  step that returns a model, `fill()` included, clears the flag. A `new` whose variable the body fills, before the read
  or after it, holds attributes too (`ModelFills::mark()`): every one for an attribute writer such
  as `fill()` called on it or a chain of calls on it as a statement, and only that column for a member or key write
  such as `$p->title = 'x'` of a value the column holds (`ReceiverType::$filled`). A later write of the variable stops
  the fills only inside the list holding that write. A write through a relation, or in a closure nothing calls or one
  with its own variable of that name, fills nothing.
  An override of `getKey()` gets no rule, because PHP holds every subclass to its declared return, which reflection
  types.
- **`getAuthIdentifier()`**: on a model that runs `Authenticatable`'s own, the type from
  `ModelAttributeResolver::authIdentifierTsType()`: the key type, or the type of the column a literal
  `getAuthIdentifierName()` return names. An unsaved receiver adds `| null`. An override of `getAuthIdentifier()` is
  read as declared.
- **`modelKeys()`**: on an `Eloquent\Collection` with `elementModel` set, the element model's key type as a list,
  wrapped first when it is a union: `(number | string)[]`.
- **`all()`**: on a collection with `elementModel` set, the element model as a list, such as `Comment[]`, since a
  relation's collection is keyed `0..n-1`.
- **`toArray()`**: on a concrete model that inherits `Model::toArray()`, `Record<string, unknown>`, since its keys hang
  on the relations loaded at runtime. On a collection with `elementModel` set, `Record<string, unknown>[]`, or
  `unknown[]` when the element model overrides `toArray()`.
- **`Model`'s bare arrays**: on a concrete model that inherits the method, what it holds, from
  `ReceiverMethodReturnResolver::MODEL_ARRAY_RETURNS`: a list of names such as `getAppends()`'s `string[]`,
  `getCasts()`'s `Record<string, string>`, or a map keyed by name such as `getRelations()`'s `Record<string, unknown>`.
- **`first()`, `firstWhere()`**: on a query or relation with `relatedModel` set, where the method, or the one its query
  forwards the call to, declares the model template beside `null` (`ReceiverClassResolver::findsQueriedModel()`), the
  model or `null`: `Comment | null`. A helper the value is passed to keeps the `null`.
- **`resolveRouteBinding()`, `resolveSoftDeletableRouteBinding()`**: on a concrete model that inherits `Model`'s own,
  the receiver's own class or `null`, such as `User | null`, because the query runs on the receiver itself.
  `resolveChildRouteBinding()` gets no rule, as it returns a related model.
- **`only()`, `except()`**: a class that runs `Support\Collection`'s own filter publishes `Record<string, unknown>` for
  any key list. A receiver holding exactly one model whose filter is `Model`'s own, or an override reflection cannot
  type, gets what `RelationFilterHandler` builds for a relation to that model. The answer is a `Pick<>` when every
  literal key is a published column, else the inline shape. A runtime key list, or a literal list that types no member,
  gives `Record<string, unknown>`. An `Eloquent\Collection` with `elementModel` set that runs its own filter, which
  keeps whole models by primary key, publishes them as a list, with the object arm only where `only(null)` or
  `except(null)` keeps a `keyed` collection's keys (`CollectionSteps::eloquentKeyedAfter()`).

A collection marked `keyed` gives the `modelKeys()`, `all()` and `toArray()` lists the object arm `json_encode()` writes
for keys out of `0..n-1`, as in `Comment[] | Record<string, Comment>`. The list arm stays, for the `[]` an empty
collection sends.

A union, an `Eloquent\Collection` with no `elementModel` and a static call get no filter rule. A model whose `only()` or
`except()` override has a return reflection types, such as `: string`, keeps that return wherever the call is written.
`RelationFilterHandler` asks the same `typesAsModelFilter()`, so a relation, accessor or map proxy to such a model
agrees.

A rule answers for the receiver's own model, never the subject's, so `getKey()` on a `UuidPost` receiver is `string`
even when the subject is backed by the integer-keyed `Post`. The filter, route binding and `all()` rules name a model
token, so they apply the published-model check of step 6 themselves. When no model instance can be built, the key rules
read the declared key. They decline, leaving `getKey()` on `mixed` and `modelKeys()` on its reflected
`(string | number)[]`, only where code decides the key.

One more rule reads no model, and runs before the nine on a resource collection's items alone:

- **A resource collection's items** (`collectedItems`): `toArray()` and `jsonSerialize()` publish the held value,
  `ReceiverMethodReturnResolver::heldItems()`. It has one arm per class: `R[]` for `Support\Collection`, or
  `Record<string, R>` when the collection preserves keys, by `ResourceCollections::listValue()`, and the `@tolki/types`
  paginator over `R` for each paginator. `only()` and `except()` publish `R[] | Record<string, R>`, since the items
  they keep come back under their own keys. `first()` and `last()` publish `R | null`, unless the call passes a
  `$default`, which Laravel sends when no item matches. A paginator's `items()` and `getCollection()` publish the list.
  `R` is the collected element, an API resource or the raw models' model; with none it is `unknown` and the item and
  list rules decline.

A method a relation or an Eloquent builder does not declare is typed on the class Laravel runs it on,
`QueryForwarding::forwardedClass()`. `Relation::__call()` forwards it to the Eloquent `Builder`,
whose `__call()` sends a name its `$passthru` lists to `Query\Builder`. A macro under the name runs first, so it
forwards nowhere, and a builder method that is not public is out of the relation's reach, so it declines.
`$this->comments()->count()` publishes `number` and `exists()` `boolean`; `max()` declares `mixed`, so it stays
`unknown`.

### The order for one class

When no convention rule answers, `ReceiverMethodReturnResolver` types the method on one class in this order, and any
step can decline:

1. The method must exist, or a relation or builder must forward it, as [Receiver rules](#receiver-rules) says. On a
   receiver other than `self::`, `static::` or `parent::`, it must be public, by the [visibility](#visibility) rule.
2. A model's `toArray()` that no rule answers, an override or an abstract model's, declines. Its output depends on which
   relations are loaded, and no declaration describes that runtime state. See [known gaps][gap-to-array].
3. When `DeclaredClasses::returnClasses()` is exactly the receiver's own class, from `static`, `$this` or a `self` the
   class declares, the call keeps the receiver's own type. It adds `| null` when the return admits `null`, so
   `Model::fresh()` on a `User` is `User | null`.
4. Otherwise `MethodReturnTypeResolver::resolve()` answers: the native signature, then the `@return` docblock when the
   signature is vague, then [the body fallback](#the-body-fallback-carries-no-fqcn-channel), then
   [the value its returns hold](#a-method-read-as-a-value-carries-import-channels). A conditional `@return` whose every
   branch is a string type, as `Str::upper()` declares, reads `string`.
5. A vague result declines. `unknown[]` would claim a list where an associative array or a `keyBy()` collection is a
   JSON object. A concrete model keeps the `Record<string, unknown>` of a method it inherits from `Model`, such as
   `getAttributes()` or `getDirty()`, since Laravel keys those arrays by attribute name.
6. Every model the result names must have a published file, by `ValueResult::namesOnlyPublishedModels()`. A model under
   `Illuminate\`, or an abstract one, declines, so `User::resolveChildRouteBinding()`'s `Model | null` does too.
   `MethodReturnTypeResolver::resolve()` applies the same check for the call paths that read through it, and the direct
   reflection in `ResolvesRelatedModelTypes` applies it itself, so here it still guards step 3.

### The body fallback carries no FQCN channel

When a declared return is too vague to publish, such as a bare `: array`, `MethodReturnTypeResolver` analyzes the method
body once per set of bindings its calls' arguments make. The shape spelled by its literal return, or by a returned
variable the walk reads, replaces the declaration's array arms, so `PriceQuoteService::quote()`, which declares only
`: array`, publishes `{ unit: string; minimum: number; discounted: { unit: string } }`. `$this->resource->m()` reads it
too, so it publishes what the bare `$this->m()` does. A vague `@return array<string, …>` wins over the native `array` in
`methodOrDocblockReturnTypes()`, and by the same rule over a body value whose arms it spells with a `Record` for the
`unknown[]`, as a parameter returned untouched can be: a string-keyed array encodes as a `Record`, never as a list
(`TsTypeString::spellsListAsRecord()`).

A returned parameter that the body writes only through its keys still holds every key its caller passed. The walk starts
from them where `ObjectMembers::seed()` reads the parameter's bound value, as `GuardedLinkPayload` pins; otherwise the
body never sees them, so the analyzer marks the analysis as holding unread keys
(`ReadsReturnedVariables::returnsUnseededParameter()`) and the shape gains `[key: string]: unknown`.
`DecoratedPayloadResource` pins that through a container instance, a static call and a resource's own helper. A walk
that holds keys it never read, from a whole write, helper or spread it cannot read, a key write it cannot name or an
appending by-reference call anywhere in a statement (`MethodAnalysis::$holdsUnreadKeys`), opens the shape the same way,
as `UnreadBasePayload` pins.

The resolver's docblocks carry the bounds. The shape replaces only the array arms, so `?array` gives `{…} | null`, and a
return the analysis does not read must be a literal a declared arm covers. A `class@method` already under analysis
returns nothing, through a guard in [`AnalysisMemo`](ast-engine.md#the-run-memo-replays-what-it-recorded), so a body
that calls itself terminates, whatever each call passes. An int key publishes quoted, as
`PriceQuoteService::tierLabels()`'s `{ "1": string; "2": string }` shows, whatever the subject. A resource publisher
then [re-indexes](resource-ast-analyzer.md#numeric-keys) such a shape into the list Laravel sends, so
`ServiceReturnResource.tiers` publishes `string[]`.

The inline type is a plain string with no FQCN channel, so a body whose shape names an enum or a model could never
import that token. `TsTypeString::shapeValueHasUnimportableToken()` therefore publishes each member whose type names a
token as `unknown`, and its siblings keep their types.

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

An `EnumResource` wrap over a closure parameter's column, a local or any other resolved payload, and a
`$this->helper()` that `SubjectHelperReturnResolver` reads, stay `unknown` without imports, so their siblings keep their
types.

An accessor still costs the method its whole shape when its type names a class any other way:

- Through its closure signature or its `Attribute<>` docblock.
- Through an old-style getter's return type or `@return` docblock.
- Through an `@property` tag.
- Through a getter that returns a class-typed value without a filter, such as `fn () => $this->author`.

### A method read as a value carries import channels

When the literal shape declines, `MethodReturnTypeResolver` reads the method's own returns as values, through
`AstEngine::analyzeMethodValue()`. So `MediaType::extensions(): array`, a `match` of string lists, publishes `string[]`,
and `UrlService::mixedReturn(): mixed`, which returns `null`, publishes `null`.

- **A fresh scope over the method's class**, never the caller's, but for the runtime class a call hands on: there
  `$this`, `static` and the model `$this` reads are the caller's, so an ancestor's helper a `self::` call reaches reads
  the calling resource's model. Otherwise a model is its own backing and a resource has the model `ModelClassResolver`
  gives it, so a trait helper reads each using resource's model. The parameters bind by their declarations and what the
  call passes. Which calls hand the runtime class on, and which bodies only assume it:
  [AST engine § Known or assumed runtime class](ast-engine.md#known-or-assumed-runtime-class).
- **Strict**: one return that types nothing declines the whole read, since dropping it would claim less than PHP can
  return. A union elsewhere drops such an arm ([known gaps][gap-union-arm]).
- **Declines** a body that yields, and a return that may hold a closure, arrow function or first-class callable, which
  Laravel sends as the `Closure` object, `{}`: as itself, an arm of `?:`, `??` or `match`, or a local the scope binds to
  its one top-level write, which is how the engine reads that local. A local written more than once is not followed,
  and a parameter is never bound to a callable its call passes ([AST engine § A called method's parameters][params]).
- **Skips a dependency's method**: built in, under a vendor directory Composer registered, or in this package's own
  `src/` (`DependencyFiles`).
- **An untyped body that can end without a value** adds `| null`, as `SubjectHelperReturnResolver` does.
- **Unlike the shape, it carries import channels**, so a helper that returns an enum or a model imports it.

It shares the `method-body:` cycle guard with the shape, so a recursion through either reader stops at its first repeat.

## Property access on a receiver

`ReceiverPropertyFetchHandler` claims `PropertyFetch` and `NullsafePropertyFetch`, and does for a property read what
`ReceiverMethodCallHandler` does for a call. So `$post?->title` is `string | null` once `$post` is known to hold a
`Post`. A `$this->prop` leaf declines outright, since `ThisPropertyHandler` owns it and reads the subject's own
declaration first. See [AST engine § Subject mode](ast-engine.md#subject-mode).
A read on `$this->prop` types from the class `prop` holds, as a call does.

Each class types the property one of two ways:

- **A model**: `ModelAttributeResolver::resolveAttribute()`, then `resolveRelation()`, the order `Model::__get()` uses.
  A relation carries its `modelFqcn`, and a morph union its targets as `embeddedModelFqcns`, so the emitted token keeps
  its import. On an `attributeless` receiver a column adds `| null`, as Laravel reads a missing attribute, unless the
  model's new instance holds a default for it or an accessor or cast class computes it; a relation reads as ever.
- **Any other class**: a public, non-static property, typed by `SubjectPropertyTypeResolver::resolve()` in the same
  three steps as subject mode. It must name only published models, for the reasons under
  [the order for one class](#the-order-for-one-class).

An enum's `value` or `name`, read on an enum or on `BackedEnum` or `UnitEnum`, comes from [`EnumValueTypes`] first, and
what it declines falls to the reflected property. `->value` publishes the enum's `…Type` alias with its import, and
`->name` a backed enum's `…Kind` alias, or a pure enum's `…Type`, since its values are its names. An alias needs a
[reader that carries imports][reader-no-import], an enum [`PublishedEnums`] says `ts:publish` writes, and no case whose
`#[TsCase]` changes what the alias spells. Otherwise the read is the literal union of what PHP sends. A case past
±(2⁵³−1) makes `->value` `number`, and the interfaces read `string | number` and `string`. `PropertyChainHandler`'s
wrapped-enum arm asks the same class.

The answers of several classes merge through `ValueResult::unionResults()`, which follows the
[registry's rule][queue-contract] for same-named classes. One declining arm declines the read. The read gains
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

[`CollectionSteps`]: ../../src/Ast/Receivers/CollectionSteps.php
[`DeclaredClasses`]: ../../src/Ast/Receivers/DeclaredClasses.php
[`EnumValueTypes`]: ../../src/Ast/Values/EnumValueTypes.php
[`MacroCallResolver`]: ../../src/Ast/Calls/MacroCallResolver.php
[`MethodReturnTypeResolver`]: ../../src/Ast/Members/MethodReturnTypeResolver.php
[`NarrowsInstanceofSubjects`]: ../../src/Ast/Concerns/NarrowsInstanceofSubjects.php
[`PublishedEnums`]: ../../src/Collectors/PublishedEnums.php
[`QueryForwarding`]: ../../src/Ast/Receivers/QueryForwarding.php
[`ReadsInstanceofChains`]: ../../src/Ast/Concerns/ReadsInstanceofChains.php
[`ReceiverClassResolver`]: ../../src/Ast/Receivers/ReceiverClassResolver.php
[`ReceiverMethodCallHandler`]: ../../src/Ast/Handlers/ReceiverMethodCallHandler.php
[`ReceiverMethodReturnResolver`]: ../../src/Ast/Receivers/ReceiverMethodReturnResolver.php
[`ReceiverPropertyFetchHandler`]: ../../src/Ast/Handlers/ReceiverPropertyFetchHandler.php
[`ReceiverType`]: ../../src/Ast/Receivers/ReceiverType.php
[gap-shape]: ../known-gaps.md#a-shape-whose-values-name-a-class-loses-those-values-in-one-of-two-ways
[gap-union-arm]: ../known-gaps.md#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm
[params]: ast-engine.md#a-called-methods-parameters
[declines]: ast-engine.md#a-handler-declines-what-it-cannot-type
[gap-to-array]: ../known-gaps.md#modeltoarray-on-a-receiver-publishes-recordstring-unknown-deliberately
[inventory]: ast-engine.md#the-honest-ordering-inventory
[queue-contract]: import-name-registry.md#rewriting-aliased-type-references
[reader-no-import]: accessor-body-analyzer.md#a-reader-that-carries-no-import
[resource-filter]: resource-ast-analyzer.md#this-resource-spells-the-same-filter
[resource-filters]: resource-ast-analyzer.md#attribute-filters-on-any-model-receiver
