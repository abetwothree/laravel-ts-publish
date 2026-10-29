<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;

/**
 * The resource object a `JsonApiResource` sends as `data`, composed from the analyzer's own reads of the runtime class:
 * `id`, `type`, `attributes`, and the `relationships`, `links` and `meta` Laravel sends only when they hold a key.
 *
 * The host also holds `$methodName`, `$context`, `$readsCall`, `$scope` and `$methodsOnStack`.
 *
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type JsonApiRelationship from JsonApiResources
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TsCastMap from TsCasts
 *
 * @internal
 */
trait BuildsJsonApiResourceObject
{
    /** The methods that build a resource object, so reading the object from inside one is Laravel's endless loop. */
    private const array RESOURCE_OBJECT_METHODS = ['toArray', 'toAttributes', 'toRelationships', 'toLinks', 'toMeta', 'toId', 'toType'];

    /** Whether analyzeReturnArray() reads a keyless string item as the model attribute it names, as Laravel does. */
    protected bool $readsKeylessAttributeNames = false;

    /**
     * Rewrite each property type to the list a resource sends a numeric-keyed array as, unless it preserves keys.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     */
    abstract public static function reindexNumericKeyedArrays(MethodAnalysis $analysis, ReflectionClass $resource): void;

    /**
     * Analyze the subject's analyzed method body.
     */
    abstract public function analyze(): ResourceAnalysis;

    /**
     * Resolve a single expression to its TypeScript type.
     *
     * @return ValueExpressionResult
     */
    abstract public function resolve(Expr $expr): array;

    /**
     * Resolve and analyze a $this->method() spread.
     */
    abstract protected function analyzeThisMethodSpread(string $methodName, bool $topLevel = true, ?Expr $call = null): ?ResourceAnalysis;

    /**
     * The model's own serialized array as an analysis; null with no model behind the subject.
     */
    abstract protected function buildModelSerializedAnalysis(): ?ResourceAnalysis;

    /**
     * Check if an expression is a `parent::{$methodName}()` call, or any parent:: call when null.
     */
    abstract protected function isParentCallTo(Expr $expr, ?string $methodName = null): bool;

    /**
     * The class's #[TsCasts] instances in precedence order.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @return list<TsCasts>
     */
    abstract protected function tsCastsAttributes(ReflectionClass $reflection): array;

    /**
     * The runtime class: the class `$this` is when the body runs.
     *
     * @return ReflectionClass<object>
     */
    abstract private function runtimeClass(): ReflectionClass;

    /**
     * Locate the subject's own declaration of the analyzed method, a trait's included.
     */
    abstract private function locateSubjectMethod(): ?MethodContext;

    /**
     * The analysis of one of the runtime class's own methods, null where a read of it encloses this one.
     */
    abstract private function runtimeClassAnalysis(string $method, bool $whole): ?ResourceAnalysis;

    /**
     * The one place a nested analyzer is built, reading a body as the runtime class runs it.
     *
     * @param  ReflectionClass<object>  $class
     * @param  class-string<Model>|null  $modelClass
     */
    abstract private function nestedAnalyzer(ReflectionClass $class, string $method, ?string $modelClass): self;

    /**
     * Lay one #[TsCasts] map over an analysis.
     *
     * @param  TsCastMap  $types
     * @param  CastMap|null  $keptCasts  the casts a model's map never replaces
     */
    abstract private function layTsCasts(array $types, ResourceAnalysis $analysis, ?array $keptCasts = null): void;

    /**
     * Whether this analysis reads the response of a JSON:API resource that runs as itself, not a body a caller located
     * or the value a call to its toArray() returns.
     */
    protected function sendsJsonApiResourceObject(): bool
    {
        return $this->methodName === 'toArray'
            && $this->context === null
            && ! $this->readsCall
            && $this->runtimeClass()->getName() === $this->scope->subjectReflection->getName()
            && JsonApiResources::is($this->scope->subjectReflection->getName());
    }

