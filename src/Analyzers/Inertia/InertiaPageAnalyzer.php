<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\InertiaRenderLocator;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use Throwable;

/**
 * Detects Inertia::render() calls in controller actions and types their page props with the AST engine.
 *
 * @phpstan-import-type TsCastsUnpacked from TsCastsReader
 *
 * @phpstan-type InertiaPageData = array{
 *     component: string|list<string>,
 *     pageType: string|list<string>|null,
 *     classFqcns: list<class-string>,
 *     externalImports?: array<string, list<string>>,
 * }
 * @phpstan-type PagePropEntry = array{type: string, optional: bool}
 * @phpstan-type PagePropMap = array<string, PagePropEntry>
 */
class InertiaPageAnalyzer
{
    use InspectsAstNodes;

    /** Depth cap for `$props = $other;` chains, which the flat bindings cannot prove acyclic. */
    private const MAX_VARIABLE_HOPS = 8;

    /** TypeScript's own generic heads, which a rendered type spells without naming any PHP class. */
    private const UTILITY_TYPES = ['Record', 'Omit', 'Pick', 'Partial', 'Required', 'Readonly', 'Exclude', 'Extract', 'NonNullable'];

    /**
     * Create the analyzer with an optional table analyzer override.
     */
    public function __construct(protected ?InertiaTableAnalyzer $tableAnalyzer = null) {}

