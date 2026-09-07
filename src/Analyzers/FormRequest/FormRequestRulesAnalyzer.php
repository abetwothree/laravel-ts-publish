<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use BackedEnum;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\AnyOf;
use Illuminate\Validation\Rules\ArrayRule;
use Illuminate\Validation\Rules\Contains;
use Illuminate\Validation\Rules\Date;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\DoesntContain;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\ExcludeUnless;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Numeric;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\ProhibitedUnless;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\RequiredUnless;
use Illuminate\Validation\Rules\StringRule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationRuleParser;
use ReflectionClass;
use ReflectionException;
use Throwable;
use UnitEnum;

/**
 * Analyzes a FormRequest's `rules()` method and normalizes the result into a tree for interface generation.
 *
 * `rules()` is invoked against an empty HTTP context; one that reads request state throws and degrades to
 * a dynamic `Record<string, unknown>`.
 *
 * @phpstan-type RuleLeafData = array{
 *     tsType: string,
 *     isRequired: bool,
 *     isNullable: bool,
 *     isProhibited: bool,
 *     jsDocMetadata: list<string>,
 *     syntheticArrayKeys: array<string, bool>,
 * }
 */
class FormRequestRulesAnalyzer
{
    /** Sentinel standing in for an escaped `\.` while a rule key is split on its real separators. */
    private const DOT_PLACEHOLDER = "\x00ltsp-dot\x00";

    /** Rules resolveTsType() maps to `number` — shared so the in: coercion cannot drift from it. */
    private const NUMERIC_TYPE_RULES = ['integer', 'int', 'numeric', 'decimal', 'digits', 'digits_between'];

    /**
     * Whether the rules could not be resolved statically.
     */
    public protected(set) bool $isDynamic = false;

    /**
     * Analyze a FormRequest FQCN and return normalized rule nodes.
     *
     * @param  class-string<FormRequest>  $fqcn
     * @return list<FormRequestRuleNode>
     */
    public function analyze(string $fqcn): array
    {
        $this->isDynamic = false;

        $rules = $this->resolveRules($fqcn);

        if ($rules === null) {
            $this->isDynamic = true;

            return [];
        }

        return $this->normalizeRules($rules);
    }

    /**
     * Compose one rule by dotted path, so a caller typing `validated('a.b')` reads the same trie
     * node the request's interface nests under `a` — unless a `#[TsCasts]` override replaced that
     * whole subtree, which only the interface honours. Null for an undeclared or prohibited path.
     *
     * Splitting on every `.` matches `data_get()`, which cannot reach an escaped-dot key (`'v1\.0'`)
     * either — null is that key's correct answer, not a shortfall.
     *
     * @param  class-string<FormRequest>  $fqcn
     */
    public function analyzeField(string $fqcn, string $dottedPath): ?FormRequestRuleNode
    {
        $this->isDynamic = false;

        $rawRules = $this->resolveRules($fqcn);

        if ($rawRules === null) {
            $this->isDynamic = true;

            return null;
        }

        $node = $this->buildRuleTrie($rawRules);

        foreach (explode('.', $dottedPath) as $segment) {
            // composeObjectNode() drops a prohibited child outright, so nothing beneath one reaches
            // the composed type: a path through it names a key that can never exist.
            if ($node->own !== null && $node->own['isProhibited']) {
                return null;
            }

            if (! isset($node->children[$segment])) {
                return null;
            }

            $node = $node->children[$segment];
        }

        $composed = $this->composeTrieNode($node);

        return new FormRequestRuleNode(
            fieldPath: $dottedPath,
            tsType: $composed['tsType'],
            isRequired: $composed['isRequired'],
            isNullable: $composed['isNullable'],
            isProhibited: $composed['isProhibited'],
            jsDocMetadata: [
                ...$composed['jsDocMetadata'],
                ...$this->collectChildJsDoc($node->children, $dottedPath),
            ],
        );
    }

    /**
     * Instantiate the FormRequest and call `rules()`, or null when it needs HTTP context.
     *
     * @param  class-string<FormRequest>  $fqcn
     * @return array<string, mixed>|null
     */
    protected function resolveRules(string $fqcn): ?array
    {
        $wasAuthenticated = Auth::check();
        $previousUser = $wasAuthenticated ? Auth::user() : null;

        try {
            $fakeRequest = Request::create('/', 'POST');

            /** @var FormRequest $formRequest */
            $formRequest = $fqcn::createFrom($fakeRequest);
            $formRequest->setContainer(app());

            $this->stubAuthUser();

            /** @var array<string, mixed> $rules */
            /** @phpstan-ignore method.notFound */
            $rules = $formRequest->rules();

            return $rules;
        } catch (Throwable) {
            return null;
        } finally {
            if ($wasAuthenticated && $previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetUser();
            }
        }
    }