    /**
     * The resource object the runtime class sends as `data`.
     */
    protected function buildJsonApiResourceObject(): ResourceAnalysis
    {
        $runtime = $this->runtimeClass();

        // The body path sets it, and this returns before it; without it `$this->title` never reaches the model.
        $this->scope->forwardsUndeclaredMembersTo = AnalysisScope::proxyTargetFor($runtime, $this->scope->modelClass);

        $analysis = new ResourceAnalysis;
        $analysis->addProperty('id', ['type' => 'string', 'optional' => false]);
        $analysis->addProperty('type', ['type' => JsonApiResources::typeLiteral($runtime->getName()), 'optional' => false]);

        $attributes = $this->jsonApiAttributesAnalysis();
        self::reindexNumericKeyedArrays($attributes, $runtime);
        $this->layJsonApiAttributeCasts($attributes);

        // Laravel drops an empty attributes object, so it is sure to be sent only while one member always is.
        $analysis->addProperty('attributes', $this->inlineObject($attributes), optional: ! self::sendsMemberAlways($attributes));

        // A member read only partly leaves the whole object read only partly.
        $analysis->readsCompletely = $attributes->readsCompletely;

        $relationships = JsonApiResources::relationships($runtime->getName(), $this->scope->modelClass);

        if ($relationships !== []) {
            $analysis->addProperty('relationships', self::jsonApiRelationshipsResult($relationships), optional: true);
        }

        foreach (['links' => ['toLinks', 'jsonApiLinks'], 'meta' => ['toMeta', 'jsonApiMeta']] as $key => [$method, $property]) {
            $member = $this->runsFrameworkJsonApiMethod($method)
                ? $this->jsonApiDefaultAnalysis($property)
                : $this->analyzeThisMethodSpread($method);

            if ($member !== null) {
                $analysis->addProperty($key, $this->inlineObject($member), optional: true);
                $analysis->readsCompletely = $analysis->readsCompletely && $member->readsCompletely;
            }
        }

        return $analysis;
    }

    /**
     * What JsonApiResource's resolve(), and jsonSerialize() through it, return for the runtime class: its resource
     * object under `data`; null when a method building that object reads it, as Laravel then never returns.
     */
    protected function jsonApiDocumentDataAnalysis(): ?ResourceAnalysis
    {
        $reading = [$this->methodName => true, ...$this->methodsOnStack];

        if (array_any(self::RESOURCE_OBJECT_METHODS, fn (string $method): bool => isset($reading[$method]))) {
            return null;
        }

        $object = $this->runtimeClassAnalysis('toArray', whole: true);

        if ($object === null) {
            return null;
        }

        $analysis = new ResourceAnalysis;
        $analysis->addProperty('data', $this->inlineObject($object));
        $analysis->readsCompletely = $object->readsCompletely;

        return $analysis;
    }

    /**
     * Whether an array item a JSON:API toAttributes() returns spreads JsonApiResource's own toAttributes().
     */
    protected function spreadsFrameworkToAttributes(ArrayItem $item): bool
    {
        $parent = $this->scope->declaringClass->getParentClass();

        return $item->key === null
            && $item->unpack
            && $this->isParentCallTo($item->value, 'toAttributes')
            && $parent !== false
            && JsonApiResources::declaredByFramework($parent->getMethod('toAttributes')->getDeclaringClass()->getName());
    }

    /**
     * What JsonApiResource's own toAttributes() returns for the runtime class: the `$attributes` names, else what
     * toArray() returns, whose keyless names Laravel maps the same way.
     */
    protected function jsonApiFrameworkAttributesAnalysis(): ResourceAnalysis
    {
        if ($this->runtimeClass()->hasProperty('attributes')) {
            return $this->jsonApiAttributeNamesAnalysis();
        }

        return $this->runsFrameworkJsonApiMethod('toArray')
            ? $this->buildModelSerializedAnalysis() ?? new ResourceAnalysis
            : $this->jsonApiToArrayAnalysis();
    }

    /**
     * Whether an analysis holds a member every response sends.
     */
    private static function sendsMemberAlways(MethodAnalysis $analysis): bool
    {
        return array_any(
            $analysis->properties,
            fn (array $property): bool => ! $property['optional'] && ! JsEmitter::isIndexSignatureKey($property['name']),
        );
    }

    /**
     * The `relationships` member: each relationship optional, as only an include sends it.
     *
     * @param  list<JsonApiRelationship>  $relationships
     * @return ValueExpressionResult
     */
    private static function jsonApiRelationshipsResult(array $relationships): array
    {
        $members = [];
        $generics = [];

        foreach ($relationships as $relationship) {
            $generic = TolkiTypes::JSON_API_RELATIONSHIPS[match ($relationship['toMany']) {
                true => 'toMany',
                false => 'toOne',
                null => 'either',
            }];
            $generics[$generic] = $generic;
            $members[] = JsEmitter::validJsObjectKey($relationship['name'])."?: {$generic}<{$relationship['type']}>";
        }

        return [
            'type' => '{ '.implode('; ', $members).' }',
            'optional' => true,
            'customImports' => [TolkiTypes::MODULE => array_values($generics)],
        ];
    }

