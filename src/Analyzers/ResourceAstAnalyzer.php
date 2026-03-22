<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Analyzes a JsonResource's toArray() method body to extract property names,
 * types, and conditional (optional) markers using AST parsing.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type ImportMapType from ResourceAnalysis
 *
 * @phpstan-type ModelAttributeInfo = array{name: string, type: string|null, cast: string|null, nullable: bool}
 * @phpstan-type ModelRelationInfo = array{name: string, type: string, related: class-string<Model>}
 */
class ResourceAstAnalyzer
{
    protected ?Model $modelInstance = null;

    protected ?RelationNullable $relationNullable = null;

    /** @var Collection<int, ModelAttributeInfo>|null */
    protected ?Collection $modelAttributes = null;

    /** @var Collection<int, ModelRelationInfo>|null */
    protected ?Collection $modelRelations = null;

    /** @var list<string> */
    protected array $conditionalMethods = [
        'when', 'whenHas', 'whenNotNull', 'whenLoaded',
        'whenCounted', 'whenAggregated', 'whenPivotLoaded', 'whenPivotLoadedAs',
    ];

    /**
     * @param  ReflectionClass<JsonResource>  $resourceReflection
     * @param  class-string<Model>|null  $modelClass
     */
    public function __construct(
        protected ReflectionClass $resourceReflection,
        protected ?string $modelClass = null,
    ) {
        if ($this->modelClass !== null) {
            $this->loadModelInspectorData();
        }
    }

    public function analyze(): ResourceAnalysis
    {
        $filePath = (string) $this->resourceReflection->getFileName();
        $source = (string) file_get_contents($filePath);

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        /** @var list<Node\Stmt> $stmts */
        $stmts = $parser->parse($source);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $stmts = $traverser->traverse($stmts);

        $finder = new NodeFinder;
        $toArrayMethod = $finder->findFirst($stmts, function (Node $node): bool {
            return $node instanceof ClassMethod && $node->name->toString() === 'toArray';
        });

        if (! $toArrayMethod instanceof ClassMethod || $toArrayMethod->stmts === null) {
            return $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
        }

        $returnStmt = $finder->findFirst($toArrayMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_;
        });

        if (! $returnStmt instanceof Return_ || ! $returnStmt->expr instanceof Array_) {
            if ($returnStmt instanceof Return_ && $returnStmt->expr !== null && $this->isParentToArrayCall($returnStmt->expr)) {
                return $this->analyzeParentToArray() ?? $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
            }

            return new ResourceAnalysis;
        }

