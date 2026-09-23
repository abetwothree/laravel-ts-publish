<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * The PHPDoc forms an inline `@var` on a local may bind, each read in full by the docblock resolution: scalars, classes
 * the file names, `?T`, unions, `list<X>`, `array<int|string, X>`, identifier-keyed `array{…}` and the two collections.
 * A tag with any other part binds nothing, rather than the guess the resolution makes of it.
 *
 * @internal
 */
final readonly class VarTypeWhitelist
{
    private const SCALARS = ['int', 'string', 'bool', 'float', 'null', 'true', 'false'];

    private const KEYS = ['int', 'string'];

    private const COLLECTIONS = [Collection::class, EloquentCollection::class];

    /**
     * @param  array<string, class-string>  $useMap
     */
    private function __construct(private array $useMap, private string $namespace) {}

    /**
     * The whitelist for a type written in a class's file.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     */
    public static function for(ReflectionClass $context): self
    {
        return new self(LaravelTsPublish::parseFileUseStatements($context), $context->getNamespaceName());
    }

    /**
     * Whether every part of a type is a supported form.
     */
    public function accepts(string $type): bool
    {
        $tokens = $this->tokens($type);

        return $tokens !== null && $this->type($tokens, 0) === count($tokens);
    }

    /**
     * Split a type into names and punctuation, or null when it holds any other character.
     *
     * @return list<string>|null
     */
    private function tokens(string $type): ?array
    {
        preg_match_all('/\s*(\\\\?[a-z_\x80-\xff][\w\x80-\xff]*(?:\\\\[a-z_\x80-\xff][\w\x80-\xff]*)*|[|?<>{},:])\s*/i', $type, $matches);

        return implode('', $matches[0]) === $type ? $matches[1] : null;
    }

    /**
     * Where a type starting at a token ends, `?T` or a union of forms, or null when it is not supported.
     *
     * @param  list<string>  $tokens
     */
    private function type(array $tokens, int $at): ?int
    {
        if (($tokens[$at] ?? null) === '?') {
            return $this->form($tokens, $at + 1);
        }

        $at = $this->form($tokens, $at);

        while ($at !== null && ($tokens[$at] ?? null) === '|') {
            $at = $this->form($tokens, $at + 1);
        }

        return $at;
    }

    /**
     * Where one form starting at a token ends: a scalar, a class, a list, a keyed array, a shape or a collection.
     *
     * @param  list<string>  $tokens
     */
    private function form(array $tokens, int $at): ?int
    {
        $name = $tokens[$at] ?? '';
        $next = $tokens[$at + 1] ?? null;

        return match (true) {
            in_array($name, self::SCALARS, true) => $at + 1,
            $name === 'list' && $next === '<' => $this->closed($tokens, $this->type($tokens, $at + 2), '>'),
            $name === 'array' && $next === '<' => $this->keyed($tokens, $at + 2),
            $name === 'array' && $next === '{' => $this->members($tokens, $at + 2),
            $next === '<' => in_array($this->className($name), self::COLLECTIONS, true) ? $this->keyed($tokens, $at + 2) : null,
            default => $this->className($name) === null ? null : $at + 1,
        };
    }

    /**
     * Where a generic's `int` or `string` key, its value type and the closing `>` end.
     *
     * @param  list<string>  $tokens
     */
    private function keyed(array $tokens, int $at): ?int
    {
        return in_array($tokens[$at] ?? null, self::KEYS, true) && ($tokens[$at + 1] ?? null) === ','
            ? $this->closed($tokens, $this->type($tokens, $at + 2), '>')
            : null;
    }

    /**
     * Where a shape's members, each an identifier key, an optional `?` and a type, end with the closing `}`.
     *
     * @param  list<string>  $tokens
     */
    private function members(array $tokens, int $at): ?int
    {
        if (preg_match('/^[a-z_]\w*$/i', $tokens[$at] ?? '') !== 1) {
            return null;
        }

        $at += ($tokens[$at + 1] ?? null) === '?' ? 2 : 1;
        $at = ($tokens[$at] ?? null) === ':' ? $this->type($tokens, $at + 1) : null;
        $after = $at === null ? null : $tokens[$at] ?? null;

        return match (true) {
            $at === null => null,
            $after === '}' => $at + 1,
            $after === ',' && ($tokens[$at + 1] ?? null) === '}' => $at + 2,
            $after === ',' => $this->members($tokens, $at + 1),
            default => null,
        };
    }

    /**
     * The position past a closing token that follows a parsed part, or null when the part failed or no closer follows.
     *
     * @param  list<string>  $tokens
     */
    private function closed(array $tokens, ?int $at, string $closer): ?int
    {
        return $at !== null && ($tokens[$at] ?? null) === $closer ? $at + 1 : null;
    }

    /**
     * The class, interface or enum a name resolves to through the file's imports or namespace, else null.
     *
     * An unimported name the docblock resolution falls back to the global namespace for is not the file's class.
     */
    private function className(string $name): ?string
    {
        if (preg_match('/^\\\\?[a-z_\x80-\xff]/i', $name) !== 1) {
            return null;
        }

        $resolved = LaravelTsPublish::resolveDocblockTypeName($name, $this->useMap, $this->namespace);
        $throughFile = $resolved !== $name || $this->namespace === '' || isset($this->useMap[Str::before($name, '\\')]);

        return $throughFile && (class_exists($resolved) || interface_exists($resolved)) ? $resolved : null;
    }
}
