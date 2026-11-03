<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\PrintsTopLevelMembers;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\RewritesEnumResourceProps;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\UnreadSpreads;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\InertiaRenderLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\DottedTopLevelKeys;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use AbeTwoThree\LaravelTsPublish\Support\TopLevelRenumbering;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\BinaryOp\Plus;
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
 * @phpstan-import-type TsCastsParseResult from InertiaSharedDataAnalyzer
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type TokenQueue from FileImportNames
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type PrintedMember from FileImportNames
 * @phpstan-import-type SentEntry from DottedTopLevelKeys
 *
 * @phpstan-type InertiaPageData = array{
 *     component: string|list<string>,
 *     pageType: string|list<string>|null,
 *     classFqcns: list<class-string>,
 *     pageQueues?: list<TokenQueue>,
 *     pageConstQueues?: list<TokenQueue>,
 *     enumConstFqcns?: list<class-string>,
 *     externalImports?: array<string, list<string>>,
 * }
 * @phpstan-type PagePropEntry = SentEntry
 * @phpstan-type PagePropMap = array<array-key, PagePropEntry>
 * @phpstan-type RenumberedAnalyses = array{list<ResourceAnalysis>, array<array-key, list<string>>}
 */
class InertiaPageAnalyzer
{
    use InspectsAstNodes;
    use PrintsTopLevelMembers;
    use RewritesEnumResourceProps;

    /** Depth cap for `$props = $other;` chains, which the flat bindings cannot prove acyclic. */
    private const MAX_VARIABLE_HOPS = 8;

    /** TypeScript's own generic heads a rendered type spells without a PHP class; not ImportNameRegistry's list. */
    private const UTILITY_TYPES = ['Record', 'Omit', 'Pick', 'Partial', 'Required', 'Readonly', 'Exclude', 'Extract', 'NonNullable'];