        return $this->analyzeReturnArray($returnStmt->expr);
    }

    protected function analyzeReturnArray(Array_ $array): ResourceAnalysis
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];
        /** @var ImportMapType $customImports */
        $customImports = [];

        foreach ($array->items as $item) {
            // Handle ...parent::toArray($request) spread
            if ($item->key === null && $item->unpack && $this->isParentToArrayCall($item->value)) {
                $parentAnalysis = $this->analyzeParentToArray();

                if ($parentAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties,
                        $enumResources,
                        $nestedResources,
                        $directEnumFqcns,
                        $modelFqcns,
                        $parentAnalysis->properties,
                        $parentAnalysis->enumResources,
                        $parentAnalysis->nestedResources,
                        $parentAnalysis->directEnumFqcns,
                        $parentAnalysis->modelFqcns,
                    );
                    foreach ($parentAnalysis->customImports as $path => $types) {
                        $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
                    }
                }

                continue;
            }

            // Handle ...$this->method() spread (e.g., trait methods returning arrays)
            if ($item->key === null && $item->unpack
                && $item->value instanceof MethodCall
                && $item->value->var instanceof Variable
                && $item->value->var->name === 'this'
                && $item->value->name instanceof Identifier) {
                $spreadAnalysis = $this->analyzeThisMethodSpread($item->value->name->toString());

                if ($spreadAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties,
                        $enumResources,
                        $nestedResources,
                        $directEnumFqcns,
                        $modelFqcns,
                        $spreadAnalysis->properties,
                        $spreadAnalysis->enumResources,
                        $spreadAnalysis->nestedResources,
                        $spreadAnalysis->directEnumFqcns,
                        $spreadAnalysis->modelFqcns,
                    );
                    foreach ($spreadAnalysis->customImports as $path => $types) {
                        $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
                    }
                }

                continue;
            }

            // Handle mergeWhen / $this->mergeWhen(...)
            if ($item->key === null && $item->value instanceof MethodCall) {
                $mergeResult = $this->analyzeMergeExpression($item->value);

                $this->syncAnalysisMaps(
                    $properties,
                    $enumResources,
                    $nestedResources,
                    $directEnumFqcns,
                    $modelFqcns,
                    $mergeResult['properties'],
                    $mergeResult['enumResources'],
                    $mergeResult['nestedResources'],
                    $mergeResult['directEnumFqcns'],
                    $mergeResult['modelFqcns'],
                );

                continue;
            }

            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key);

            if ($keyName === null) {
                continue;
            }

            $result = $this->analyzeValueExpression($item->value);

            // When a child key overrides a parent spread key, clear stale parent tracking
            unset($enumResources[$keyName], $nestedResources[$keyName], $directEnumFqcns[$keyName], $modelFqcns[$keyName]);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $result['optional'],
                'description' => '',
            ];

            if (isset($result['enumFqcn'])) {
                $enumResources[$keyName] = $result['enumFqcn'];
            }

            if (isset($result['directEnumFqcn'])) {
                $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
            }

            if (isset($result['resourceFqcn'])) {
                $nestedResources[$keyName] = $result['resourceFqcn'];
            }

            if (isset($result['modelFqcn'])) {
                $modelFqcns[$keyName] = $result['modelFqcn'];
            }

        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns
        );
    }

    /**
     * Synchronize analysis maps by merging new data into existing arrays.
     *
     * @param  ResourcePropertyInfoList  $properties
     * @param  ClassMapType  $enumResources
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $modelFqcns
     * @param  ResourcePropertyInfoList  $newProperties
     * @param  ClassMapType  $newEnumResources
     * @param  ClassMapType  $newNestedResources
     * @param  ClassMapType  $newDirectEnumFqcns
     * @param  ClassMapType  $newModelFqcns
     */
    protected function syncAnalysisMaps(
        array &$properties,
        array &$enumResources,
        array &$nestedResources,
        array &$directEnumFqcns,
        array &$modelFqcns,
        array $newProperties = [],
        array $newEnumResources = [],
        array $newNestedResources = [],
        array $newDirectEnumFqcns = [],
        array $newModelFqcns = [],
    ): void {
        $properties = [...$properties, ...$newProperties];
        $enumResources = [...$enumResources, ...$newEnumResources];
        $nestedResources = [...$nestedResources, ...$newNestedResources];
        $directEnumFqcns = [...$directEnumFqcns, ...$newDirectEnumFqcns];
        $modelFqcns = [...$modelFqcns, ...$newModelFqcns];
    }

    /**
     * Analyze a value expression and return its type + optional status.
     *
     * @return array{
     *  type: string,
     *  optional: bool,
     *  enumFqcn?: class-string,
     *  directEnumFqcn?: class-string,
     *  resourceFqcn?: class-string,
     *  modelFqcn?: class-string,
     * }
     */
    protected function analyzeValueExpression(Expr $expr): array
    {
        // $this->when(condition, value) or $this->when(condition, value, default)
        if ($this->isThisMethodCall($expr, 'when')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhen($expr);
        }

        // $this->whenHas('attribute')
        if ($this->isThisMethodCall($expr, 'whenHas')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenHas($expr);
        }

        // $this->whenNotNull($this->value)
        if ($this->isThisMethodCall($expr, 'whenNotNull')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenNotNull($expr);
        }

        // $this->whenLoaded('relation') or $this->whenLoaded('relation', value)
        if ($this->isThisMethodCall($expr, 'whenLoaded')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenLoaded($expr);
        }

        // $this->whenCounted('relation')
        if ($this->isThisMethodCall($expr, 'whenCounted')) {
            return ['type' => 'number', 'optional' => true];
        }

        // $this->whenAggregated('relation', 'column', 'function')
        if ($this->isThisMethodCall($expr, 'whenAggregated')) {
            return ['type' => 'number', 'optional' => true];
        }

        // $this->whenPivotLoaded('table', fn) or $this->whenPivotLoadedAs(...)
        if ($this->isThisMethodCall($expr, 'whenPivotLoaded') || $this->isThisMethodCall($expr, 'whenPivotLoadedAs')) {
            return ['type' => 'unknown', 'optional' => true];
        }

        // EnumResource::make($this->prop)
        if ($expr instanceof StaticCall) {
            return $this->analyzeStaticCall($expr);
        }

        // SomeResource::collection($this->whenLoaded('relation'))
        // Already handled by static call above

        // $this->property
        if ($this->isThisPropertyFetch($expr)) {
            return $this->analyzeThisProperty($expr);
        }

        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Analyze $this->when(condition, value) — the value is the second arg.
     *
     * @return array{
     *  type: string,
     *  optional: bool,
     *  enumFqcn?: class-string,
     *  directEnumFqcn?: class-string,
     *  resourceFqcn?: class-string,
     * }
     */
    protected function analyzeWhen(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;

            // Resolve the type from the value expression
            $inner = $this->analyzeValueExpression($valueExpr);
            $inner['optional'] = true;

            return $inner;
        }

        return ['type' => 'unknown', 'optional' => true];
    }

    /**
     * Analyze $this->whenHas('attribute') — the attribute name is the first arg string.
     *
     * @return array{type: string, optional: bool, directEnumFqcn?: class-string}
     */
    protected function analyzeWhenHas(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 1 && $args[0]->value instanceof String_) {
            $attrName = $args[0]->value->value;
            $info = $this->resolveModelAttributeTypeInfo($attrName);
            $result = ['type' => $info['type'], 'optional' => true];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn'];
            }

            return $result;
        }

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenNotNull($this->value) — resolve the inner expression type.
     *
     * @return array{
     *  type: string,
     *  optional: bool,
     *  enumFqcn?: class-string,
     *  directEnumFqcn?: class-string,
     *  resourceFqcn?: class-string,
     * }
     */
    protected function analyzeWhenNotNull(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 1) {
            $inner = $this->analyzeValueExpression($args[0]->value);
            $inner['optional'] = true;

            return $inner;
        }

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenLoaded('relation') or $this->whenLoaded('relation', value, default).
     *
     * @return array{type: string, optional: bool, resourceFqcn?: class-string, modelFqcn?: class-string}
     */
    protected function analyzeWhenLoaded(MethodCall $call): array
    {
        $args = $call->getArgs();

        // $this->whenLoaded('relation', value) — use value for type
        if (count($args) >= 2) {
            $inner = $this->analyzeValueExpression($args[1]->value);
            $inner['optional'] = true;

            return $inner;
        }

        // $this->whenLoaded('relation') — resolve relation type from model
        if (count($args) >= 1 && $args[0]->value instanceof String_) {
            $relationName = $args[0]->value->value;
            $info = $this->resolveModelRelationTypeInfo($relationName);
            $result = ['type' => $info['type'], 'optional' => true];

            if ($info['modelFqcn'] !== null) {
                $result['modelFqcn'] = $info['modelFqcn'];
            }

            return $result;
        }

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->mergeWhen(condition, [...]) — extract properties and FQCNs from 2nd arg array.
     *
     * @return array{
     *  properties: ResourcePropertyInfoList,
     *  enumResources: ClassMapType,
     *  nestedResources: ClassMapType,
     *  directEnumFqcns: ClassMapType,
     *  modelFqcns: ClassMapType
     * }
     */
    protected function analyzeMergeExpression(MethodCall $call): array
    {
        $empty = ['properties' => [], 'enumResources' => [], 'nestedResources' => [], 'directEnumFqcns' => [], 'modelFqcns' => []];

        if (! $this->isThisMethodCall($call, 'mergeWhen')) {
            return $empty;
        }

        $args = $call->getArgs();

        if (count($args) < 2) {
            return $empty;
        }

        $valueExpr = $args[1]->value;

        // mergeWhen(condition, ['key' => 'value', ...])
        if ($valueExpr instanceof Array_) {
            return $this->extractPropertiesFromArray($valueExpr, optional: true);
        }

        return $empty;
    }

    /**
     * Analyze a static method call like EnumResource::make() or SomeResource::make/collection().
     *
     * @return array{type: string, optional: bool, enumFqcn?: class-string, resourceFqcn?: class-string}
     */
    protected function analyzeStaticCall(StaticCall $call): array
    {
        $className = $this->resolveStaticCallClassName($call);
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($className === null || $methodName === null) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        // EnumResource::make($this->prop)
        if ($this->isEnumResourceClass($className) && $methodName === 'make') {
            return $this->analyzeEnumResourceMake($call);
        }

        // SomeResource::make($this->prop) — nested resource
        if ($this->isResourceClass($className) && $methodName === 'make') {
            $resourceName = class_basename($className);
            $optional = $this->hasConditionalArgument($call);

            /** @var class-string $className */
            return [
                'type' => $resourceName,
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // SomeResource::collection(...) — array of nested resource
        if ($this->isResourceClass($className) && $methodName === 'collection') {
            $resourceName = class_basename($className);
            $optional = $this->hasConditionalArgument($call);

            /** @var class-string $className */
            return [
                'type' => $resourceName.'[]',
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Analyze EnumResource::make($this->prop) — resolve the enum class from the model property.
     *
     * @return array{type: string, optional: bool, enumFqcn?: class-string}
     */
    protected function analyzeEnumResourceMake(StaticCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) < 1) {
            return ['type' => 'unknown', 'optional' => false];
        }

        $innerExpr = $args[0]->value;

        // EnumResource::make($this->prop)
        if ($this->isThisPropertyFetch($innerExpr)) {
            /** @var PropertyFetch $innerExpr */
            $propName = $innerExpr->name instanceof Identifier ? $innerExpr->name->toString() : null;

            if ($propName !== null) {
                $enumFqcn = $this->resolveModelPropertyEnumClass($propName);

                if ($enumFqcn !== null) {
                    $tsInfo = LaravelTsPublish::phpToTypeScriptType($enumFqcn);

                    return [
                        'type' => $tsInfo['type'],
                        'optional' => false,
                        'enumFqcn' => $enumFqcn,
                    ];
                }
            }
        }

        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Analyze $this->property — resolve the type from the backing model.
     *
     * @return array{type: string, optional: bool, directEnumFqcn?: class-string}
     */
    protected function analyzeThisProperty(Expr $expr): array
    {
        /** @var PropertyFetch $expr */
        $propName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($propName === null) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        $info = $this->resolveModelAttributeTypeInfo($propName);
        $result = ['type' => $info['type'], 'optional' => false];

        if ($info['enumFqcn'] !== null) {
            $result['directEnumFqcn'] = $info['enumFqcn'];
        }

        return $result;
    }

    /**
     * Extract properties and FQCNs from an array expression, e.g. for mergeWhen's second argument.
     *
     * @return array{
     *  properties: ResourcePropertyInfoList,
     *  enumResources: ClassMapType,
     *  nestedResources: ClassMapType,
     *  directEnumFqcns: ClassMapType,
     *  modelFqcns: ClassMapType,
     * }
     */
    protected function extractPropertiesFromArray(Array_ $array, bool $optional = false): array
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];

        foreach ($array->items as $item) {
            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key);

            if ($keyName === null) {
                continue;
            }

            $result = $this->analyzeValueExpression($item->value);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $optional || $result['optional'],
                'description' => '',
            ];

            if (isset($result['enumFqcn'])) {
                $enumResources[$keyName] = $result['enumFqcn'];
            }

            if (isset($result['directEnumFqcn'])) {
                $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
            }

            if (isset($result['resourceFqcn'])) {
                $nestedResources[$keyName] = $result['resourceFqcn'];
            }

            if (isset($result['modelFqcn'])) {
                $modelFqcns[$keyName] = $result['modelFqcn'];
            }
        }

        return [
            'properties' => $properties,
            'enumResources' => $enumResources,
            'nestedResources' => $nestedResources,
            'directEnumFqcns' => $directEnumFqcns,
            'modelFqcns' => $modelFqcns,
        ];
    }

    /**
     * Check if an expression is a parent::toArray() call.
     */
    protected function isParentToArrayCall(Expr $expr): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->toLowerString() === 'parent'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'toArray';
    }

    /**
     * Resolve and analyze the parent resource's toArray() method.
     */
    protected function analyzeParentToArray(): ?ResourceAnalysis
    {
        $parentClass = $this->resourceReflection->getParentClass();

        if ($parentClass === false || ! is_a($parentClass->getName(), JsonResource::class, true)) {
            return null; // @codeCoverageIgnore
        }

        if ($parentClass->getName() === JsonResource::class) {
            return $this->buildModelDelegatedAnalysis();
        }

        $parentAnalyzer = new self(
            $parentClass,
            $this->modelClass,
        );

        return $parentAnalyzer->analyze();
    }

    /**
     * Resolve and analyze a $this->method() spread from a trait or the class itself.
     */
    protected function analyzeThisMethodSpread(string $methodName): ?ResourceAnalysis
    {
        if (! $this->resourceReflection->hasMethod($methodName)) {
            return null; // @codeCoverageIgnore
        }

        $method = $this->resourceReflection->getMethod($methodName);
        $filePath = $method->getFileName();

        if ($filePath === false) {
            return null; // @codeCoverageIgnore
        }

        $source = (string) file_get_contents($filePath);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        /** @var list<Node\Stmt> $stmts */
        $stmts = $parser->parse($source);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $stmts = $traverser->traverse($stmts);

        $finder = new NodeFinder;
        $targetMethod = $finder->findFirst($stmts, function (Node $node) use ($methodName): bool {
            return $node instanceof ClassMethod && $node->name->toString() === $methodName;
        });

        if (! $targetMethod instanceof ClassMethod || $targetMethod->stmts === null) {
            return null; // @codeCoverageIgnore
        }

        $returnStmt = $finder->findFirst($targetMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_;
        });

        if (! $returnStmt instanceof Return_ || ! $returnStmt->expr instanceof Array_) {
            return null; // @codeCoverageIgnore
        }

        $analysis = $this->analyzeReturnArray($returnStmt->expr);

        // Apply PHPDoc @return array shape types to resolve unknown property types
        $docTypes = $this->parseReturnArrayShape($method);

        if ($docTypes !== []) {
            $tsMap = LaravelTsPublish::typesMap();

            foreach ($analysis->properties as &$prop) {
                if ($prop['type'] !== 'unknown' || ! isset($docTypes[$prop['name']])) {
                    continue;
                }

                $prop['type'] = $this->resolvePhpDocType($docTypes[$prop['name']], $tsMap);
            }

            unset($prop);
        }

        // Apply #[TsResourceCasts] attribute overrides from the method
        foreach ($method->getAttributes(TsResourceCasts::class) as $attr) {
            $instance = $attr->newInstance();

            foreach ($instance->types as $property => $value) {
                $type = is_array($value) ? $value['type'] : $value;
                $optional = is_array($value) && isset($value['optional']) ? (bool) $value['optional'] : null;

                $found = false;

                foreach ($analysis->properties as &$prop) {
                    if ($prop['name'] === $property) {
                        $prop['type'] = $type;

                        if ($optional !== null) {
                            $prop['optional'] = $optional;
                        }

                        $found = true;

                        break;
                    }
                }

                unset($prop);

                if (! $found) {
                    $analysis->properties[] = [
                        'name' => $property,
                        'type' => $type,
                        'optional' => $optional ?? false,
                        'description' => '',
                    ];
                }

                if (is_array($value) && isset($value['import'])) {
                    foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                        $analysis->customImports[$value['import']][] = $importName;
                    }
                }
            }
        }

        return $analysis;
    }

    /**
     * Parse a @return array shape PHPDoc annotation into a property-name → PHP-type map.
     *
     * Supports: @return array{key: type, key2: type2, ...}
     *
     * @return array<string, string>
     */
    protected function parseReturnArrayShape(\ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return [];
        }

        if (! preg_match('/@return\s+array\{([^}]+)\}/', $docComment, $matches)) {
            return [];
        }

        $result = [];
        $entries = explode(',', $matches[1]);

        foreach ($entries as $entry) {
            $entry = trim($entry);
            $entry = (string) preg_replace('/^\*\s*/', '', $entry);

            if (! str_contains($entry, ':')) {
                continue;
            }

            [$key, $type] = explode(':', $entry, 2);
            $result[trim($key)] = trim($type);
        }

        return $result;
    }

    /**
     * Convert a PHPDoc type string (e.g. "string|null") to its TypeScript equivalent.
     *
     * @param  array<string, string|(callable(): string)>  $tsMap
     */
    protected function resolvePhpDocType(string $phpType, array $tsMap): string
    {
        $parts = array_map('trim', explode('|', $phpType));
        $resolved = [];

        foreach ($parts as $part) {
            $lower = strtolower($part);
            $mapped = $tsMap[$lower] ?? null;

            if (is_string($mapped)) {
                $resolved[] = $mapped;
            } elseif (is_callable($mapped)) {
                $resolved[] = (string) $mapped();
            } else {
                $resolved[] = $part;
            }
        }

        return implode(' | ', array_unique($resolved));
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Check if a static call's first argument is a conditional expression
     * (e.g. $this->whenLoaded('relation'), $this->when(...), etc.).
     */
    protected function hasConditionalArgument(StaticCall $call): bool
    {
        $args = $call->getArgs();

        if (count($args) < 1) {
            return false;
        }

        $inner = $args[0]->value;

        foreach ($this->conditionalMethods as $method) {
            if ($this->isThisMethodCall($inner, $method)) {
                return true;
            }
        }

        return false;
    }

    protected function isThisMethodCall(Expr $expr, string $methodName): bool
    {
        return $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === $methodName;
    }

    protected function isThisPropertyFetch(Expr $expr): bool
    {
        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this';
    }

    protected function resolveKeyName(Expr $key): ?string
    {
        if ($key instanceof String_) {
            return $key->value;
        }

        return null;
    }

    protected function resolveStaticCallClassName(StaticCall $call): ?string
    {
        if ($call->class instanceof Name) {
            return $call->class->toString();
        }

        return null; // @codeCoverageIgnore
    }

    protected function isEnumResourceClass(string $fqcn): bool
    {
        return $fqcn === EnumResource::class
            || $fqcn === 'EnumResource'
            || is_a($fqcn, EnumResource::class, true);
    }

    protected function isResourceClass(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
    }

    // -------------------------------------------------------------------------
    // Model resolution helpers
    // -------------------------------------------------------------------------

    /**
     * Build a ResourceAnalysis from all model attributes when the resource
     * delegates to JsonResource::toArray() (which returns $this->resource->toArray()).
     */
    protected function buildModelDelegatedAnalysis(): ?ResourceAnalysis
    {
        if ($this->modelAttributes === null) {
            return null;
        }

        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];

        foreach ($this->modelAttributes as $attr) {
            $info = $this->resolveModelAttributeTypeInfo($attr['name']);

            $properties[] = [
                'name' => $attr['name'],
                'type' => $info['type'],
                'optional' => false,
                'description' => '',
            ];

            if ($info['enumFqcn'] !== null) {
                $directEnumFqcns[$attr['name']] = $info['enumFqcn'];
            }
        }

        return new ResourceAnalysis(
            properties: $properties,
            directEnumFqcns: $directEnumFqcns,
        );
    }

    protected function loadModelInspectorData(): void
    {
        if ($this->modelClass === null || ! class_exists($this->modelClass)) {
            return;
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = resolve($this->modelClass);
            $this->modelInstance = $modelInstance;

            $data = resolve(ModelInspector::class)->inspect($this->modelClass);
            /** @var Collection<int, ModelAttributeInfo> $attributes */
            $attributes = $data->attributes;
            $this->modelAttributes = $attributes;
            $this->modelRelations = $data->relations;
            $this->relationNullable = new RelationNullable($this->modelInstance, $this->modelAttributes);
        } catch (\Throwable) { // @codeCoverageIgnore
            // Model may not have a working database connection during analysis
        }
    }

    /**
     * Resolve the TypeScript type and optional enum FQCN for a model attribute.
     *
     * @return array{type: string, enumFqcn: class-string|null}
     */
    protected function resolveModelAttributeTypeInfo(string $attributeName): array
    {
        if ($this->modelAttributes === null) {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $attr = $this->modelAttributes->firstWhere('name', $attributeName);

        if ($attr === null) {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        // Attribute accessors need special handling — resolve from DB column type
        $cast = $attr['cast'];

        if ($cast !== null && $cast !== '' && $cast !== 'attribute' && $cast !== 'accessor') {
            $tsInfo = LaravelTsPublish::phpToTypeScriptType($cast);

            /** @var class-string|null $enumFqcn */
            $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

            return ['type' => $tsInfo['type'], 'enumFqcn' => $enumFqcn];
        }

        // Fall back to DB column type
        if ($attr['type'] === null || $attr['type'] === '') {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $tsInfo = LaravelTsPublish::phpToTypeScriptType($attr['type']);
        $type = $tsInfo['type'];

        if ($attr['nullable'] && $type !== 'unknown') {
            $type .= ' | null';
        }

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return ['type' => $type, 'enumFqcn' => $enumFqcn];
    }

    /**
     * Resolve the enum class for a model property (if it's cast to an enum).
     *
     * @return class-string|null
     */
    protected function resolveModelPropertyEnumClass(string $propertyName): ?string
    {
        if ($this->modelAttributes === null) {
            return null;
        }

        $attr = $this->modelAttributes->firstWhere('name', $propertyName);

        if ($attr === null || $attr['cast'] === null) {
            return null;
        }

        $cast = $attr['cast'];

        // Check if the cast is an enum class
        if (class_exists($cast) && (new ReflectionClass($cast))->isEnum()) {
            return $cast;
        }

        return null;
    }

    /**
     * @return array{type: string, modelFqcn: class-string|null}
     */
    protected function resolveModelRelationTypeInfo(string $relationName): array
    {
        if ($this->modelRelations === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relation = $this->modelRelations->firstWhere('name', $relationName);

        if ($relation === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relatedModel = class_basename($relation['related']);
        $containsMany = str_contains(strtolower($relation['type']), 'many');

        if ($containsMany) {
            return ['type' => $relatedModel.'[]', 'modelFqcn' => $relation['related']];
        }

        $type = $relatedModel;
        $nullableRelations = config()->boolean('ts-publish.nullable_relations');

        if ($nullableRelations && $this->relationNullable?->isNullable($relation)) {
            $type .= ' | null';
        }

        return ['type' => $type, 'modelFqcn' => $relation['related']];
    }
}
