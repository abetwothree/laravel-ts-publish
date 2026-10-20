<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Stringable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * What a class extending Laravel's `JsonApiResource` sends in its resource object beside the attributes: its `type`,
 * and the relationships an `include` can send. The class ships from Laravel 12.45, so it is named by string.
 *
 * @phpstan-type IncludedResource = array{type: string, resource: class-string|null}
 * @phpstan-type RelationshipTarget = array{type: string, included: IncludedResource|null}
 * @phpstan-type JsonApiRelationship = array{name: string, toMany: bool|null, type: string, included: list<IncludedResource>}
 * @phpstan-type DeclaredRelationship = array{name: string, resource: class-string|null, toMany?: bool}
 *
 * @internal
 */
final class JsonApiResources
{
    /**
     * Whether this Laravel ships `JsonApiResource`, and with it `JsonResource::toAttributes()`: 12.45 and later.
     */
    public static function shipped(): bool
    {
        $jsonApiResource = 'Illuminate\Http\Resources\JsonApi\JsonApiResource';

        return class_exists($jsonApiResource);
    }

    /**
     * Whether a class is Laravel's `JsonApiResource` or extends it.
     */
    public static function is(string $class): bool
    {
        return self::shipped() && is_a($class, 'Illuminate\Http\Resources\JsonApi\JsonApiResource', true);
    }

    /**
     * The type an instance of an API resource class is sent as wherever json_encode() writes it: its own type, under
     * `data` for a JSON:API resource, whose resolve() nests its resource object there.
     *
     * @param  class-string  $class
     */
    public static function instanceType(string $class): string
    {
        $name = TsNaming::resourceTypeName($class);

        return self::is($class) ? '{ data: '.$name.' }' : $name;
    }

    /**
     * Whether a JSON:API resource runs JsonApiResource's own resolve(), which sends its resource object under `data`.
     *
     * @param  class-string  $class
     */
    public static function runsFrameworkResolve(string $class): bool
    {
        return self::is($class) && self::declaredByFramework(new ReflectionMethod($class, 'resolve')->getDeclaringClass()->getName());
    }

    /**
     * Whether a method's declaring class is Laravel's own, so a JSON:API resource runs no override of it.
     */
    public static function declaredByFramework(string $declaringClass): bool
    {
        return in_array($declaringClass, ['Illuminate\Http\Resources\JsonApi\JsonApiResource', JsonResource::class], true);
    }

    /**
     * The `type` a JSON:API resource class sends as a TypeScript literal: Laravel's own expression over the class name,
     * the one string a `toType()` override returns, or `string` for any other override.
     *
     * @param  class-string  $class
     */
    public static function typeLiteral(string $class): string
    {
        if (self::declaredByFramework(new ReflectionMethod($class, 'toType')->getDeclaringClass()->getName())) {
            return JsEmitter::toJsLiteral((string) new Stringable($class)->classBasename()->basename('Resource')->snake()->pluralStudly());
        }

        $literal = resolve(ReturnLiteralReader::class)->stringLiteral($class, 'toType');

        return $literal === null ? 'string' : JsEmitter::toJsLiteral($literal);
    }

    /**
     * Each relationship a JSON:API resource can include, from `$relationships` or a `toRelationships()` override's
     * array literals: whether it sends a list, the `type` of each resource object it names, and what an include of it
     * adds to `included`.
     *
     * @param  class-string  $class
     * @param  class-string<Model>|null  $modelClass
     * @return list<JsonApiRelationship>
     */
    public static function relationships(string $class, ?string $modelClass): array
    {
        return resolve(AnalysisMemo::class)->remember(
            'json-api-relationships:'.$class.'@'.$modelClass,
            fn (): array => self::readRelationships($class, $modelClass),
        );
    }

    /**
     * Every resource object an include can add to a JSON:API resource's `included`, following its relationships to any
     * depth: a JSON:API resource, or Laravel's bare JsonApiResource over a model that has none, by its `type`.
     *
     * @param  class-string  $class
     * @return list<IncludedResource>
     */
    public static function includedResources(string $class): array
    {
        return resolve(AnalysisMemo::class)->remember('json-api-included:'.$class, fn (): array => self::readIncludedResources($class));
    }

    /**
     * Whether a JSON:API resource declares no relationship on any request, so Laravel never sends it an `included`: it
     * runs JsonApiResource's own toRelationships() over a `$relationships` that is absent, or empty and never written.
     *
     * @param  class-string  $class
     */
    public static function declaresNoRelationships(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        if (! self::declaredByFramework($reflection->getMethod('toRelationships')->getDeclaringClass()->getName())) {
            return false;
        }

        return ! $reflection->hasProperty('relationships')
            || (($reflection->getDefaultProperties()['relationships'] ?? null) === []
                && resolve(SubjectPropertyTypeResolver::class)->holdsDefault($reflection, 'relationships'));
    }