    /**
     * Analyze a controller action and extract Inertia page data.
     *
     * @param  array{uses: string}  $action  The route action array with 'uses' key (Controller@method format).
     * @return InertiaPageData|null Null when the action does not render an Inertia response.
     */
    public function analyze(array $action): ?array
    {
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
     * Type every render call in one controller action.
     *
     * @param  class-string  $controllerClass
     * @return InertiaPageData|null
     */
    protected function analyzeAction(string $controllerClass, string $methodName): ?array
    {
        $context = resolve(MethodLocator::class)->locateDeclared($controllerClass, $methodName);

        if ($context === null) {
            return null;
        }

        $this->markTableReads($context);
        $scope = resolve(AstEngine::class)->bindingsFor($context);
        $analyzer = $this->analyzerFor($context, $scope);
        $branches = $this->collectComponentBranches($context, $analyzer, $scope);

        if ($branches === []) {
            return null;
        }

        $parsed = $this->parseTsCastsFromMethod($controllerClass, $methodName);

        return $this->buildPageData(
            $branches,
            $analyzer,
            $parsed['overrides'],
            $parsed['importPaths'] ?? [], // @phpstan-ignore nullCoalesce.offset (a 2.7-shaped override omits it)
            $parsed['optionalOverrides'] ?? [],
            $controllerClass.'@'.$methodName,
        );
    }

    /**
     * Build the expression engine for one action: reading what Inertia sends, over a scope already carrying the
     * action's route-bound models, request parameters, and local variables.
     */
    protected function analyzerFor(MethodContext $context, AnalysisScope $scope): ResourceAstAnalyzer
    {
        return new ResourceAstAnalyzer(
            $context->reflection,
            null,
            $context->method->name->toString(),
            scope: $scope,
            reading: Reading::Inertia,
        );
    }

    /**
     * Group the action's render calls by component name, one analysis per props array a call may send.
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

            $branches[$call->nameArg->value] = [
                ...$branches[$call->nameArg->value] ?? [],
                ...$this->analyzeProps($call->propsArg, $context, $analyzer, $scope),
            ];
        }

        return $branches;
    }

    /**
     * Analyze one render call's props argument into each props array it may send, with properties and FQCN channels.
     *
     * @return non-empty-list<ResourceAnalysis>
     */
    protected function analyzeProps(
        ?Expr $propsArg,
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): array {
        if ($propsArg === null) {
            return [new ResourceAnalysis];
        }

        return $this->readProps($propsArg, $context, $analyzer, $scope)
            ?? [$this->analyzeDelegatedProps($propsArg, $context)];
    }

    /**
     * Each props array an argument may hold: one per literal, else one per path of a walked local or a `+`.
     *
     * @return non-empty-list<ResourceAnalysis>|null
     */
    protected function readProps(
        Expr $propsArg,
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): ?array {
        $literals = $this->propsArrayLiterals($propsArg, $scope);

        if ($literals !== []) {
            return $this->literalProps($literals, $analyzer);
        }

        $read = $this->pathProps($propsArg, $context, $analyzer, $scope);

        return $read === null ? null : $this->presenceArms($read);
    }

    /**
     * Re-express a props argument as the array literal(s) it evaluates to: an inline array, a
     * `compact()` call, an `array_merge()` of literals, each write a variable's read sees, or a ternary of those.
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

        if ($propsArg instanceof Variable) {
            $literals = array_map(
                fn (Expr $write): array => $this->propsArrayLiterals($write, $scope, $hops + 1),
                $this->spelledWrites($propsArg, $scope) ?? [],
            );

            // A write it cannot re-express leaves its branch unread, so the walk reads the local instead.
            return in_array([], $literals, true) ? [] : array_merge(...$literals);
        }

        if ($propsArg instanceof FuncCall) {
            $compacted = $this->compactedArrayLiteral($propsArg);

            return $compacted === null ? $this->mergedArrayLiterals($propsArg, $scope) : [$compacted];
        }

        return [];
    }

    /**
     * Re-express `compact('post', 'comments')` as `['post' => $post, 'comments' => $comments]`, so the
     * variable bindings type the props exactly as the equivalent literal would. Each variable sits where its name
     * does, so a read past the write that ended a parameter's binding takes what the action assigned.
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

            $items[] = new ArrayItem(new Variable($arg->value->value, $arg->value->getAttributes()), new String_($arg->value->value));
        }

        return $items === [] ? null : new Array_($items);
    }

    /**
     * Type a props argument delegated to a collaborator — `Inertia::render('X', $this->props->build())` —
     * by analyzing that method, as Inertia sends what it returns, on the property's own class, which the container may
     * fill with a subclass unless it is final, so the body only assumes it.
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
        $analysis->merge(resolve(AstEngine::class)->analyzeMethod(
            $propertyClass,
            $propsArg->name->toString(),
            runtime: RuntimeClass::injected(new ReflectionClass($propertyClass)),
            reading: Reading::Inertia,
        ));

        return $analysis;
    }

    /**
     * Build the InertiaPageData contract from the per-component branch analyses.
     *
     * @param  array<string, list<ResourceAnalysis>>  $branches
     * @param  array<array-key, string>  $overrides  TsCasts overrides from the controller method
     * @param  array<string, string>  $importPaths  each override's `import` path, by its key
     * @param  array<array-key, bool>  $optionalOverrides  each override's own `optional` flag, by its key
     * @param  string  $subject  the `Controller@method` action a warning names
     * @return InertiaPageData
     */
    protected function buildPageData(
        array $branches,
        ResourceAstAnalyzer $analyzer,
        array $overrides,
        array $importPaths,
        array $optionalOverrides = [],
        string $subject = '',
    ): array {
        $components = array_keys($branches);
        /** @var list<string> $pageTypes */
        $pageTypes = [];
        /** @var list<class-string> $allFqcns */
        $allFqcns = [];
        /** @var list<class-string> $constFqcns */
        $constFqcns = [];
        /** @var list<TokenQueue> $pageQueues */
        $pageQueues = [];
        /** @var list<TokenQueue> $pageConstQueues */
        $pageConstQueues = [];
        /** @var array<string, list<string>> $externalImports */
        $externalImports = [];

        foreach ($branches as $analyses) {
            [$analyses, $sent] = $this->renumberEach($analyses, $subject);
            $analysis = count($analyses) === 1 ? $analyses[0] : $analyzer->mergeReturnBranches($analyses);
            $keys = array_column($analysis->properties, 'name');
            $castKeys = $this->castKeysAsSent(array_keys($overrides), $sent, $keys, $subject);
            $sentOverrides = TopLevelRenumbering::rekey(array_intersect_key($overrides, $castKeys), $castKeys);
            $targets = JsEmitter::castTargets(array_keys($sentOverrides), $keys);
            JsEmitter::warnAmbiguousCasts($subject, array_keys($sentOverrides), $keys);
            $casts = JsEmitter::retargetCasts($sentOverrides, $targets);
            $paths = JsEmitter::retargetCasts(
                TopLevelRenumbering::rekey(array_intersect_key($importPaths, $castKeys), $castKeys),
                $targets,
            );
            $castImports = [];

            // A losing spelling's import goes with its type.
            foreach ($paths as $key => $path) {
                $castImports[$path] = [
                    ...$castImports[$path] ?? [],
                    ...TsTypeString::extractImportableTypes($casts[$key] ?? ''),
                ];
            }

            $externalImports = $this->withImports($externalImports, $castImports);

            // Each props literal was reconciled alone, and the controller's own casts are laid over the props below.
            resolve(IndexSignatureReconciler::class)->reconcile($analysis, $casts, subject: $subject);
            resolve(CastChannels::class)->fit($analysis, $this->castsInForce($analysis, $casts, $paths));

            $props = $this->rewriteEnumResourceTypes($this->collectProps($analysis), $analysis);
            $members = $this->buildMembersWithOverrides(
                $props,
                $casts,
                JsEmitter::retargetCasts(
                    TopLevelRenumbering::rekey(array_intersect_key($optionalOverrides, $castKeys), $castKeys),
                    $targets,
                ),
                $this->renumberTopLevel($props, $casts, $subject),
            );

            // Only this branch's own imports decide which tokens the fallback leaves alone, whatever branch came first.
            $brought = ImportMaps::localNames(ImportMaps::merge($castImports, $analysis->customImports));
            $page = $members === []
                ? ['type' => 'Inertia.SharedData', 'classes' => [], 'consts' => []]
                : FileImportNames::objectMember($analysis, $members, 'Inertia.SharedData & ', $brought);
            $pageType = $page['type'];

            $pageTypes[] = $pageType;
            $pageQueues[] = $page['classes'];
            $pageConstQueues[] = $page['consts'];

            foreach ($this->usedFqcns($analysis, $pageType) as $fqcn) {
                if (! in_array($fqcn, $allFqcns, true)) {
                    $allFqcns[] = $fqcn;
                }
            }

            foreach ($this->spelledEnumConsts($analysis, $pageType) as $fqcn) {
                if (! in_array($fqcn, $constFqcns, true)) {
                    $constFqcns[] = $fqcn;
                }
            }

            $externalImports = $this->withImports($externalImports, $analysis->customImports);
        }

        return [
            'component' => count($components) === 1 ? $components[0] : $components,
            'pageType' => count($pageTypes) === 1 ? $pageTypes[0] : $pageTypes,
            'classFqcns' => $allFqcns,
            'pageQueues' => $pageQueues,
            'pageConstQueues' => $pageConstQueues,
            'enumConstFqcns' => $constFqcns,
            'externalImports' => $externalImports,
        ];
    }

    /**
     * Flatten the analysis into a prop map, later declarations of a key winning; one a dotted key replaces is `closed`.
     *
     * @return PagePropMap
     */
    protected function collectProps(MethodAnalysis $analysis): array
    {
        $props = [];

        foreach ($analysis->properties as $property) {
            $props[$property['name']] = [
                'type' => $property['type'],
                'optional' => $property['optional'],
                ...(isset($analysis->closedToDottedKeys[$property['name']]) ? ['closed' => true] : []),
            ];
        }

        return $props;
    }

    /**
     * The key Laravel sends each top-level prop on, warning for each it renumbers; assumes shared data has no int key.
     *
     * Each props array and each action cast is numbered before this, so a page's keys arrive as Laravel sends them.
     *
     * @param  PagePropMap  $props
     * @param  array<array-key, string>  $casts
     * @return array<array-key, string>
     */
    protected function renumberTopLevel(array $props, array $casts, string $subject): array
    {
        $renumbered = TopLevelRenumbering::renumber(array_map(strval(...), array_keys(array_replace($props, $casts))));
        TopLevelRenumbering::warnRenumbered($subject, 'a page', $renumbered);

        return $renumbered;
    }

    /**
     * Print each prop as an object type's member, a TsCasts override in its place, where Inertia sends its key.
     *
     * @param  PagePropMap  $props
     * @param  array<array-key, string>  $overrides
     * @param  array<array-key, bool>  $optionalOverrides  each override's own `optional` flag, by its key
     * @param  array<array-key, string>  $renumbered  the key Laravel sends each prop on, by its name
     * @return list<PrintedMember>
     */
    protected function buildMembersWithOverrides(
        array $props,
        array $overrides,
        array $optionalOverrides = [],
        array $renumbered = [],
    ): array {
        $casts = [];

        // A cast that says nothing about optional keeps the prop's own flag.
        foreach ($overrides as $key => $type) {
            $optional = $optionalOverrides[$key] ?? $props[$key]['optional'] ?? false;
            $casts[$key] = JsEmitter::signatureSafeMember((string) $key, $type, $optional);
        }

        // A cast keeps its prop's place, and one no prop has goes after them.
        return $this->printTopLevel($props, $casts, $renumbered);
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
            ...$analysis->allCarriedFqcns(),
        ];

        foreach ([$analysis->multiEnumResourceFqcns, $analysis->inlineEnumFqcns, $analysis->inlineModelFqcns, $analysis->inlineEnumResourceFqcns] as $map) {
            foreach ($map as $fqcns) {
                $candidates = [...$candidates, ...$fqcns];
            }
        }

        return array_values(array_filter(
            array_unique($candidates),
            // An enum's type name follows #[TsEnum(name:)]; its bare name is the const, which only a `typeof` reads.
            fn (string $fqcn): bool => enum_exists($fqcn)
                ? array_any(ValueResult::enumNames($fqcn), fn (string $name): bool => $this->typeSpells($pageType, $name))
                : $this->typeSpells($pageType, class_basename($fqcn)) || $this->typeSpells($pageType, TsNaming::resourceTypeName($fqcn)),
        ));
    }