    /**
     * Analyze a controller action and extract Inertia page data.
     *
     * @param  array{uses: string}  $action  The route action array with 'uses' key (Controller@method format).
     * @return InertiaPageData|null Null when the action does not render an Inertia response.
     */
    public function analyze(array $action): ?array
    {
        $tableData = $this->resolveTableAnalyzer()->analyze($action['uses']);

        if ($tableData !== null) {
            return $tableData;
        }

        if (! str_contains($action['uses'], '@')) {
            return null;
        }

        [$controllerClass, $methodName] = explode('@', $action['uses'], 2);

        if (! class_exists($controllerClass)) {
            return null;
        }

        DependencyRecorder::recordClass($controllerClass);

        try {
            return $this->analyzeAction($controllerClass, $methodName);
        } catch (Throwable $e) {
            // Analysis reflects application classes, so one unloadable dependency must degrade a
            // single action to "no page type" rather than abort the whole ts:publish run.
            AnalysisWarnings::add($action['uses'], $e::class.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Convert a component name to a fully-qualified Inertia namespace path.
     *
     * Inertia resolves pages through Laravel's FileViewFinder, which splits the `::` namespace hint and
     * rewrites every `.` to `/`, so dots separate directories exactly as slashes do. Empty segments are
     * dropped because `Settings//General` names the same page file as `Settings/General`.
     *
     * @example "Dashboard" → "Inertia.Pages.Dashboard"
     * @example "Settings/General" → "Inertia.Pages.Settings.General"
     * @example "billing.payment-methods" → "Inertia.Pages.Billing.PaymentMethods"
     */
    public function componentToFqn(string $component): string
    {
        $normalized = str_replace(['::', '.'], '/', $component);

        return collect(explode('/', $normalized))
            ->filter(fn (string $part): bool => $part !== '')
            ->map(fn (string $part): string => Str::studly($part))
            ->prepend('Inertia.Pages')
            ->implode('.');
    }

    /**
     * Resolve the static Inertia UI Table analyzer.
     */
    protected function resolveTableAnalyzer(): InertiaTableAnalyzer
    {
        return $this->tableAnalyzer ??= resolve(InertiaTableAnalyzer::class);
    }

    /**
     * Type every render call in one controller action.
     *
     * @param  class-string  $controllerClass
     * @return InertiaPageData|null
     */
    protected function analyzeAction(string $controllerClass, string $methodName): ?array
    {
        $context = resolve(MethodLocator::class)->locateOwn($controllerClass, $methodName);

        if ($context === null) {
            return null;
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);
        $analyzer = $this->analyzerFor($context, $scope);
        $branches = $this->collectComponentBranches($context, $analyzer, $scope);

        if ($branches === []) {
            return null;
        }

        $parsed = $this->parseTsCastsFromMethod($controllerClass, $methodName);

        return $this->buildPageData($branches, $analyzer, $parsed['overrides'], $parsed['importMap']);
    }

    /**
     * Build the expression engine for one action: the controller handler profile over a scope
     * already carrying the action's route-bound models, request parameters, and local variables.
     */
    protected function analyzerFor(MethodContext $context, AnalysisScope $scope): ResourceAstAnalyzer
    {
        return new ResourceAstAnalyzer(
            $context->reflection,
            null,
            $context->method->name->toString(),
            ControllerExpressionHandlers::make(),
            $scope,
        );
    }

    /**
     * Group the action's render calls by component name, one analysis per call.
     *
     * @return array<string, list<ResourceAnalysis>>
     */
    protected function collectComponentBranches(
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): array {
        $branches = [];

        foreach (resolve(InertiaRenderLocator::class)->findRenderCalls($context->method) as $call) {
            if (! $call->nameArg instanceof String_) {
                continue;
            }

            $branches[$call->nameArg->value][] = $this->analyzeProps($call->propsArg, $context, $analyzer, $scope);
        }

        return $branches;
    }

    /**
     * Analyze one render call's props argument into properties and FQCN channels.
     */
    protected function analyzeProps(
        ?Expr $propsArg,
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): ResourceAnalysis {
        if ($propsArg === null) {
            return new ResourceAnalysis;
        }

        $literals = $this->propsArrayLiterals($propsArg, $scope);

        if ($literals === []) {
            return $this->analyzeDelegatedProps($propsArg, $context);
        }

        // This literal IS the render call's whole props shape, not an array embedded inside it —
        // top-level, so a top-level resolve()/toArray() spread inside it flattens instead of vanishing.
        $analyses = array_map(
            fn (Array_ $array): ResourceAnalysis => $analyzer->returnArrayAnalysis($array, topLevel: true),
            $literals,
        );

        return count($analyses) === 1 ? $analyses[0] : $analyzer->mergeReturnBranches($analyses);
    }

    /**
     * Re-express a props argument as the array literal(s) it evaluates to: an inline array, a
     * `compact()` call, an `array_merge()` of literals, a bound variable, or a ternary of those.
     *
     * @return list<Array_>
     */
    protected function propsArrayLiterals(Expr $propsArg, AnalysisScope $scope, int $hops = 0): array
    {
        if ($propsArg instanceof Array_) {
            return [$propsArg];
        }

        if ($hops >= self::MAX_VARIABLE_HOPS) {
            return []; // @codeCoverageIgnore
        }

        if ($propsArg instanceof Ternary) {
            $arms = array_values(array_filter([$propsArg->if, $propsArg->else]));

            return array_merge(...array_map(
                fn (Expr $arm): array => $this->propsArrayLiterals($arm, $scope, $hops + 1),
                $arms,
            ));
        }

        if ($propsArg instanceof Variable && is_string($propsArg->name)) {
            $bound = $scope->localVarBindings[$propsArg->name] ?? null;

            return $bound === null ? [] : $this->propsArrayLiterals($bound, $scope, $hops + 1);
        }

        if ($propsArg instanceof FuncCall) {
            $compacted = $this->compactedArrayLiteral($propsArg);

            if ($compacted !== null) {
                return [$compacted];
            }

            $merged = $this->mergedArrayLiteral($propsArg, null, $scope->localVarBindings);

            if ($merged !== null) {
                return [$merged];
            }
        }

        return [];
    }

    /**
     * Re-express `compact('post', 'comments')` as `['post' => $post, 'comments' => $comments]`, so the
     * variable bindings type the props exactly as the equivalent literal would.
     */
    protected function compactedArrayLiteral(FuncCall $call): ?Array_
    {
        if (! $call->name instanceof Name || $call->name->getLast() !== 'compact' || $call->isFirstClassCallable()) {
            return null;
        }

        $items = [];

        foreach ($call->getArgs() as $arg) {
            if (! $arg->value instanceof String_) {
                return null;
            }

            $items[] = new ArrayItem(new Variable($arg->value->value), new String_($arg->value->value));
        }

        return $items === [] ? null : new Array_($items);
    }

    /**
     * Type a props argument delegated to a collaborator — `Inertia::render('X', $this->props->build())` —
     * by analyzing that method on the property's own class.
     */
    protected function analyzeDelegatedProps(Expr $propsArg, MethodContext $context): ResourceAnalysis
    {
        if (! $propsArg instanceof MethodCall || ! $propsArg->name instanceof Identifier) {
            return new ResourceAnalysis;
        }

        $propertyClass = resolve(CallMatcher::class)->resolveThisPropertyClass($context->reflection, $propsArg->var);

        if ($propertyClass === null) {
            return new ResourceAnalysis;
        }

        $analysis = new ResourceAnalysis;
        $analysis->merge(resolve(AstEngine::class)->analyzeMethod($propertyClass, $propsArg->name->toString()));

        return $analysis;
    }

    /**
     * Build the InertiaPageData contract from the per-component branch analyses.
     *
     * @param  array<string, list<ResourceAnalysis>>  $branches
     * @param  array<string, string>  $overrides  TsCasts overrides from the controller method
     * @param  array<string, list<string>>  $importMap  Import map from TsCasts `import` keys
     * @return InertiaPageData
     */
    protected function buildPageData(
        array $branches,
        ResourceAstAnalyzer $analyzer,
        array $overrides,
        array $importMap,
    ): array {
        $components = array_keys($branches);
        /** @var list<string> $pageTypes */
        $pageTypes = [];
        /** @var list<class-string> $allFqcns */
        $allFqcns = [];
        /** @var array<string, list<string>> $externalImports */
        $externalImports = $importMap;

        foreach ($branches as $analyses) {
            $analysis = count($analyses) === 1 ? $analyses[0] : $analyzer->mergeReturnBranches($analyses);

            $this->forgetOverriddenChannels($analysis, $overrides);

            $props = $this->collectProps($analysis);
            $pageType = $props === [] && $overrides === []
                ? 'Inertia.SharedData'
                : 'Inertia.SharedData & '.$this->buildTypeStringWithOverrides($props, $overrides);

            $pageTypes[] = $pageType;

            foreach ($this->usedFqcns($analysis, $pageType) as $fqcn) {
                if (! in_array($fqcn, $allFqcns, true)) {
                    $allFqcns[] = $fqcn;
                }
            }

            foreach ($analysis->customImports as $path => $types) {
                foreach ($types as $type) {
                    if (! in_array($type, $externalImports[$path] ?? [], true)) {
                        $externalImports[$path][] = $type;
                    }
                }
            }
        }

        return [
            'component' => count($components) === 1 ? $components[0] : $components,
            'pageType' => count($pageTypes) === 1 ? $pageTypes[0] : $pageTypes,
            'classFqcns' => $allFqcns,
            'externalImports' => $externalImports,
        ];
    }

    /**
     * Flatten the analysis into a prop map, later declarations of a key winning.
     *
     * @return PagePropMap
     */
    protected function collectProps(MethodAnalysis $analysis): array
    {
        $props = [];

        foreach ($analysis->properties as $property) {
            $props[$property['name']] = ['type' => $property['type'], 'optional' => $property['optional']];
        }

        return $props;
    }

    /**
     * Build a TypeScript object type string, applying TsCasts overrides for specific props.
     *
     * @param  PagePropMap  $props
     * @param  array<string, string>  $overrides
     */
    protected function buildTypeStringWithOverrides(array $props, array $overrides): string
    {
        if ($props === [] && $overrides === []) {
            return 'Record<string, never>'; // @codeCoverageIgnore
        }

        $parts = [];

        foreach ($props as $key => $prop) {
            $parts[] = isset($overrides[$key])
                ? $key.': '.$overrides[$key]
                : $key.($prop['optional'] ? '?: ' : ': ').$prop['type'];
        }

        foreach ($overrides as $key => $type) {
            if (! array_key_exists($key, $props)) {
                $parts[] = $key.': '.$type;
            }
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Drop every FQCN channel belonging to an overridden key, so its import dies with its type.
     *
     * @param  array<string, string>  $overrides
     */
    protected function forgetOverriddenChannels(MethodAnalysis $analysis, array $overrides): void
    {
        foreach (array_keys($overrides) as $name) {
            unset(
                $analysis->enumResources[$name], $analysis->nestedResources[$name], $analysis->directEnumFqcns[$name],
                $analysis->modelFqcns[$name], $analysis->multiEnumResourceFqcns[$name], $analysis->inlineEnumFqcns[$name],
                $analysis->inlineModelFqcns[$name], $analysis->inlineEnumResourceFqcns[$name],
            );
        }
    }

    /**
     * Every FQCN the rendered type actually spells, so no import outlives the type that needed it.
     *
     * @return list<class-string>
     */
    protected function usedFqcns(MethodAnalysis $analysis, string $pageType): array
    {
        /** @var list<class-string> $candidates */
        $candidates = [
            ...array_values($analysis->enumResources),
            ...array_values($analysis->directEnumFqcns),
            ...array_values($analysis->nestedResources),
            ...array_values($analysis->modelFqcns),
        ];

        foreach ([$analysis->multiEnumResourceFqcns, $analysis->inlineEnumFqcns, $analysis->inlineModelFqcns, $analysis->inlineEnumResourceFqcns] as $map) {
            foreach ($map as $fqcns) {
                $candidates = [...$candidates, ...$fqcns];
            }
        }

        return array_values(array_filter(
            array_unique($candidates),
            // An enum's TypeScript name follows #[TsEnum(name:)], which neither of the first two spell.
            fn (string $fqcn): bool => $this->typeSpells($pageType, class_basename($fqcn))
                || $this->typeSpells($pageType, TsNaming::resourceTypeName($fqcn))
                || (enum_exists($fqcn) && $this->typeSpells($pageType, LaravelTsPublish::toTsType($fqcn)['type'])),
        ));
    }

    /**
     * Whether a rendered type string names an identifier as a type of its own.
     *
     * The utility-type heads are erased first: a class whose basename is `Record` must not be kept
     * alive by the `Record<string, X>` a preserve-keys collection renders, which names no class.
     */
    protected function typeSpells(string $pageType, string $name): bool
    {
        $structural = (string) preg_replace('/\b(?:'.implode('|', self::UTILITY_TYPES).')</', '<', $pageType);

        return TsTypeString::typeNameOccursIn($name, $structural);
    }

    /**
     * Parse the `#[TsCasts]` attribute from a controller method.
     *
     * @return array{overrides: array<string, string>, importMap: array<string, list<string>>}
     */
    protected function parseTsCastsFromMethod(string $controllerClass, string $methodName): array
    {
        if (! class_exists($controllerClass)) {
            return ['overrides' => [], 'importMap' => []];
        }

        $reflection = new ReflectionClass($controllerClass);

        if (! $reflection->hasMethod($methodName)) {
            return ['overrides' => [], 'importMap' => []]; // @codeCoverageIgnore
        }

        $attrs = $reflection->getMethod($methodName)->getAttributes(TsCasts::class);

        if ($attrs === []) {
            return ['overrides' => [], 'importMap' => []];
        }

        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack([$attrs[0]->newInstance()]);

        return ['overrides' => $unpacked['overrides'], 'importMap' => $unpacked['importMap']];
    }
}