    /**
     * Stub an authenticated user so `Auth::user()->anyMethod()` inside `rules()` returns false instead of throwing.
     */
    private function stubAuthUser(): void
    {
        if (Auth::check()) {
            return;
        }

        $stub = new class(['id' => null]) extends GenericUser
        {
            /** @param array<int, mixed> $arguments */
            public function __call(string $name, array $arguments): mixed
            {
                return false;
            }
        };

        Auth::setUser($stub);
    }

    /**
     * Normalize a raw rules array into a list of `FormRequestRuleNode` objects.
     *
     * Builds a dot-path trie and collapses it bottom-up, so `parent.*.child`/`parent.child` rules
     * compose into their nearest undotted ancestor instead of surviving as separate flat keys.
     *
     * @param  array<string, mixed>  $rawRules
     * @return list<FormRequestRuleNode>
     */
    protected function normalizeRules(array $rawRules): array
    {
        $trie = $this->buildRuleTrie($rawRules);

        $nodes = [];

        foreach ($trie->children as $fieldPath => $childNode) {
            $composed = $this->composeTrieNode($childNode);

            $nodes[] = new FormRequestRuleNode(
                fieldPath: (string) $fieldPath,
                tsType: $composed['tsType'],
                isRequired: $composed['isRequired'],
                isNullable: $composed['isNullable'],
                isProhibited: $composed['isProhibited'],
                jsDocMetadata: [
                    ...$composed['jsDocMetadata'],
                    ...$this->collectChildJsDoc($childNode->children, (string) $fieldPath),
                ],
            );
        }

        return $nodes;
    }

    /**
     * Build a dot-path trie from raw rules: a `*` segment marks "array of this node", any
     * other segment nests an object key. A node's own rule data lives at the exact path it
     * was declared on; intermediate ancestors created only to reach a deeper path have none.
     *
     * @param  array<string, mixed>  $rawRules
     */
    protected function buildRuleTrie(array $rawRules): FormRequestRuleTrieNode
    {
        $root = new FormRequestRuleTrieNode;

        foreach ($rawRules as $fieldPath => $ruleDefinition) {
            /** @var string $fieldPath */
            $leaf = $this->buildLeafData($this->parseFieldRules($ruleDefinition));

            $node = $root;

            $escaped = str_replace('\\.', self::DOT_PLACEHOLDER, $fieldPath);

            foreach (explode('.', $escaped) as $rawSegment) {
                $segment = str_replace(self::DOT_PLACEHOLDER, '.', $rawSegment);

                if (! isset($node->children[$segment])) {
                    $node->children[$segment] = new FormRequestRuleTrieNode;
                }

                $node = $node->children[$segment];
            }

            $node->own = $leaf;
        }

        return $root;
    }

    /**
     * Build the composable leaf data for a single field's parsed rules.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $parsedRules
     * @return RuleLeafData
     */
    protected function buildLeafData(array $parsedRules): array
    {
        return [
            'tsType' => $this->resolveTsType($parsedRules),
            'isRequired' => $this->isRequired($parsedRules) && ! $this->isSometimes($parsedRules),
            'isNullable' => $this->isNullable($parsedRules),
            'isProhibited' => $this->isProhibited($parsedRules),
            'jsDocMetadata' => $this->resolveJsDocMetadata($parsedRules),
            'syntheticArrayKeys' => $this->resolveSyntheticArrayKeys($parsedRules),
        ];
    }

    /**
     * Collapse a trie node bottom-up into a composed leaf: object (named children), array (`*` child), or its own leaf.
     *
     * A node with both its own rule and children uses the children — more specific than the own rule's placeholder.
     * Synthesized key-list children merge in rather than replace, with a real declared child winning a name collision.
     *
     * @return RuleLeafData
     */
    protected function composeTrieNode(FormRequestRuleTrieNode $node): array
    {
        $children = $node->children;
        $own = $node->own;

        if ($own !== null && $own['syntheticArrayKeys'] !== []) {
            $children += $this->syntheticArrayKeyChildren($own['syntheticArrayKeys']);
        }

        if ($children === []) {
            return $own ?? $this->emptyLeaf();
        }

        if ($this->allKeysAreNumeric($children)) {
            return $this->composeIndexedNode($children, $own);
        }

        if (array_key_exists('*', $children)) {
            $wildcard = $children['*'];
            unset($children['*']);

            return $children === []
                ? $this->composeArrayNode($wildcard, $own)
                : $this->composeMixedNode($children, $wildcard, $own);
        }

        return $this->composeObjectNode($children, $own);
    }

