<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Concerns\FiltersRoutes;
use AbeTwoThree\LaravelTsPublish\Dtos\TsRouteDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * @phpstan-import-type RouteActionData from TsRouteDto
 * @phpstan-import-type RouteArgData from TsRouteDto
 *
 * @extends CoreTransformer<object>
 */
class RouteTransformer extends CoreTransformer
{
    use FiltersRoutes;

    public protected(set) string $controllerName;

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

    public protected(set) ?string $description;

    /** @var list<RouteActionData> */
    public protected(set) array $actions = [];

    /** @var ReflectionClass<object> */
    protected ReflectionClass $reflectionController;

    /** @var array<class-string, object> Cache of instantiated models for binding resolution */
    protected static array $modelInstanceCache = [];

    protected const INVOKE = '__invoke';

    #[Override]
    public function transform(): self
    {
        $this->reflectionController = new ReflectionClass($this->findable);
        $this->controllerName = $this->reflectionController->getShortName();
        $this->filePath = (string) $this->reflectionController->getFileName();
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);
        $this->description = LaravelTsPublish::parseDocBlockDescription($this->reflectionController->getDocComment());

        $this->actions = $this->collectActions();

        return $this;
    }

    #[Override]
    public function data(): TsRouteDto
    {
        return new TsRouteDto(
            controllerName: $this->controllerName,
            filePath: $this->namespacePath.'/'.Str::kebab($this->controllerName),
            fqcn: $this->findable,
            description: $this->description !== '' ? $this->description : null,
            actions: $this->actions,
        );
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->controllerName);
    }

    /**
     * Collect all publishable route actions for this controller.
     *
     * @return list<RouteActionData>
     */
    protected function collectActions(): array
    {
        /** @var Router $router */
        $router = app(Router::class);

        /** @var array<string, RouteActionData> $actionsByMethod Actions keyed by method name to deduplicate */
        $actionsByMethod = [];

        $only = array_values(array_filter(config()->array('ts-publish.routes.only', []), 'is_string'));
        $except = array_values(array_filter(config()->array('ts-publish.routes.except', []), 'is_string'));
        $onlyNamed = config()->boolean('ts-publish.routes.only_named', false);
        $excludeMiddleware = array_values(array_filter(config()->array('ts-publish.routes.exclude_middleware', []), 'is_string'));
        $methodCasing = config()->string('ts-publish.routes.method_casing', 'camel');

        foreach ($router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (ltrim((string) $route->getControllerClass(), '\\') !== $this->findable) {
                continue;
            }

            if (! $this->shouldIncludeRoute($route, $only, $except, $onlyNamed, $excludeMiddleware)) {
                continue;
            }

            $routeName = $route->getName();
            $actionMethod = $route->getActionMethod();

            // For invokable controllers, getActionMethod() returns the FQCN rather than '__invoke'
            if (str_contains($actionMethod, '\\') || ! $this->reflectionController->hasMethod($actionMethod)) {
                $actionMethod = self::INVOKE;
            }

            // Skip if the action method has #[TsExclude]
            if ($this->isMethodExcluded($actionMethod)) {
                continue;
            }

            $methodName = $this->resolveMethodName($actionMethod, $routeName, $methodCasing);

            // When multiple routes map to the same controller method, keep the one with a name
            // (or the first one seen if none have a name).
            if (isset($actionsByMethod[$actionMethod])) {
                if ($routeName !== null && $actionsByMethod[$actionMethod]['name'] === null) {
                    // Replace un-named entry with named one
                    $actionsByMethod[$actionMethod] = $this->buildAction($route, $methodName, $actionMethod);
                }

                continue;
            }

            $actionsByMethod[$actionMethod] = $this->buildAction($route, $methodName, $actionMethod);
        }

        return array_values($actionsByMethod);
    }

    /**
     * Build a single route action data array.
     *
     * @return RouteActionData
     */
    protected function buildAction(Route $route, string $methodName, string $originalMethodName): array
    {
        $description = null;

        if ($this->reflectionController->hasMethod($originalMethodName)) {
            $desc = LaravelTsPublish::parseDocBlockDescription(
                $this->reflectionController->getMethod($originalMethodName)->getDocComment()
            );
            $description = $desc !== '' ? $desc : null;
        }

        $uri = '/'.ltrim($route->uri(), '/');

        $url = null;
        if (is_string($domain = $route->domain())) {
            $url = $domain.$uri;
        }

        return [
            'name' => $route->getName(),
            'url' => $url,
            'uri' => $uri,
            'domain' => $route->getDomain(),
            'methods' => array_values(array_filter(
                array_map(fn (mixed $m): string => strtolower(is_string($m) ? $m : ''), $route->methods()),
                fn (string $m): bool => $m !== 'head',
            )),
            'methodName' => $methodName,
            'originalMethodName' => $originalMethodName,
            'description' => $description,
            'args' => $this->resolveArgs($route, $originalMethodName),
        ];
    }

    /**
     * Resolve the JavaScript export name for a route action.
     *
     * Route name segments (e.g. 'auth.2fa' → '2fa') can start with a digit,
     * which is invalid for JS/TS identifiers used in `export const`. Those are
     * prefixed with an underscore (e.g. '2fa' → '_2fa').
     */
    protected function resolveMethodName(string $actionMethod, ?string $routeName, string $casing): string
    {
        // __invoke always maps to 'invoke', regardless of the route name
        if ($actionMethod === self::INVOKE) {
            return 'invoke';
        }

        // Prefer last segment of the named route name (e.g. 'posts.index' → 'index')
        if ($routeName !== null) {
            $lastSegment = Str::afterLast($routeName, '.');
            if ($lastSegment !== '' && $lastSegment !== $routeName) {
                return $this->ensureValidIdentifier(
                    LaravelTsPublish::safeJsIdentifier(
                        LaravelTsPublish::keyCase($lastSegment, $casing),
                        'Method'
                    )
                );
            }
        }

        return LaravelTsPublish::safeJsIdentifier(
            LaravelTsPublish::keyCase($actionMethod, $casing),
            'Method'
        );
    }

    /**
     * Ensure a resolved method name is a valid JS/TS identifier.
     *
     * Route name segments can start with a digit (e.g. '2fa'), which passes
     * through keyCase and safeJsIdentifier unchanged but is invalid as a bare
     * identifier in `export const` declarations. Prefix with underscore.
     */
    protected function ensureValidIdentifier(string $name): string
    {
        if ($name !== '' && ctype_digit($name[0])) {
            return '_'.$name;
        }

        return $name;
    }

    /**
     * Resolve the args list for a route, including binding metadata.
     *
     * @return list<RouteArgData>
     */
    protected function resolveArgs(Route $route, string $actionMethod): array
    {
        /** @var list<RouteArgData> $args */
        $args = [];

        $paramNames = $route->parameterNames();

        if ($paramNames === []) {
            return $args;
        }

        $methodParams = [];

        if ($this->reflectionController->hasMethod($actionMethod)) {
            $methodParams = $this->reflectionController->getMethod($actionMethod)->getParameters();
        }

        // Build a map of param name → ReflectionParameter for type-hint inspection
        /** @var array<string, ReflectionParameter> $paramMap */
        $paramMap = [];

        foreach ($methodParams as $rp) {
            $paramMap[$rp->getName()] = $rp;
        }

        $wheres = $route->wheres;

        foreach ($paramNames as $paramName) {
            if (! is_string($paramName)) {
                continue; // @codeCoverageIgnore
            }

            // parameterNames() already strips the '?' suffix; detect optionality from the URI
            $cleanName = $paramName;
            $required = ! str_contains($route->uri(), '{'.$paramName.'?}');

            /** @var RouteArgData $arg */
            $arg = ['name' => $cleanName, 'required' => $required];

            // Check for regex constraint
            $whereValue = $wheres[$cleanName] ?? null;

            if (is_string($whereValue) && $whereValue !== '') {
                $arg['where'] = $whereValue;
            }

            // Resolve binding type
            $bindingField = $this->resolveBindingField($route, $cleanName, $paramMap);

            if ($bindingField !== null) {
                $arg['_routeKey'] = $bindingField;
            } else {
                // Check for enum binding
                $enumValues = $this->resolveEnumValues($cleanName, $paramMap);

                if ($enumValues !== null) {
                    $arg['_enumValues'] = $enumValues;
                }
            }

            $args[] = $arg;
        }

        return $args;
    }

    /**
     * Resolve the route key field for a model-bound parameter.
     * Returns null if the parameter is not model-bound.
     *
     * Priority:
     * 1. Explicit binding field from {post:slug} syntax
     * 2. Type-hinted to a Model — call getRouteKeyName()
     * 3. Default: null (not model-bound)
     *
     * @param  array<string, ReflectionParameter>  $paramMap
     */
    protected function resolveBindingField(Route $route, string $paramName, array $paramMap): ?string
    {
        // 1. Explicit binding field from route definition (e.g. {post:slug})
        $explicit = $route->bindingFieldFor($paramName);

        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        // 2. Check if the param is type-hinted to a Model
        if (! isset($paramMap[$paramName])) {
            return null;
        }

        $rp = $paramMap[$paramName];
        $type = $rp->getType();

        if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();

        if (! class_exists($className)) {
            return null; // @codeCoverageIgnore
        }

        // Check it's actually a Model (not an Enum or other class)
        if (! is_a($className, Model::class, true)) {
            return null;
        }

        // Only instantiate if the class overrides the default route key
        if ($this->overridesRouteKey($className)) {
            if (! isset(self::$modelInstanceCache[$className])) {
                self::$modelInstanceCache[$className] = new $className;
            }

            /** @var Model $instance */
            $instance = self::$modelInstanceCache[$className];

            return $instance->getRouteKeyName();
        }

        return 'id';
    }

    /**
     * Check if a model class overrides the default route key name.
     *
     * Only instantiate the model if it actually overrides getRouteKeyName,
     * getKeyName, or $primaryKey — otherwise assume 'id'.
     *
     * @param  class-string  $className
     */
    protected function overridesRouteKey(string $className): bool
    {
        $reflection = new ReflectionClass($className);

        // Check if getRouteKeyName is overridden
        if ($reflection->hasMethod('getRouteKeyName')) {
            $method = $reflection->getMethod('getRouteKeyName');

            if ($method->getDeclaringClass()->getName() !== Model::class) {
                return true;
            }
        }

        // Check if $primaryKey property is overridden
        if ($reflection->hasProperty('primaryKey')) {
            $prop = $reflection->getProperty('primaryKey');

            if ($prop->getDeclaringClass()->getName() !== Model::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve backed enum values for an enum-bound parameter.
     * Returns null if the parameter is not enum-bound.
     *
     * @param  array<string, ReflectionParameter>  $paramMap
     * @return list<string|int>|null
     */
    protected function resolveEnumValues(string $paramName, array $paramMap): ?array
    {
        if (! isset($paramMap[$paramName])) {
            return null;
        }

        $rp = $paramMap[$paramName];
        $type = $rp->getType();

        if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();

        if (! class_exists($className)) {
            return null; // @codeCoverageIgnore
        }

        // Only backed enums can be route-bound
        if (! is_a($className, BackedEnum::class, true)) {
            return null;
        }

        /** @var class-string<BackedEnum> $className */
        return array_map(
            fn (BackedEnum $case): string|int => $case->value,
            $className::cases(),
        );
    }

    /**
     * Check whether a controller method has #[TsExclude].
     */
    protected function isMethodExcluded(string $methodName): bool
    {
        if (! $this->reflectionController->hasMethod($methodName)) {
            return false; // @codeCoverageIgnore
        }

        return $this->reflectionController->getMethod($methodName)->getAttributes(TsExclude::class) !== [];
    }

    /**
     * Clear the static model instance cache.
     *
     * Useful in test suites to prevent stale state between tests.
     */
    public static function clearModelInstanceCache(): void
    {
        self::$modelInstanceCache = [];
    }
}
