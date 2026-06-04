<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest;

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

/**
 * Analyzes a FormRequest class's `rules()` method and normalizes the result
 * into a structured tree for TypeScript interface generation.
 *
 * Instantiates the FormRequest with an empty HTTP context and calls `rules()`.
 * If `rules()` throws (e.g. when it accesses request state), falls back to a
 * dynamic placeholder that emits `Record<string, unknown>` in the generated output.
 */
class FormRequestRulesAnalyzer
{
    /**
     * Whether the rules could not be resolved statically.
     * When true, the generated interface will be `Record<string, unknown>`.
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
        $rules = $this->resolveRules($fqcn);

        if ($rules === null) {
            $this->isDynamic = true;

            return [];
        }

        return $this->normalizeRules($rules);
    }

    /**
     * Attempt to instantiate the FormRequest and call `rules()`.
     * Returns null when the rules cannot be resolved without HTTP context.
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

            // Stub a fake authenticated user so that rules() methods calling
            // Auth::user()->someMethod() don't throw on null. The stub returns
            // false for any unknown method call, so auth-gated branches always
            // resolve to the "unauthenticated" path.
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
     * Set a stub authenticated user so that `Auth::user()->anyMethod()` calls
     * inside `rules()` don't throw. Any unknown method returns `false`.
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
     * Handles dot-notation nested keys (e.g. `meta.description`) and wildcard
     * array element keys (e.g. `tags.*`).
     *
     * @param  array<string, mixed>  $rawRules
     * @return list<FormRequestRuleNode>
     */
    protected function normalizeRules(array $rawRules): array
    {
        // Pre-scan wildcard keys (e.g. `tags.*`) to build an element type lookup.
        // Used to upgrade parent array fields from `unknown[]` to `elementType[]`.
        /** @var array<string, string> $wildcardElementTypes */
        $wildcardElementTypes = [];

        foreach ($rawRules as $fieldPath => $ruleDefinition) {
            /** @var string $fieldPath */
            if (str_ends_with($fieldPath, '.*')) {
                $parentPath = substr($fieldPath, 0, -2);
                $parsedWildcard = $this->parseFieldRules($ruleDefinition);
                $wildcardType = $this->resolveTsType($parsedWildcard);

                if ($wildcardType !== 'unknown') {
                    $wildcardElementTypes[$parentPath] = $wildcardType;
                }
            }
        }

        /** @var array<string, FormRequestRuleNode> $nodes */
        $nodes = [];

        foreach ($rawRules as $fieldPath => $ruleDefinition) {
            /** @var string $fieldPath */
            $parsedRules = $this->parseFieldRules($ruleDefinition);

            $tsType = $this->resolveTsType($parsedRules);

            // Upgrade `unknown[]` to a typed array when a wildcard sibling defines the element type
            if ($tsType === 'unknown[]' && isset($wildcardElementTypes[$fieldPath])) {
                $tsType = $wildcardElementTypes[$fieldPath].'[]';
            }

            $isRequired = $this->isRequired($parsedRules);
            $isNullable = $this->isNullable($parsedRules);
            $isProhibited = $this->isProhibited($parsedRules);
            $isSometimes = $this->isSometimes($parsedRules);
            $jsDocMetadata = $this->resolveJsDocMetadata($parsedRules);

            $nodes[$fieldPath] = new FormRequestRuleNode(
                fieldPath: $fieldPath,
                tsType: $tsType,
                isRequired: $isRequired && ! $isSometimes,
                isNullable: $isNullable,
                isProhibited: $isProhibited,
                jsDocMetadata: $jsDocMetadata,
            );
        }

        return array_values($nodes);
    }

    /**
     * Parse a rule definition into a normalized list of rule arrays.
     *
     * @return list<array{0: mixed, 1: list<mixed>}>
     */
    protected function parseFieldRules(mixed $ruleDefinition): array
    {
        // Normalize to array form
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
                // Unknown object rule — treat as unresolvable
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
        // Check for File rule object (covers File and ImageFile since ImageFile extends File)
        foreach ($rules as [$rule]) {
            if ($rule instanceof File) {
                return 'File';
            }
        }

        // Check for AnyOf rule object — union inner rule sets
        foreach ($rules as [$rule]) {
            if ($rule instanceof AnyOf) {
                return $this->resolveAnyOfType($rule);
            }
        }

        // Check for enum rule first (most specific)
        foreach ($rules as [$rule]) {
            if ($rule instanceof Enum) {
                return $this->resolveEnumType($rule);
            }
        }

        // Check for `in:a,b,c` rule
        foreach ($rules as [$rule]) {
            if ($rule instanceof In) {
                return $this->resolveInType($rule);
            }

            if (is_string($rule) && strtolower($rule) === 'in') {
                // Handled via In object above; string form should already be parsed
            }
        }

        // Check for fluent Rule objects that determine the field's base TypeScript type
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

            if ($rule instanceof NotIn) {
                return 'string';
            }
        }

