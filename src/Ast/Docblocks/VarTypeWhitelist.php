<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * The PHPDoc forms an inline `@var` on a local may bind: scalars, classes the file names, aliases it sees, unions,
 * `?T`, `T[]`, `list<X>`, `array<K, X>` keyed by `int`, `string`, both, `array-key` or string literals, keyed
 * `array{…}` and `object{…}`, positional `list{…}` and `array{…}`, and the two collections, keyed or by element alone.
 *
 * A callable reads as `unknown`, so it is taken only as a shape's member, and counted.
 *
 * @phpstan-type Part array{end: int, unknowns: int}
 * @phpstan-type NextMember callable(list<string>, int, int): ?Part
 *
 * @internal
 */
final readonly class VarTypeWhitelist
{
    private const SCALARS = ['int', 'string', 'bool', 'float', 'null', 'true', 'false'];

    private const KEYS = ['int', 'string'];

    /** The key of either kind, which the docblock resolution reads as `array-key`. */
    private const EITHER_KEY = 'array-key';

    private const COLLECTIONS = [Collection::class, EloquentCollection::class];

    /** A shape member's key: an int, a dashed or plain name, or a quoted string, then an optional `?`, then `:`. */
    private const KEY = '(?:-?\d+|[a-z_][\w-]*|\'[^\'\\\\]*\'|"[^"\\\\]*")\s*\??\s*:(?!:)';

    /**
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve a name
     * @param  array<string, true>  $expanding  "{FQCN}::{alias}" aliases whose definitions are being read
     */
    private function __construct(private ReflectionClass $context, private array $expanding = []) {}

    /**
     * The whitelist for a type written in a class's file.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     */
    public static function for(ReflectionClass $context): self
    {
        return new self($context);
    }

    /**
     * Whether every part of a type is a supported form.
     */
    public function accepts(string $type): bool
    {
        return $this->unknownLeaves($type) !== null;
    }

    /**
     * How many leaves of a supported type the docblock resolution reads as `unknown` by design, one per callable; null
     * when the type holds a form outside the whitelist.
     */
    public function unknownLeaves(string $type): ?int
    {
        $tokens = $this->tokens($type) ?? [];
        $part = $tokens === [] ? null : $this->type($tokens, 0);

        return $part !== null && $part['end'] === count($tokens) ? $part['unknowns'] : null;
    }

    /**
     * Split a type into tokens, or null when it holds any other character. A shape's opener, each member's key with
     * its `?` and `:`, a quoted string, a callable's `callable(`, `array-key` and a list's `[]` are single tokens.
     *
     * @return list<string>|null
     */
    private function tokens(string $type): ?array
    {
        $name = '\\\\?[a-z_\x80-\xff][\w\x80-\xff]*(?:\\\\[a-z_\x80-\xff][\w\x80-\xff]*)*';
        $literal = '\'[^\'\\\\]*\'|"[^"\\\\]*"';
        preg_match_all(
            '/\s*('.self::KEY.'|(?:array|list|object)\{|callable\(|'.self::EITHER_KEY.'(?![\w-])|'.$literal.'|'.$name.'|\[\]|[|?<>},():])\s*/i',
            $type,
            $matches,
        );

        return implode('', $matches[0]) === $type ? $matches[1] : null;
    }

    /**
     * A type starting at a token: `?T`, or a union of forms.
     *
     * @param  list<string>  $tokens
     * @param  bool  $member  whether the type is a shape's member, the one place a callable may stand
     * @return Part|null
     */
    private function type(array $tokens, int $at, bool $member = false): ?array
    {
        // The resolution appends a top-level `| null` arm to a `?` form whose reading has none.
        if (($tokens[$at] ?? null) === '?') {
            return $this->form($tokens, $at + 1, $member);
        }

        $unknowns = 0;

        do {
            $part = $this->form($tokens, $at, $member);

            if ($part === null) {
                return null;
            }

            $unknowns += $part['unknowns'];
            $at = $part['end'] + 1;
        } while (($tokens[$part['end']] ?? null) === '|');

        return ['end' => $part['end'], 'unknowns' => $unknowns];
    }

    /**
     * One form starting at a token, listed by each `[]` that follows it.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function form(array $tokens, int $at, bool $member): ?array
    {
        $part = $this->baseForm($tokens, $at, $member);

        while ($part !== null && ($tokens[$part['end']] ?? null) === '[]') {
            $part['end']++;
        }

        return $part;
    }

    /**
     * One form starting at a token: a bare scalar, class or alias, a list, a keyed array, a shape, a collection, or a
     * shape member's callable.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function baseForm(array $tokens, int $at, bool $member): ?array
    {
        $name = $tokens[$at] ?? '';
        $next = $tokens[$at + 1] ?? null;

        return match (true) {
            in_array($name, self::SCALARS, true) => ['end' => $at + 1, 'unknowns' => 0],
            $name === 'list' && $next === '<' => $this->closed($tokens, $this->type($tokens, $at + 2)),
            $name === 'array' && $next === '<' => $this->keyed($tokens, $at + 2),
            $name === 'array{' => $this->members($tokens, $at + 1, 0) ?? $this->positional($tokens, $at + 1, 0),
            $name === 'object{' => $this->members($tokens, $at + 1, 0),
            $name === 'list{' => $this->positional($tokens, $at + 1, 0),
            $name === 'callable(' => $member ? $this->callable($tokens, $at + 1) : null,
            $next === '<' => in_array($this->className($name), self::COLLECTIONS, true)
                ? $this->keyed($tokens, $at + 2) ?? $this->closed($tokens, $this->type($tokens, $at + 2))
                : null,
            default => $this->bareClass($name, $at),
        };
    }

    /**
     * A generic's key, its value type and the closing `>`: `int`, `string`, both as a union, `array-key`, or a union of
     * string literals.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function keyed(array $tokens, int $at): ?array
    {
        $key = $tokens[$at] ?? null;

        if ($this->isKeyUnion($tokens, $at)) {
            $at += 2;
        } elseif ($key !== self::EITHER_KEY && ! in_array($key, self::KEYS, true)) {
            // A union of string literals reads as an object of optional keys; a decimal int one is an int key.
            while ($this->isTextLiteral($tokens[$at] ?? '') && ($tokens[$at + 1] ?? null) === '|') {
                $at += 2;
            }

            if (! $this->isTextLiteral($tokens[$at] ?? '')) {
                return null;
            }
        }

        return ($tokens[$at + 1] ?? null) === ','
            ? $this->closed($tokens, $this->type($tokens, $at + 2))
            : null;
    }

    /**
     * A shape's members, each a key token and a type, up to the closing `}`.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function members(array $tokens, int $at, int $unknowns): ?array
    {
        $member = $this->isKey($tokens[$at] ?? '') ? $this->type($tokens, $at + 1, true) : null;

        return $member === null ? null : $this->next($tokens, $member, $unknowns, $this->members(...));
    }

    /**
     * A list shape's members, each a type with no key, up to the closing `}`.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function positional(array $tokens, int $at, int $unknowns): ?array
    {
        $member = $this->isKey($tokens[$at] ?? '') ? null : $this->type($tokens, $at, true);

        return $member === null ? null : $this->next($tokens, $member, $unknowns, $this->positional(...));
    }

    /**
     * After a shape's member: the shape's end at `}`, or the next member after a `,`.
     *
     * @param  list<string>  $tokens
     * @param  Part  $member
     * @param  NextMember  $continue
     * @return Part|null
     */
    private function next(array $tokens, array $member, int $unknowns, callable $continue): ?array
    {
        $unknowns += $member['unknowns'];
        $end = $member['end'];
        $after = $tokens[$end] ?? null;

        return match (true) {
            $after === '}' => ['end' => $end + 1, 'unknowns' => $unknowns],
            $after === ',' && ($tokens[$end + 1] ?? null) === '}' => ['end' => $end + 2, 'unknowns' => $unknowns],
            $after === ',' => $continue($tokens, $end + 1, $unknowns),
            default => null,
        };
    }

    /**
     * A callable's parameter types, its `)` and an optional `: return` type; the resolution reads it as `unknown`.
     *
     * @param  list<string>  $tokens
     * @return Part|null
     */
    private function callable(array $tokens, int $at): ?array
    {
        while (($tokens[$at] ?? null) !== ')') {
            $parameter = $this->type($tokens, $at);
            $after = $parameter === null ? null : $tokens[$parameter['end']] ?? null;

            if ($parameter === null || ($after !== ',' && $after !== ')')) {
                return null;
            }

            $at = $after === ',' ? $parameter['end'] + 1 : $parameter['end'];
        }

        if (($tokens[$at + 1] ?? null) !== ':') {
            return ['end' => $at + 1, 'unknowns' => 1];
        }

        $return = $this->form($tokens, $at + 2, false);

        return $return === null ? null : ['end' => $return['end'], 'unknowns' => 1];
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
            ? ['end' => $value['end'] + 1, 'unknowns' => $value['unknowns']]
            : null;
    }

    /**
     * A bare class name at a token, or an alias the file's class sees where it names no class; else null.
     *
     * @return Part|null
     */
    private function bareClass(string $name, int $at): ?array
    {
        if ($this->className($name) !== null) {
            return ['end' => $at + 1, 'unknowns' => 0];
        }

        $unknowns = TypeAliases::isName($name) ? $this->aliasUnknownLeaves($name) : null;

        return $unknowns === null ? null : ['end' => $at + 1, 'unknowns' => $unknowns];
    }

    /**
     * The unknown leaves of an alias's definition as the file that wrote it would bind it spelled directly; null when
     * the class sees no such alias, the definition holds a form outside the whitelist, or it names itself.
     */
    private function aliasUnknownLeaves(string $name): ?int
    {
        $key = $this->context->getName().'::'.$name;
        $alias = isset($this->expanding[$key]) ? null : TypeAliases::find($name, $this->context);

        return $alias === null
            ? null
            : new self(new ReflectionClass($alias['class']), [...$this->expanding, $key => true])->unknownLeaves($alias['definition']);
    }

    /**
     * Whether the tokens at a position spell a key of either kind as a union, `int|string` or `string|int`.
     *
     * @param  list<string>  $tokens
     */
    private function isKeyUnion(array $tokens, int $at): bool
    {
        $first = $tokens[$at] ?? null;
        $second = $tokens[$at + 2] ?? null;

        return in_array($first, self::KEYS, true)
            && ($tokens[$at + 1] ?? null) === '|'
            && in_array($second, self::KEYS, true)
            && $second !== $first;
    }

    /**
     * Whether a token is a shape member's key, with its `:`.
     */
    private function isKey(string $token): bool
    {
        return preg_match('/^'.self::KEY.'$/i', $token) === 1;
    }

    /**
     * Whether a token is a quoted string literal whose text is no decimal int, which PHP would cast to an int key.
     */
    private function isTextLiteral(string $token): bool
    {
        return preg_match('/^([\'"])(.*)\1$/s', $token, $quoted) === 1 && PhpArrayKey::storedInt($quoted[2]) === null;
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

        $resolved = DocblockNames::resolve($name, $this->context);
        $throughFile = $resolved !== $name
            || $this->context->getNamespaceName() === ''
            || isset(LaravelTsPublish::parseFileUseStatements($this->context)[Str::before($name, '\\')]);

        return $throughFile && DocblockNames::isValueClass($resolved) ? $resolved : null;
    }
}