    /**
     * The relationships relationships() answers, read off the class and its model.
     *
     * @param  class-string  $class
     * @param  class-string<Model>|null  $modelClass
     * @return list<JsonApiRelationship>
     */
    private static function readRelationships(string $class, ?string $modelClass): array
    {
        $relationships = [];

        foreach (self::declaredRelationships($class) as $declared) {
            $relation = $modelClass === null ? null : resolve(ModelAttributeResolver::class)->resolveRelation($modelClass, $declared['name']);
            $relatedModels = match (true) {
                $relation === null => [],
                $relation['modelFqcn'] !== null => [$relation['modelFqcn']],
                default => $relation['morphFqcns'],
            };

            // Laravel builds the resolver's resource over every related model, else each model's own guess.
            $targets = $declared['resource'] !== null
                ? [self::target($declared['resource'], null)]
                : array_map(fn (string $model): array => self::target(self::guessedResource($model), $model), $relatedModels);

            $relationships[] = [
                'name' => $declared['name'],
                'toMany' => $declared['toMany'] ?? self::sendsList($relation['type'] ?? 'unknown'),
                'type' => self::typeUnion($targets),
                'included' => $targets === [] ? [['type' => 'string', 'resource' => null]] : array_values(array_filter(
                    array_map(fn (array $target): ?array => $target['included'], $targets),
                )),
            ];
        }

        return $relationships;
    }

    /**
     * The resource objects includedResources() answers, walking the relationships from the class.
     *
     * @param  class-string  $class
     * @return list<IncludedResource>
     */
    private static function readIncludedResources(string $class): array
    {
        $included = [];
        $read = [$class => true];
        $pending = [$class];

        while ($pending !== []) {
            $resource = array_shift($pending);
            $model = resolve(ModelClassResolver::class)->resolve(new ReflectionClass($resource));

            foreach (self::relationships($resource, $model) as $relationship) {
                foreach ($relationship['included'] as $member) {
                    $included[$member['resource'] ?? $member['type']] ??= $member;

                    if ($member['resource'] !== null && ! isset($read[$member['resource']])) {
                        $read[$member['resource']] = true;
                        $pending[] = $member['resource'];
                    }
                }
            }
        }

        return array_values($included);
    }

    /**
     * The relationships a class declares: the `$relationships` default Laravel's `toRelationships()` returns, or the
     * entries of every array literal an override returns.
     *
     * @param  class-string  $class
     * @return list<DeclaredRelationship>
     */
    private static function declaredRelationships(string $class): array
    {
        $reflection = new ReflectionClass($class);

        if (! self::declaredByFramework($reflection->getMethod('toRelationships')->getDeclaringClass()->getName())) {
            return self::toRelationshipsEntries($class);
        }

        $defaults = $reflection->getDefaultProperties()['relationships'] ?? [];
        $declared = [];

        foreach (is_array($defaults) ? $defaults : [] as $key => $value) {
            if (is_int($key)) {
                if (is_string($value)) {
                    $declared[] = ['name' => $value, 'resource' => null];
                }

                continue;
            }

            $declared[] = ['name' => $key, 'resource' => is_string($value) && class_exists($value) ? $value : null];
        }

        return $declared;
    }

    /**
     * The entries of the array literals a `toRelationships()` override returns: a key alone, or a key whose `X::class`
     * value, or closure returning `new X()` or `X::collection()`, names the resource.
     *
     * @param  class-string  $class
     * @return list<DeclaredRelationship>
     */
    private static function toRelationshipsEntries(string $class): array
    {
        $stmts = resolve(MethodLocator::class)->locate($class, 'toRelationships')?->method->stmts ?? [];
        $finder = new NodeFinder;
        $closureReturns = [];
        $entries = [];

        foreach ($finder->findInstanceOf($stmts, ClosureExpr::class) as $closure) {
            foreach ($finder->findInstanceOf($closure->stmts, Return_::class) as $return) {
                $closureReturns[spl_object_id($return)] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Return_::class) as $return) {
            if (isset($closureReturns[spl_object_id($return)]) || ! $return->expr instanceof Array_) {
                continue;
            }

            foreach ($return->expr->items as $item) {
                if ($item->key instanceof String_) {
                    $entries[] = ['name' => $item->key->value, ...self::resolverResource($item->value)];
                } elseif ($item->key === null && ! $item->unpack && $item->value instanceof String_) {
                    $entries[] = ['name' => $item->value->value, 'resource' => null];
                }
            }
        }

        return $entries;
    }

