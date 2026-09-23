<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * The PHPDoc forms an inline `@var` on a local may bind: scalars, classes the file names, unions, `list<X>`,
 * `array<int|string, X>`, identifier-keyed `array{…}` and the two collections, less spellings the docblock resolution
 * misreads: `?` before a generic or shape, a generic's union of non-bare members, a class bare and contained.
 *
 * @phpstan-type Part array{end: int, classes: list<string>, bare: bool}
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

        return $tokens !== null && ($this->type($tokens, 0, false)['end'] ?? null) === count($tokens);
    }

    /**
     * Split a type into tokens, or null when it holds any other character. A shape's `array{` and each `key:` or
     * `key?:` are single tokens, since the shape reader only reads them written together.
     *
     * @return list<string>|null
     */
    private function tokens(string $type): ?array
    {
        $name = '\\\\?[a-z_\x80-\xff][\w\x80-\xff]*(?:\\\\[a-z_\x80-\xff][\w\x80-\xff]*)*';
        preg_match_all('/\s*([a-z_]\w*\??:|array\{|'.$name.'|[|?<>},])\s*/i', $type, $matches);

        return implode('', $matches[0]) === $type ? $matches[1] : null;
    }

    /**
     * A type starting at a token: `?T`, or a union of forms. A union may not name a class both bare and inside another
     * member, which the union merge drops, and in a generic's value slot it takes only bare members.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function type(array $tokens, int $at, bool $slot): ?array
    {
        // The resolution appends `| null` to a `?` only before a bare form whose own reading holds no `null`.
        if (($tokens[$at] ?? null) === '?') {
            $form = $this->form($tokens, $at + 1);

            return $form !== null && $form['bare'] && ! $this->readsNull($form['classes']) ? $form : null;
        }

        $members = [];

        do {
            $member = $this->form($tokens, $at);

            if ($member === null) {
                return null;
            }

            $members[] = $member;
            $at = $member['end'] + 1;
        } while (($tokens[$member['end']] ?? null) === '|');

        if (count($members) === 1) {
            return $members[0];
        }

        $bare = array_filter($members, fn (array $part): bool => $part['bare']);
        $bareClasses = array_merge([], ...array_column($bare, 'classes'));
        $containedClasses = array_merge([], ...array_column(array_diff_key($members, $bare), 'classes'));

        return ($slot && count($bare) < count($members)) || array_intersect($bareClasses, $containedClasses) !== []
            ? null
            : ['end' => $member['end'], 'classes' => [...$bareClasses, ...$containedClasses], 'bare' => false];
    }

    /**
     * One form starting at a token: a bare scalar or class, or a list, a keyed array, a shape or a collection.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function form(array $tokens, int $at): ?array
    {
        $name = $tokens[$at] ?? '';
        $next = $tokens[$at + 1] ?? null;

        return match (true) {
            in_array($name, self::SCALARS, true) => ['end' => $at + 1, 'classes' => [], 'bare' => true],
            $name === 'list' && $next === '<' => $this->closed($tokens, $this->type($tokens, $at + 2, true)),
            $name === 'array' && $next === '<' => $this->keyed($tokens, $at + 2),
            $name === 'array{' => $this->members($tokens, $at + 1, []),
            $next === '<' => in_array($this->className($name), self::COLLECTIONS, true) ? $this->keyed($tokens, $at + 2) : null,
            default => $this->bareClass($name, $at),
        };
    }

    /**
     * A generic's `int` or `string` key, its value type and the closing `>`.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function keyed(array $tokens, int $at): ?array
    {
        return in_array($tokens[$at] ?? null, self::KEYS, true) && ($tokens[$at + 1] ?? null) === ','
            ? $this->closed($tokens, $this->type($tokens, $at + 2, true))
            : null;
    }

    /**
     * A shape's members, each a `key:` or `key?:` token and a type, up to the closing `}`.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $classes  the classes the members before this one name
     * @return Part|null
     */
    private function members(array $tokens, int $at, array $classes): ?array
    {
        $member = preg_match('/^[a-z_]\w*\??:$/i', $tokens[$at] ?? '') === 1 ? $this->type($tokens, $at + 1, false) : null;

        if ($member === null) {
            return null;
        }

        $classes = [...$classes, ...$member['classes']];
        $after = $tokens[$member['end']] ?? null;

        return match (true) {
            $after === '}' => ['end' => $member['end'] + 1, 'classes' => $classes, 'bare' => false],
            $after === ',' && ($tokens[$member['end'] + 1] ?? null) === '}' => ['end' => $member['end'] + 2, 'classes' => $classes, 'bare' => false],
            $after === ',' => $this->members($tokens, $member['end'] + 1, $classes),
            default => null,
        };
    }

    /**
     * A generic's value type followed by its closing `>`, or null when the type failed or no `>` follows.
     *
     * @param  list<string>  $tokens
     * @param  Part|null  $value
     * @return Part|null
     */
    private function closed(array $tokens, ?array $value): ?array
    {
        return $value !== null && ($tokens[$value['end']] ?? null) === '>'
            ? ['end' => $value['end'] + 1, 'classes' => $value['classes'], 'bare' => false]
            : null;
    }

    /**
     * A bare class name at a token, or null when it names no class.
     *
     * @return Part|null
     */
    private function bareClass(string $name, int $at): ?array
    {
        $class = $this->className($name);

        return $class === null ? null : ['end' => $at + 1, 'classes' => [$class], 'bare' => true];
    }

    /**
     * Whether the docblock resolution's reading of any of these classes holds `null`, such as a nullable property's.
     *
     * @param  list<string>  $classes
     */
    private function readsNull(array $classes): bool
    {
        return array_any($classes, fn (string $class): bool => str_contains(LaravelTsPublish::toTsType($class)['type'], 'null'));
    }

    /**
     * The class, interface or enum a name resolves to through the file's imports or namespace, else null.
     *
     * An unimported name the docblock resolution falls back to the global namespace for is not the file's class.
     */
    private function className(string $name): ?string
    {
        if (preg_match('/^\\\\?[a-z_\x80-\xff][\w\x80-\xff\\\\]*$/i', $name) !== 1) {
            return null;
        }

        $resolved = LaravelTsPublish::resolveDocblockTypeName($name, $this->useMap, $this->namespace);
        $throughFile = $resolved !== $name || $this->namespace === '' || isset($this->useMap[Str::before($name, '\\')]);

        return $throughFile && (class_exists($resolved) || interface_exists($resolved)) ? $resolved : null;
    }
}
