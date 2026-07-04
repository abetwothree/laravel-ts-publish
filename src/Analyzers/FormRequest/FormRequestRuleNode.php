<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest;

/**
 * A normalized representation of a single FormRequest rule field.
 *
 * Carries the TypeScript type string, field path, and presence/nullability flags
 * needed to render a TypeScript interface field.
 */
final readonly class FormRequestRuleNode
{
    /**
     * @param  string  $fieldPath  Dot-notation field path (e.g. `meta.description`, `tags.*`).
     * @param  string  $tsType  Resolved TypeScript type string (e.g. `string`, `number`, `'a' | 'b'`).
     * @param  bool  $isRequired  True when a `required*` rule is present.
     * @param  bool  $isNullable  True when a `nullable` rule is present.
     * @param  bool  $isProhibited  True when a `missing` or `prohibited` rule is present.
     * @param  list<string>  $jsDocMetadata  Additional JSDoc annotation lines (e.g. `@format email`).
     */
    public function __construct(
        public string $fieldPath,
        public string $tsType,
        public bool $isRequired,
        public bool $isNullable,
        public bool $isProhibited,
        public array $jsDocMetadata,
    ) {}
}