        // Check rule strings for scalar type
        foreach ($rules as [$rule, $params]) {
            if (! is_string($rule)) {
                continue;
            }

            // ValidationRuleParser::parse() normalizes rule names to PascalCase
            // (e.g. alpha_dash → AlphaDash). Convert back to lowercase snake_case.
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            $type = match (true) {
                in_array($ruleLower, [
                    'string', 'alpha', 'alpha_dash', 'alpha_num', 'ascii', 'current_password',
                    'hex_color', 'json', 'date', 'date_equals', 'date_format',
                    'email', 'url', 'active_url', 'uuid', 'ulid', 'ip', 'ipv4', 'ipv6',
                    'mac_address', 'regex', 'not_regex',
                ], true) => 'string',
                in_array($ruleLower, ['integer', 'int', 'numeric', 'decimal', 'digits', 'digits_between'], true) => 'number',
                in_array($ruleLower, ['boolean', 'accepted', 'accepted_if', 'declined', 'declined_if'], true) => 'boolean',
                in_array($ruleLower, ['file', 'image', 'mimes', 'mimetypes', 'extensions'], true) => 'File',
                $ruleLower === 'array' => 'unknown[]',
                $ruleLower === 'list' => 'unknown[]',
                $ruleLower === 'in' => $this->resolveInFromParams($params),
                default => null,
            };

            if ($type !== null) {
                return $type;
            }
        }

        // Default fallback
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
            fn (mixed $v): string => is_string($v) ? "'{$v}'" : (is_int($v) || is_float($v) ? (string) $v : ''),
            array_filter($values, fn (mixed $v): bool => $v !== null && $v !== ''),
        );

        return $literals !== [] ? implode(' | ', $literals) : 'string';
    }

    /**
     * Resolve the TypeScript union type from `in:a,b,c` params.
     *
     * @param  list<mixed>  $params
     */
    protected function resolveInFromParams(array $params): string
    {
        $literals = array_map(
            fn (mixed $v): string => is_string($v) ? "'{$v}'" : (is_int($v) || is_float($v) ? (string) $v : ''),
            array_filter($params, fn (mixed $v): bool => $v !== null && $v !== ''),
        );

        return $literals !== [] ? implode(' | ', $literals) : 'string';
    }

    /**
     * Resolve the TypeScript union type from an `AnyOf` rule object.
     *
     * Inspects each inner rule set and resolves its type.
     * Returns a union of all non-`unknown` resolved types, or `'unknown'`
     * when none of the inner sets can be resolved.
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

        if (! $enumReflection->isEnum() || ! $enumReflection->implementsInterface(\BackedEnum::class)) {
            return 'string';
        }

        $cases = $enumReflection->getMethod('cases')->invoke(null);

        /** @var \BackedEnum[] $cases */

        // Apply .only() and .except() filters if set on the Enum rule
        /** @var \UnitEnum[] $only */
        $only = $reflection->getProperty('only')->getValue($rule);
        /** @var \UnitEnum[] $except */
        $except = $reflection->getProperty('except')->getValue($rule);

        if ($only !== []) {
            $cases = array_values(array_filter(
                $cases,
                fn (\BackedEnum $case): bool => in_array($case, $only, true),
            ));
        } elseif ($except !== []) {
            $cases = array_values(array_filter(
                $cases,
                fn (\BackedEnum $case): bool => ! in_array($case, $except, true),
            ));
        }

        $values = array_map(
            fn (\BackedEnum $case): string => is_string($case->value) ? "'{$case->value}'" : (string) $case->value,
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
     * Resolve JSDoc metadata annotations for a field.
     *
     * @param  list<array{0: mixed, 1: list<mixed>}>  $rules
     * @return list<string>
     */
    protected function resolveJsDocMetadata(array $rules): array
    {
        $metadata = [];

        // Handle fluent Rule object annotations
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

            // ValidationRuleParser::parse() normalizes rule names to PascalCase
            // (e.g. required_with_all → RequiredWithAll). Convert back to snake_case.
            $pascalToSnake = preg_replace('/[A-Z]/', '_$0', lcfirst($rule));
            $ruleLower = strtolower(is_string($pascalToSnake) ? $pascalToSnake : $rule);

            if (in_array($ruleLower, ['email', 'url', 'active_url'], true)) {
                $metadata[] = "@format {$ruleLower}";
            } elseif (in_array($ruleLower, ['uuid', 'ulid', 'ip', 'ipv4', 'ipv6', 'mac_address', 'hex_color'], true)) {
                $metadata[] = "@format {$ruleLower}";
            } elseif (in_array($ruleLower, ['date', 'date_equals'], true)) {
                $metadata[] = '@format date-time';
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
}