    /**
     * The resource a relationship's resolver names, and, for a closure, whether it returns a collection of it.
     *
     * @return array{resource: class-string|null, toMany?: bool}
     */
    private static function resolverResource(Expr $value): array
    {
        if ($value instanceof ClassConstFetch) {
            $class = $value->class instanceof Name ? $value->class->toString() : null;
            $namesClass = $value->name instanceof Identifier && $value->name->toLowerString() === 'class';

            return ['resource' => $namesClass && $class !== null && class_exists($class) ? $class : null];
        }

        $returned = match (true) {
            $value instanceof ArrowFunction => $value->expr,
            $value instanceof ClosureExpr => self::firstReturned($value),
            default => null,
        };

        if ($returned instanceof New_ && $returned->class instanceof Name && class_exists($returned->class->toString())) {
            return ['resource' => $returned->class->toString(), 'toMany' => false];
        }

        if ($returned instanceof StaticCall && $returned->class instanceof Name && $returned->name instanceof Identifier
            && in_array($returned->name->toLowerString(), ['make', 'collection'], true) && class_exists($returned->class->toString())) {
            return ['resource' => $returned->class->toString(), 'toMany' => $returned->name->toLowerString() === 'collection'];
        }

        return ['resource' => null];
    }

    /**
     * The expression a closure's own first `return` hands back, never a nested closure's.
     */
    private static function firstReturned(ClosureExpr $closure): ?Expr
    {
        foreach ($closure->stmts as $stmt) {
            if ($stmt instanceof Return_) {
                return $stmt->expr;
            }
        }

        return null;
    }

    /**
     * The resource object a relationship sends for one related model: its `type`, and what an include adds to
     * `included`, which is nothing for a resource that is no JSON:API resource, as Laravel throws building it.
     *
     * @param  class-string|null  $resource  the resource Laravel builds, null when it finds none
     * @param  class-string|null  $model  the related model, null when the relation names none
     * @return RelationshipTarget
     */
    private static function target(?string $resource, ?string $model): array
    {
        if ($resource !== null) {
            DependencyRecorder::recordClass($resource);

            if (! self::is($resource)) {
                return ['type' => 'string', 'included' => null];
            }

            $type = self::typeLiteral($resource);

            return ['type' => $type, 'included' => ['type' => $type, 'resource' => $resource]];
        }

        // With no resource found Laravel sends a bare JsonApiResource over the model.
        $type = $model !== null && is_a($model, Model::class, true) ? self::modelTypeLiteral($model) : 'string';

        return ['type' => $type, 'included' => ['type' => $type, 'resource' => null]];
    }

    /**
     * The `type` literals of a relationship's resource objects as one union; `string` when one is not a literal.
     *
     * @param  list<RelationshipTarget>  $targets
     */
    private static function typeUnion(array $targets): string
    {
        $types = array_values(array_unique(array_column($targets, 'type')));

        return $types === [] || in_array('string', $types, true) ? 'string' : implode(' | ', $types);
    }

    /**
     * Whether a relation's published type is a list, so its relationship sends one; null for a relation nothing types.
     */
    private static function sendsList(string $relationType): ?bool
    {
        return $relationType === 'unknown' ? null : str_ends_with(str_replace(' | null', '', $relationType), '[]');
    }

    /**
     * The resource `Model::toResource()` builds for a related model: the `#[UseResource]` class, else the first
     * candidate of the naming convention that exists.
     *
     * @param  class-string  $modelFqcn
     * @return class-string|null
     */
    private static function guessedResource(string $modelFqcn): ?string
    {
        if (! is_a($modelFqcn, Model::class, true)) {
            return null;
        }

        $useResource = 'Illuminate\Database\Eloquent\Attributes\UseResource';
        $attributes = class_exists($useResource) ? new ReflectionClass($modelFqcn)->getAttributes($useResource) : [];
        $named = $attributes === [] ? null : $attributes[0]->newInstance()->class;

        if ($named !== null && class_exists($named)) {
            return $named;
        }

        foreach ($modelFqcn::guessResourceName() as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The `type` Laravel's fallback `new JsonApiResource($model)` sends: the morph alias, else the class basename.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private static function modelTypeLiteral(string $modelFqcn): string
    {
        $alias = Relation::getMorphAlias($modelFqcn);

        // A morph map may key a model by an int, which Laravel's Stringable writes as its digits.
        $name = $alias !== $modelFqcn ? (string) $alias : class_basename($modelFqcn);

        return JsEmitter::toJsLiteral((string) new Stringable($name)->snake()->pluralStudly());
    }
}