    /**
     * Compose an array-typed node: the `*` child's composed type suffixed `[]`. Required, nullable,
     * prohibited, and JSDoc describe the array itself; the element's own nullability folds into the
     * element type, so a nullable element reads `(string | null)[]`.
     *
     * @param  RuleLeafData|null  $own
     * @return RuleLeafData
     */
    protected function composeArrayNode(FormRequestRuleTrieNode $wildcardChild, ?array $own): array
    {
        $element = $this->composeTrieNode($wildcardChild);
        $elementType = $element['tsType'].($element['isNullable'] ? ' | null' : '');
        $tsType = $element['isProhibited'] ? 'never[]' : $this->arrayWrapType($elementType);

        return [
            'tsType' => $tsType,
            'isRequired' => $own !== null && $own['isRequired'],
            'isNullable' => $own !== null && $own['isNullable'],
            'isProhibited' => $own !== null && $own['isProhibited'],
            'jsDocMetadata' => $own !== null ? $own['jsDocMetadata'] : [],
            'syntheticArrayKeys' => [],
        ];
    }

    /**
     * Compose a node carrying both a `*` child and named children — Laravel's way of describing a map
     * whose values share a rule and whose some keys are pinned. Emitted as the named object shape
     * intersected with an index signature, which is always valid TS even when the two types differ.
     *
     * @param  array<string, FormRequestRuleTrieNode>  $children  the named children, `*` already removed
     * @param  RuleLeafData|null  $own
     * @return RuleLeafData
     */
    protected function composeMixedNode(array $children, FormRequestRuleTrieNode $wildcardChild, ?array $own): array
    {
        $object = $this->composeObjectNode($children, $own);
        $element = $this->composeTrieNode($wildcardChild);
        $elementType = $element['isProhibited']
            ? 'never'
            : $element['tsType'].($element['isNullable'] ? ' | null' : '');

        return [
            ...$object,
            'tsType' => $object['tsType'].' & Record<string, '.$elementType.'>',
        ];
    }

