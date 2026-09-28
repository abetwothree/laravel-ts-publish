# RouteTransformer

[`RouteTransformer`](../../src/Transformers/RouteTransformer.php) builds each controller action's `RouteActionData`.
This page covers one piece of it, the `_routeKey` a model-bound parameter carries, which tells `defineRoute()` from
`@tolki/ts` which property to read when a caller passes a model instead of a scalar.
`RouteTransformer::resolveBindingField()` resolves the key, and `RouteTransformer::overridesRouteKey()` decides
whether the model is worth constructing to ask.

## How a route key resolves

`resolveBindingField()` takes the first answer in this order:

1. An explicit `{post:slug}` binding, read through `Route::bindingFieldFor()`. The model is not consulted.
2. `null`, when the parameter has no class type or its class is not a `Model`.
3. The model's own `getRouteKeyName()`, when `overridesRouteKey()` says the key can differ from `'id'`. The model is
   constructed once per class and cached.
4. `'id'`, without constructing the model.

## `overridesRouteKey()` must see every source of a non-default key

Constructing a model runs its constructor, which on Laravel 13 also resolves its class attributes through
`initializeModelAttributes()`. Most route models key by `id` and gain nothing from that, so the transformer asks
reflection first and builds a model only when its key can differ.

The check is sound only while it tracks every input to Laravel's `Model::getRouteKeyName()`, which reads the
`#[RouteKey]` attribute and falls back to `getKeyName()`, which reads `$primaryKey`. So `overridesRouteKey()` returns
true when `getRouteKeyName()`, `getKeyName()` or `$primaryKey` is declared below `Model`, or when the class carries
`#[RouteKey]`. A source it misses publishes `'id'` for a model that binds by another key, with no error.

`#[RouteKey]` first ships in Laravel 13.21.0, so the check names it by string behind `class_exists()`. Its guard is
recorded in [Version-guarded Laravel classes](../laravel-version-guards.md).

## Related

These pages cover route usage and the `#[RouteKey]` guard:

- [Routing](https://tolki.abe.dev/ts/routing.html) in the tolki docs, for how route files are used.
- [Version-guarded Laravel classes](../laravel-version-guards.md), for when the `#[RouteKey]` guard can become an
  import.