    /**
     * The analysis the `attributes` object publishes: a toAttributes() override, else what JsonApiResource's own
     * toAttributes() returns.
     */
    private function jsonApiAttributesAnalysis(): ResourceAnalysis
    {
        return $this->runsFrameworkJsonApiMethod('toAttributes')
            ? $this->jsonApiFrameworkAttributesAnalysis()
            : $this->analyzeThisMethodSpread('toAttributes') ?? new ResourceAnalysis;
    }

    /**
     * What an own toArray() returns, read as analyze() reads a body, so a whole `parent::toArray()` return reaches
     * JsonResource's, the model's own array.
     */
    private function jsonApiToArrayAnalysis(): ResourceAnalysis
    {
        // Reached from a toArray() body already being read: JsonApiResource's toAttributes() calls it again, forever.
        if ($this->context !== null && $this->methodName === 'toArray') {
            return new ResourceAnalysis;
        }

        $declaring = $this->runtimeClass()->getMethod('toArray')->getDeclaringClass();
        $analyzer = $this->nestedAnalyzer($declaring, 'toArray', $this->scope->modelClass);

        // A located body is read as it is; without one, analyze() would answer with the resource object itself.
        $analyzer->context = $analyzer->locateSubjectMethod();
        $analyzer->readsKeylessAttributeNames = true;

        if ($analyzer->context === null) {
            return new ResourceAnalysis;
        }

        return $analyzer->analyze();
    }

    /**
     * The `$attributes` default as the attributes it sends: a name reads the model attribute, a keyed entry its value.
     */
    private function jsonApiAttributeNamesAnalysis(): ResourceAnalysis
    {
        $runtime = $this->runtimeClass();
        $analysis = new ResourceAnalysis;

        // A method that writes `$attributes` sends what it writes, which no default names.
        if (! resolve(SubjectPropertyTypeResolver::class)->holdsDefault($runtime, 'attributes')) {
            return $analysis;
        }

        $default = $runtime->getProperty('attributes')->getDefaultValue();

        foreach (is_array($default) ? $default : [] as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $analysis->addProperty($value, $this->resolve($this->modelAttributeRead($value)));
            } elseif (is_string($key)) {
                $analysis->addProperty($key, resolve(ValueResolver::class)->resolveConstantValue($value) ?? ValueResult::unknown());
            }
        }

        return $analysis;
    }

    /**
     * `$this->resource->{name}`, the read Laravel makes for a keyless attribute name.
     */
    private function modelAttributeRead(string $name): PropertyFetch
    {
        return new PropertyFetch(new PropertyFetch(new Variable('this'), new Identifier('resource')), new Identifier($name));
    }

    /**
     * A `links` or `meta` default as the member it sends; null for an empty one, which Laravel drops.
     */
    private function jsonApiDefaultAnalysis(string $property): ?ResourceAnalysis
    {
        $runtime = $this->runtimeClass();

        // A method that writes the property sends what it writes, which no default names.
        if (! resolve(SubjectPropertyTypeResolver::class)->holdsDefault($runtime, $property)) {
            return new ResourceAnalysis;
        }

        $default = $runtime->getProperty($property)->getDefaultValue();

        if (! is_array($default) || $default === []) {
            return null;
        }

        $analysis = new ResourceAnalysis;

        foreach ($default as $key => $value) {
            $analysis->addProperty((string) $key, resolve(ValueResolver::class)->resolveConstantValue($value) ?? ValueResult::unknown());
        }

        return $analysis;
    }

    /**
     * Lay the model's #[TsCasts] over the attribute keys no method cast retypes, then the resource's own over all of
     * them, as ResourceTransformer lays both over a plain resource's top-level keys.
     */
    private function layJsonApiAttributeCasts(ResourceAnalysis $attributes): void
    {
        $methodCasts = $attributes->casts;
        $modelClass = $this->scope->modelClass;

        if ($modelClass !== null && class_exists($modelClass)) {
            foreach ($this->tsCastsAttributes(new ReflectionClass($modelClass)) as $casts) {
                $this->layTsCasts($casts->types, $attributes, $methodCasts);
            }
        }

        foreach ($this->tsCastsAttributes($this->runtimeClass()) as $casts) {
            $this->layTsCasts($casts->types, $attributes);
        }
    }

    /**
     * An analysis as the inline object type a resource object member publishes.
     *
     * @return ValueExpressionResult
     */
    private function inlineObject(MethodAnalysis $analysis): array
    {
        return new InlineObjectResult()->of($analysis);
    }

    /**
     * Whether the runtime class runs Laravel's own declaration of a method rather than an override.
     */
    private function runsFrameworkJsonApiMethod(string $method): bool
    {
        return JsonApiResources::declaredByFramework($this->runtimeClass()->getMethod($method)->getDeclaringClass()->getName());
    }
}