    /**
     * Whether every child key is an explicit numeric index (`items.0.name`), which describes a list
     * rather than an object — `{ "0": T }` is a type no real JSON array is assignable to.
     *
     * @param  array<string, FormRequestRuleTrieNode>  $children
     */
    protected function allKeysAreNumeric(array $children): bool
    {
        if ($children === []) {
            return false;
        }

        foreach (array_keys($children) as $key) {
            if (preg_match('/^\d+$/', (string) $key) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compose numerically-indexed children into an array of the union of their shapes, so
     * `items.0.name` reads `{ name: string }[]` instead of an unusable `{ "0": ... }` object.
     *
     * @param  array<string, FormRequestRuleTrieNode>  $children
     * @param  RuleLeafData|null  $own
     * @return RuleLeafData
     */
    protected function composeIndexedNode(array $children, ?array $own): array
    {
        $elementTypes = [];

        foreach ($children as $childNode) {
            $element = $this->composeTrieNode($childNode);

            if ($element['isProhibited']) {
                continue;
            }

            $elementTypes[] = $element['tsType'].($element['isNullable'] ? ' | null' : '');
        }

        $elementTypes = array_values(array_unique($elementTypes));

        return [
            'tsType' => $elementTypes === [] ? 'never[]' : $this->arrayWrapType(implode(' | ', $elementTypes)),
            'isRequired' => $own !== null && $own['isRequired'],
            'isNullable' => $own !== null && $own['isNullable'],
            'isProhibited' => $own !== null && $own['isProhibited'],
            'jsDocMetadata' => $own !== null ? $own['jsDocMetadata'] : [],
            'syntheticArrayKeys' => [],
        ];
    }

    /**
     * Compose an object-typed node from its named children into an inline `{ k: T; k2?: T2 }` type.
     * A prohibited child is dropped entirely — it can never legally appear in the payload — and a node
     * whose children are all prohibited becomes `Record<string, never>`, i.e. "no keys allowed".
     *
     * @param  array<string, FormRequestRuleTrieNode>  $children
     * @param  RuleLeafData|null  $own
     * @return RuleLeafData
     */
    protected function composeObjectNode(array $children, ?array $own): array
    {
        $parts = [];

        foreach ($children as $key => $childNode) {
            $child = $this->composeTrieNode($childNode);

            if ($child['isProhibited']) {
                continue;
            }

            $childType = $child['tsType'].($child['isNullable'] ? ' | null' : '');
            $optional = $child['isRequired'] ? '' : '?';

            $parts[] = JsEmitter::validJsObjectKey((string) $key).$optional.': '.$childType;
        }

        return [
            'tsType' => $parts === [] ? 'Record<string, never>' : '{ '.implode('; ', $parts).' }',
            'isRequired' => $own !== null && $own['isRequired'],
            'isNullable' => $own !== null && $own['isNullable'],
            'isProhibited' => $own !== null && $own['isProhibited'],
            'jsDocMetadata' => $own !== null ? $own['jsDocMetadata'] : [],
            'syntheticArrayKeys' => [],
        ];
    }

    /**
     * Collect descendants' JSDoc metadata, each suffixed with the full rule key it was declared on,
     * so `@format uuid` on `order.id` still reaches the reader as `@format uuid order.id` on `order`.
     *
     * @param  array<string, FormRequestRuleTrieNode>  $children
     * @return list<string>
     */
    protected function collectChildJsDoc(array $children, string $prefix): array
    {
        $collected = [];

        foreach ($children as $key => $child) {
            $path = $prefix.'.'.$key;

            if ($child->own !== null && $child->own['isProhibited']) {
                continue;
            }

            if ($child->own !== null) {
                foreach ($child->own['jsDocMetadata'] as $entry) {
                    $collected[] = $entry.' '.$path;
                }
            }

            $collected = [...$collected, ...$this->collectChildJsDoc($child->children, $path)];
        }

        return $collected;
    }

    /**
     * Synthesize pseudo-children for a leaf array's `required_array_keys`/`in_array_keys`/`array:`/
     * `array_keys:` keys, so they compose into a typed object instead of staying `unknown[]`. Each
     * key's own required-ness came from `resolveSyntheticArrayKeys()`; a real declared child with
     * the same name wins the merge.
     *
     * @param  array<string, bool>  $keys  key name => whether the declaring rule requires it
     * @return array<string, FormRequestRuleTrieNode>
     */
    protected function syntheticArrayKeyChildren(array $keys): array
    {
        $children = [];

        foreach ($keys as $key => $isRequired) {
            $node = new FormRequestRuleTrieNode;
            $node->own = $this->emptyLeaf(isRequired: $isRequired);
            $children[$key] = $node;
        }

        return $children;
    }

    /**
     * The default leaf for a trie node reached with no own rule and no children — unreachable
     * in practice, since every node exists only because it is on the path to a declared rule.
     *
     * @return RuleLeafData
     */
    protected function emptyLeaf(bool $isRequired = false): array
    {
        return [
            'tsType' => 'unknown',
            'isRequired' => $isRequired,
            'isNullable' => false,
            'isProhibited' => false,
            'jsDocMetadata' => [],
            'syntheticArrayKeys' => [],
        ];
    }

    /**
     * Parse a rule definition into a normalized list of rule arrays.
     *
     * @return list<array{0: mixed, 1: list<mixed>}>
     */
    protected function parseFieldRules(mixed $ruleDefinition): array
    {
        if (is_string($ruleDefinition)) {
            $ruleDefinition = explode('|', $ruleDefinition);
        }

        if (! is_array($ruleDefinition)) {
            $ruleDefinition = [$ruleDefinition];
        }

        $parsed = [];

        foreach ($ruleDefinition as $rule) {
            if (is_string($rule)) {
                [$name, $params] = ValidationRuleParser::parse($rule);
                $parsed[] = [$name, is_array($params) ? array_values($params) : []];
            } elseif ($rule instanceof Enum) {
                $parsed[] = [$rule, []];
            } elseif ($rule instanceof In) {
                $parsed[] = [$rule, []];
            } elseif ($rule instanceof File) {
                $parsed[] = [$rule, []];
            } elseif ($rule instanceof AnyOf) {
                $parsed[] = [$rule, []];
            } elseif (is_object($rule)) {
                $parsed[] = [$rule, []];
            }
        }

        return $parsed;
    }

    /**
     * Resolve the TypeScript type string for a set of parsed rules.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function resolveTsType(array $rules): string
    {
        // Passes run most-specific first; `File` also catches `ImageFile`, which extends it.
        foreach ($rules as [$rule]) {
            if ($rule instanceof File) {
                return 'File';
            }
        }

        foreach ($rules as [$rule]) {
            if ($rule instanceof AnyOf) {
                return $this->resolveAnyOfType($rule);
            }
        }

        foreach ($rules as [$rule]) {
            if ($rule instanceof Enum) {
                return $this->resolveEnumType($rule);
            }
        }

        foreach ($rules as [$rule, $params]) {
            if ($rule instanceof In) {
                return $this->resolveInType($rule);
            }

            // String-form `in:a,b,c` gets the same position-independent priority as the In object,
            // so an earlier `string`/`integer` rule cannot shadow the literal union.
            if (is_string($rule) && strtolower($rule) === 'in' && $params !== []) {
                return $this->resolveInFromParams($params, $rules);
            }
        }

        foreach ($rules as [$rule]) {
            if ($rule instanceof StringRule || $rule instanceof Email || $rule instanceof Date || $rule instanceof Password) {
                return 'string';
            }

            if ($rule instanceof Numeric) {
                return 'number';
            }

            if ($rule instanceof Dimensions) {
                return 'File';
            }

            if ($rule instanceof ArrayRule || $rule instanceof Contains || $rule instanceof DoesntContain) {
                return 'unknown[]';
            }

            // Illuminate\Validation\Rules\ArrayKeys only exists from Laravel 13.24; guard before instanceof.
            $arrayKeysClass = 'Illuminate\Validation\Rules\ArrayKeys';

            if (class_exists($arrayKeysClass) && $rule instanceof $arrayKeysClass) {
                return 'unknown[]';
            }

            if ($rule instanceof NotIn) {
                return 'string';
            }
        }

        foreach ($rules as [$rule, $params]) {
            if (! is_string($rule)) {
                continue;
            }

            // ValidationRuleParser::parse() returns PascalCase names (alpha_dash → AlphaDash); undo that here.
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            $type = match (true) {
                in_array($ruleLower, [
                    'string', 'alpha', 'alpha_dash', 'alpha_num', 'ascii', 'current_password',
                    'hex_color', 'json', 'date', 'date_equals', 'date_format',
                    'email', 'url', 'active_url', 'uuid', 'ulid', 'ip', 'ipv4', 'ipv6',
                    'mac_address', 'regex', 'not_regex',
                ], true) => 'string',
                in_array($ruleLower, self::NUMERIC_TYPE_RULES, true) => 'number',
                in_array($ruleLower, ['boolean', 'accepted', 'accepted_if', 'declined', 'declined_if'], true) => 'boolean',
                in_array($ruleLower, ['file', 'image', 'mimes', 'mimetypes', 'extensions'], true) => 'File',
                $ruleLower === 'array' => 'unknown[]',
                $ruleLower === 'list' => 'unknown[]',
                $ruleLower === 'array_keys' => 'unknown[]',
                $ruleLower === 'in' => $this->resolveInFromParams($params, $rules),
                default => null,
            };

            if ($type !== null) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Resolve the TypeScript union type from an `In` rule object.
     */
    protected function resolveInType(In $rule): string
    {
        try {
            /** @var array<int, mixed> $values */
            $values = (new ReflectionClass($rule))->getProperty('values')->getValue($rule);
        } catch (ReflectionException) {
            return 'string';
        }

        $literals = array_map(
            fn (mixed $v): string => JsEmitter::toJsLiteral($v),
            array_filter($values, fn (mixed $v): bool => $v !== null && $v !== ''),
        );

        return $literals !== [] ? implode(' | ', $literals) : 'string';
    }

    /**
     * Resolve the TypeScript union type from `in:a,b,c` params.
     *
     * ValidationRuleParser::parse() always parses string-form params as strings, so a numeric-looking
     * value needs an explicit signal to emit unquoted — a sibling rule from `NUMERIC_TYPE_RULES` on the
     * same field is that signal, matching what `Rule::in([1, 2, 3])` already emits for the same values.
     * A param only coerces when `+0` round-trips losslessly back to the same text: `validateIn()`
     * compares `(string) $value` against the literal param, so a padded/reformatted value like `'007'`
     * or `'2.50'` must stay a quoted string — emitting `7`/`2.5` would describe a value Laravel itself
     * rejects for that field.
     *
     * @param  list<mixed>  $params
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function resolveInFromParams(array $params, array $rules = []): string
    {
        $numeric = $this->hasNumericTypeSibling($rules);

        $literals = array_map(
            fn (mixed $v): string => JsEmitter::toJsLiteral(
                $numeric && is_string($v) && is_numeric($v) && $v === (string) ($v + 0) ? $v + 0 : $v,
            ),
            array_filter($params, fn (mixed $v): bool => $v !== null && $v !== ''),
        );

        return $literals !== [] ? implode(' | ', $literals) : 'string';
    }

    /**
     * Whether a field's sibling rules declare it numeric (see `NUMERIC_TYPE_RULES`).
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function hasNumericTypeSibling(array $rules): bool
    {
        foreach ($rules as [$rule]) {
            if (! is_string($rule)) {
                continue;
            }

            // ValidationRuleParser::parse() returns PascalCase names (alpha_dash → AlphaDash); undo that.
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            if (in_array($ruleLower, self::NUMERIC_TYPE_RULES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the TypeScript union type from an `AnyOf` rule object.
     */
    protected function resolveAnyOfType(AnyOf $rule): string
    {
        $reflection = new ReflectionClass($rule);

        /** @var array<int, mixed> $innerRuleSets */
        $innerRuleSets = $reflection->getProperty('rules')->getValue($rule);

        $types = [];

        foreach ($innerRuleSets as $ruleSet) {
            $parsed = $this->parseFieldRules($ruleSet);
            $type = $this->resolveTsType($parsed);

            if ($type !== 'unknown') {
                $types[] = $type;
            }
        }

        $uniqueTypes = array_unique($types);

        return $uniqueTypes !== [] ? implode(' | ', $uniqueTypes) : 'unknown';
    }

    /**
     * Resolve the TypeScript union type from an `Enum` rule object.
     */
    protected function resolveEnumType(Enum $rule): string
    {
        $reflection = new ReflectionClass($rule);

        /** @var class-string $enumClass */
        $enumClass = $reflection->getProperty('type')->getValue($rule);

        $enumReflection = new ReflectionClass($enumClass);

        if (! $enumReflection->isEnum() || ! $enumReflection->implementsInterface(BackedEnum::class)) {
            return 'string';
        }

        $cases = $enumReflection->getMethod('cases')->invoke(null);

        /** @var BackedEnum[] $cases */

        /** @var UnitEnum[] $only */
        $only = $reflection->getProperty('only')->getValue($rule);
        /** @var UnitEnum[] $except */
        $except = $reflection->getProperty('except')->getValue($rule);

        if ($only !== []) {
            $cases = array_values(array_filter(
                $cases,
                fn (BackedEnum $case): bool => in_array($case, $only, true),
            ));
        } elseif ($except !== []) {
            $cases = array_values(array_filter(
                $cases,
                fn (BackedEnum $case): bool => ! in_array($case, $except, true),
            ));
        }

        $values = array_map(
            fn (BackedEnum $case): string => JsEmitter::toJsLiteral($case),
            $cases,
        );

        return $values !== [] ? implode(' | ', $values) : 'string';
    }

    /**
     * Determine whether the field is required based on parsed rules.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function isRequired(array $rules): bool
    {
        foreach ($rules as [$rule]) {
            if ($rule instanceof RequiredIf || $rule instanceof RequiredUnless) {
                return true;
            }

            if (! is_string($rule)) {
                continue;
            }

            if (str_starts_with(strtolower($rule), 'required')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the field is nullable based on parsed rules.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function isNullable(array $rules): bool
    {
        foreach ($rules as [$rule]) {
            if (is_string($rule) && strtolower($rule) === 'nullable') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the field is prohibited.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function isProhibited(array $rules): bool
    {
        foreach ($rules as [$rule]) {
            if (is_string($rule) && in_array(strtolower($rule), ['missing', 'prohibited'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the field uses the `sometimes` rule.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     */
    protected function isSometimes(array $rules): bool
    {
        foreach ($rules as [$rule]) {
            if (is_string($rule) && strtolower($rule) === 'sometimes') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the keys declared by `required_array_keys:a,b`, `in_array_keys:a,b`, `array:a,b`,
     * or `array_keys:a,b`, mapped to whether Laravel's validator guarantees that key's presence.
     * `required_array_keys` demands every listed key; the other three never guarantee a single
     * key, so theirs come back optional.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     * @return array<string, bool> key name => whether the declaring rule requires it
     */
    protected function resolveSyntheticArrayKeys(array $rules): array
    {
        $requiredKeys = [];
        $optionalKeys = [];

        foreach ($rules as [$rule, $params]) {
            if (! is_string($rule)) {
                continue;
            }

            // ValidationRuleParser::parse() returns PascalCase names (required_array_keys → RequiredArrayKeys).
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            $keys = array_values(array_filter($params, 'is_string'));

            if ($ruleLower === 'required_array_keys') {
                $requiredKeys = [...$requiredKeys, ...$keys];
            } elseif (in_array($ruleLower, ['in_array_keys', 'array', 'array_keys'], true)) {
                $optionalKeys = [...$optionalKeys, ...$keys];
            }
        }

        return array_fill_keys($requiredKeys, true) + array_fill_keys($optionalKeys, false);
    }

    /**
     * Resolve JSDoc metadata annotations for a field.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     * @return list<string>
     */
    protected function resolveJsDocMetadata(array $rules): array
    {
        $metadata = [];

        foreach ($rules as [$rule]) {
            if ($rule instanceof RequiredIf || $rule instanceof RequiredUnless) {
                $metadata[] = '@metadata required-if conditional';
            }

            if ($rule instanceof ProhibitedIf || $rule instanceof ProhibitedUnless) {
                $metadata[] = '@metadata prohibited-if conditional';
            }

            if ($rule instanceof ExcludeIf || $rule instanceof ExcludeUnless) {
                $metadata[] = '@metadata exclude-if conditional';
            }

            if ($rule instanceof Exists) {
                $metadata[] = '@constraint exists';
            }

            if ($rule instanceof Unique) {
                $metadata[] = '@constraint unique';
            }
        }

        foreach ($rules as [$rule, $params]) {
            if (! is_string($rule)) {
                continue;
            }

            // ValidationRuleParser::parse() returns PascalCase names (required_with_all → RequiredWithAll).
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            if (in_array($ruleLower, ['email', 'url', 'active_url'], true)) {
                $metadata[] = "@format {$ruleLower}";
            } elseif (in_array($ruleLower, ['uuid', 'ulid', 'ip', 'ipv4', 'ipv6', 'mac_address', 'hex_color'], true)) {
                $metadata[] = "@format {$ruleLower}";
            } elseif (in_array($ruleLower, ['date', 'date_equals'], true)) {
                $metadata[] = '@format date';
            } elseif (in_array($ruleLower, ['exists', 'unique'], true)) {
                $metadata[] = "@constraint {$ruleLower}";
            } elseif (in_array($ruleLower, ['required_if', 'required_unless', 'required_with', 'required_without', 'required_with_all', 'required_without_all'], true)) {
                $metadata[] = '@metadata required-conditionally';
            } elseif ($ruleLower === 'not_in') {
                $notValues = implode(', ', array_filter($params, 'is_string'));
                $metadata[] = "@not {$notValues}";
            }
        }

        return $metadata;
    }

    /**
     * Suffix a type with `[]`, parenthesizing when a top-level `|` or `&` is present: TypeScript binds
     * `[]` tighter than both, so `A | B[]` parses as `A | (B[])`. Depth- and quote-aware — a separator
     * inside a `{ ... }` shape or a `'literal'` belongs to that member, not to the outer type.
     */
    private function arrayWrapType(string $type): string
    {
        return $this->hasTopLevelSeparator($type) ? '('.$type.')[]' : $type.'[]';
    }

    /**
     * Whether a `|` or `&` appears outside every `{}`, `<>`, `()` and `[]` nesting level, ignoring
     * quoted literals whose contents are opaque.
     */
    private function hasTopLevelSeparator(string $type): bool
    {
        $depth = 0;
        $length = strlen($type);
        $inQuote = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $type[$i];

            if ($char === "'") {
                $inQuote = ! $inQuote;
            } elseif ($inQuote) {
                continue;
            } elseif ($char === '{' || $char === '<' || $char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === '>' || $char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
            } elseif (($char === '|' || $char === '&') && $depth === 0) {
                return true;
            }
        }

        return false;
    }
}