    /**
     * Every enum whose const the rendered type reads through `typeof`, as an `AsEnum<typeof Status>` prop does.
     *
     * @return list<class-string>
     */
    protected function spelledEnumConsts(MethodAnalysis $analysis, string $pageType): array
    {
        $candidates = array_values($analysis->enumResources);

        foreach ([$analysis->multiEnumResourceFqcns, $analysis->inlineEnumResourceFqcns] as $map) {
            foreach ($map as $fqcns) {
                $candidates = [...$candidates, ...$fqcns];
            }
        }

        return array_values(array_filter(
            array_unique($candidates),
            fn (string $fqcn): bool => preg_match(
                TsTypeString::queuedTokenPattern([ValueResult::enumConstName($fqcn)], 'typeof\s+\K'),
                $pageType,
            ) === 1,
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
     * @return TsCastsParseResult
     */
    protected function parseTsCastsFromMethod(string $controllerClass, string $methodName): array
    {
        if (! class_exists($controllerClass)) {
            return ['overrides' => [], 'importPaths' => []];
        }

        $reflection = new ReflectionClass($controllerClass);

        if (! $reflection->hasMethod($methodName)) {
            return ['overrides' => [], 'importPaths' => []]; // @codeCoverageIgnore
        }

        $attrs = $reflection->getMethod($methodName)->getAttributes(TsCasts::class);

        if ($attrs === []) {
            return ['overrides' => [], 'importPaths' => []];
        }

        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack([$attrs[0]->newInstance()]);

        return [
            'overrides' => $unpacked['overrides'],
            'importPaths' => $unpacked['importPaths'],
            'optionalOverrides' => $unpacked['optionalOverrides'],
        ];
    }

    /**
     * Each props array with its int keys renumbered as Laravel sends them, beside the names each sent key holds.
     *
     * Laravel's top-level `array_merge()` renumbers each array's int keys from 0; each key it moves warns.
     *
     * @param  list<ResourceAnalysis>  $analyses
     * @return RenumberedAnalyses
     */
    private function renumberEach(array $analyses, string $subject): array
    {
        $renumberedAnalyses = [];
        $sent = [];

        foreach ($analyses as $analysis) {
            $names = array_values(array_unique(array_map(strval(...), array_column($analysis->properties, 'name'))));
            $renumbered = TopLevelRenumbering::renumber($names);
            TopLevelRenumbering::warnRenumbered($subject, 'a page', $renumbered);

            foreach ($renumbered as $name => $key) {
                $sent[$key] = array_values(array_unique([...$sent[$key] ?? [], (string) $name]));
            }

            if (array_any($renumbered, static fn (string $key, int|string $name): bool => $key !== (string) $name)) {
                $analysis = clone $analysis;
                $analysis->renameKeys($renumbered);
            }

            $renumberedAnalyses[] = $analysis;
        }

        return [$renumberedAnalyses, $sent];
    }

    /**
     * The props a walked local or a `+` holds, once for each count of the int keys it may lack before a later int key.
     *
     * Such a key moves every later one down; lacking the first n of them puts each key on every number lacking any n
     * would. A key an arm keeps is sent there, unless its own value may be left out, as a deferred prop's is.
     *
     * @return non-empty-list<ResourceAnalysis>
     */
    private function presenceArms(ResourceAnalysis $analysis): array
    {
        $optional = [];

        foreach ($analysis->properties as $property) {
            if (PhpArrayKey::storedInt($property['name']) !== null) {
                $optional[$property['name']] = $property['optional'];
            }
        }

        $shifting = array_map(strval(...), array_keys(array_filter(array_slice($optional, 0, -1, true))));

        if ($shifting === []) {
            return [$analysis];
        }

        $arms = [];

        for ($lacked = 0; $lacked <= count($shifting); $lacked++) {
            $absent = array_slice($shifting, 0, $lacked);
            $arm = clone $analysis;
            $arm->properties = [];

            foreach ($analysis->properties as $property) {
                if (in_array($property['name'], $absent, true)) {
                    continue;
                }

                $sent = in_array($property['name'], $shifting, true) && ! isset($analysis->leftOutValues[$property['name']]);
                $arm->properties[] = $sent ? [...$property, 'optional' => false] : $property;
            }

            foreach ($absent as $name) {
                $arm->forgetChannels($name);
            }

            $arms[] = $arm;
        }

        return $arms;
    }

    /**
     * The key each action cast publishes on, as Laravel renumbers the int keys of the props arrays.
     *
     * A cast on a number another props array sends another key on retypes none, and warns; an int key none holds goes
     * after theirs.
     *
     * @param  list<array-key>  $castKeys
     * @param  array<array-key, list<string>>  $sent  each key Laravel sends => the names the props arrays hold there
     * @param  list<string>  $keys  the props' keys as Laravel sends them
     * @return array<array-key, string> each cast key kept => the key it publishes on
     */
    private function castKeysAsSent(array $castKeys, array $sent, array $keys, string $subject): array
    {
        $next = count(array_filter(
            array_unique($keys),
            static fn (string $key): bool => PhpArrayKey::storedInt($key) !== null,
        ));
        $sentOn = [];

        foreach ($sent as $key => $names) {
            foreach ($names as $name) {
                $sentOn[$name] ??= (string) $key;
            }
        }

        $kept = [];

        foreach ($castKeys as $castKey) {
            $name = (string) $castKey;
            $key = $sentOn[$name] ?? null;

            if ($key !== null && $sent[$key] !== [$name]) {
                AnalysisWarnings::addOnce($subject, sprintf(
                    'The #[TsCasts] key "%s" retypes none: Laravel renumbers it to "%s", which another props array sends '
                    .'another key on; use a string key.',
                    $name,
                    $key,
                ));
            } elseif ($key !== null) {
                $kept[$name] = $key;
            } elseif (PhpArrayKey::storedInt($name) !== null) {
                $kept[$name] = (string) $next++;
                TopLevelRenumbering::warnRenumbered($subject, 'a page', [$name => $kept[$name]]);
            } else {
                $kept[$name] = $name;
            }
        }

        return $kept;
    }

    /**
     * The cast in force for each key: a props helper's own method cast, under the action's cast with its import path.
     *
     * @param  array<array-key, string>  $casts  the action's casts, by the key each retypes
     * @param  array<array-key, string>  $paths  each of those casts' import path, by its key
     * @return CastMap
     */
    private function castsInForce(MethodAnalysis $analysis, array $casts, array $paths): array
    {
        $inForce = $analysis->casts;

        foreach ($casts as $key => $type) {
            $inForce[$key] = ['type' => $type, 'import' => $paths[$key] ?? null];
        }

        return $inForce;
    }

    /**
     * Marks each string-keyed value of the first render call's props literal that a table-rooted chain types.
     *
     * Where the table reader this analyzer once held typed such a chain, one nothing else reads keeps that type.
     */
    private function markTableReads(MethodContext $context): void
    {
        $locator = resolve(InertiaRenderLocator::class);
        $render = $locator->findRenderCall($context->method);

        foreach (($render === null ? null : $locator->propsArray($render))->items ?? [] as $item) {
            if ($item->key instanceof String_) {
                $item->value->setAttribute(InertiaTableHandler::TYPED_AS_TABLE, true);
            }
        }
    }

    /**
     * Each literal read as a render call's whole props.
     *
     * @param  non-empty-list<Array_>  $literals
     * @return non-empty-list<ResourceAnalysis>
     */
    private function literalProps(array $literals, ResourceAstAnalyzer $analyzer): array
    {
        // This literal IS the render call's whole props shape, not an array embedded inside it —
        // top-level, so a top-level resolve()/toArray() spread inside it flattens instead of vanishing.
        return array_map(
            fn (Array_ $array): ResourceAnalysis => $analyzer->returnArrayAnalysis($array, topLevel: true),
            $literals,
        );
    }

    /**
     * The props a local walked from its writes before the render call, or a `+`, holds, its paths joined.
     */
    private function pathProps(
        Expr $propsArg,
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): ?ResourceAnalysis {
        if ($propsArg instanceof Variable) {
            return $analyzer->variableAnalysis($propsArg, $context->method->stmts ?? []);
        }

        if (! $propsArg instanceof Plus) {
            return null;
        }

        $unread = UnreadSpreads::count();
        $left = $this->operandProps($propsArg->left, $context, $analyzer, $scope);

        // `+` keeps each key its left side holds, so a key the left may hold unread, or a signature may stand for, may
        // take the right's place.
        if ($left === null || ! $left->readsCompletely || UnreadSpreads::count() !== $unread
            || array_any($left->properties, fn (array $prop): bool => JsEmitter::isIndexSignatureKey($prop['name']))) {
            return null;
        }

        $right = $this->operandProps($propsArg->right, $context, $analyzer, $scope);

        return $right === null ? null : $this->plusProps($left, $right, $analyzer);
    }

    /**
     * An operand of `+` as one analysis: its literals joined, else the local or `+` it holds.
     */
    private function operandProps(
        Expr $operand,
        MethodContext $context,
        ResourceAstAnalyzer $analyzer,
        AnalysisScope $scope,
    ): ?ResourceAnalysis {
        $literals = $this->propsArrayLiterals($operand, $scope);

        if ($literals === []) {
            return $this->pathProps($operand, $context, $analyzer, $scope);
        }

        $analyses = $this->literalProps($literals, $analyzer);

        return count($analyses) === 1 ? $analyses[0] : $analyzer->mergeReturnBranches($analyses);
    }

    /**
     * The props `$left + $right` holds: on the path where the left lacks each key it may lack, the right's value lands.
     */
    private function plusProps(ResourceAnalysis $left, ResourceAnalysis $right, ResourceAstAnalyzer $analyzer): ResourceAnalysis
    {
        // With no key the left may lack, both paths are one.
        if (! array_any($left->properties, fn (array $prop): bool => $prop['optional'])) {
            $right->dropKeysHeldBy($left);
            $left->merge($right);

            return $left;
        }

        $present = clone $left;
        $present->properties = array_map(fn (array $prop): array => [...$prop, 'optional' => false], $left->properties);
        $absent = clone $left;
        $absent->properties = [];

        foreach ($left->properties as $prop) {
            if ($prop['optional']) {
                $absent->forgetChannels($prop['name']);
            } else {
                $absent->properties[] = $prop;
            }
        }

        foreach ([$present, $absent] as $branch) {
            $added = clone $right;
            $added->dropKeysHeldBy($branch);
            $branch->merge($added);
        }

        return $analyzer->mergeReturnBranches([$present, $absent]);
    }

    /**
     * The imports with each name the others bring appended under its path, once, in the order they bring them.
     *
     * @param  TypesImportMap  $imports
     * @param  TypesImportMap  $more
     * @return TypesImportMap
     */
    private function withImports(array $imports, array $more): array
    {
        foreach ($more as $path => $names) {
            foreach ($names as $name) {
                if (! in_array($name, $imports[$path] ?? [], true)) {
                    $imports[$path][] = $name;
                }
            }
        }

        return $imports;
    }
}
