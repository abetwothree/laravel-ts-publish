<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationRuleParser;
use ReflectionClass;
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
        try {
            $fakeRequest = Request::create('/', 'POST');

            /** @var FormRequest $formRequest */
            $formRequest = $fqcn::createFrom($fakeRequest);
            $formRequest->setContainer(app());

            /** @var array<string, mixed> $rules */
            /** @phpstan-ignore method.notFound */
            $rules = $formRequest->rules();

            return $rules;
        } catch (Throwable) {
            return null;
        }
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
        /** @var array<int, mixed> $values */
        $values = (new ReflectionClass($rule))->getProperty('values')->getValue($rule);

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
