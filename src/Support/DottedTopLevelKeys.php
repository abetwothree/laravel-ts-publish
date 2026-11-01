<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;

/**
 * The object Inertia sends once `PropsResolver::unpackDotProps()` writes each top-level dotted key into its path with
 * `Arr::set()`, in key order: into an array it finds there, over any other value, a new key after the others.
 *
 * Each key it writes prints from pieces, so a class token is read with the classes of the key whose type it prints.
 *
 * @phpstan-type TypePiece = array{text: string, from: array-key|null}
 * @phpstan-type TopLevelEntry = array{type: string, optional: bool, pieces?: list<TypePiece>}
 * @phpstan-type Base = array{pieces: list<TypePiece>, text: string, optional: bool, direct: bool}
 * @phpstan-type Node = array{parent: string|null, segment: string, leaf: array-key|null, base: Base|null}
 * @phpstan-type Printed = array{pieces: list<TypePiece>, optional: bool}
 *
 * @internal
 */
final class DottedTopLevelKeys
{
    /** What an empty PHP array publishes as, which a write leaves holding only the keys it writes. */
    private const array EMPTY = ['[]', 'never[]', '{}', TsTypeString::EMPTY_OBJECT];

    /** @var array<string, Node> each node by its path, in the order Inertia's array holds them */
    private array $nodes = [];

    /**
     * @param  array<array-key, array{type: string, optional: bool}>  $props
     */
    private function __construct(private readonly array $props) {}

    /**
     * Write each dotted key into its path as Inertia unpacks it.
     *
     * @param  array<array-key, array{type: string, optional: bool}>  $props  each top-level key as Laravel sends it
     * @return array<array-key, TopLevelEntry> a key the writes reach carries the pieces of its type, each naming the
     *                                         input key whose type it prints, or null for the text between them
     */
    public static function nest(array $props): array
    {
        if (! array_any(array_keys($props), self::isDotted(...))) {
            return $props;
        }

        return new self($props)->nested();
    }

    /**
     * Whether Inertia unpacks a top-level key: a string holding a dot, which a generated index signature is not.
     */
    private static function isDotted(int|string $key): bool
    {
        return is_string($key) && str_contains($key, '.') && ! IndexSignatureKey::is($key);
    }

    /**
     * The path of a segment under its parent's, never int-like, so PHP keeps it a string key.
     */
    private static function id(?string $parent, string $segment): string
    {
        return ($parent ?? '')."\0".$segment;
    }

    /**
     * A member's key as written, without the `?` and quotes it prints with.
     */
    private static function memberName(string $printed): string
    {
        $name = str_ends_with($printed, '?') ? substr($printed, 0, -1) : $printed;

        return match (true) {
            str_starts_with($name, '"') => is_string($decoded = json_decode($name)) ? $decoded : $name,
            str_starts_with($name, "'") => substr($name, 1, -1),
            default => $name,
        };
    }

    /**
     * Pieces of one literal text between the typed ones.
     *
     * @return list<TypePiece>
     */
    private static function text(string $text): array
    {
        return [['text' => $text, 'from' => null]];
    }

    /**
     * Join the pieces of each part with literal text between them.
     *
     * @param  list<list<TypePiece>>  $parts
     * @return list<TypePiece>
     */
    private static function joined(array $parts, string $glue): array
    {
        $pieces = [];

        foreach ($parts as $at => $part) {
            array_push($pieces, ...($at === 0 ? [] : self::text($glue)), ...$part);
        }

        return $pieces;
    }

    /**
     * Each run of literal pieces merged into one.
     *
     * @param  list<TypePiece>  $pieces
     * @return list<TypePiece>
     */
    private static function merged(array $pieces): array
    {
        $merged = [];

        foreach ($pieces as $piece) {
            $last = array_key_last($merged);

            if ($last !== null && $piece['from'] === null && $merged[$last]['from'] === null) {
                $merged[$last]['text'] .= $piece['text'];
            } elseif ($piece['text'] !== '') {
                $merged[] = $piece;
            }
        }

        return $merged;
    }

    /**
     * Unpack every dotted key, then print each top-level key a write reached.
     *
     * @return array<array-key, TopLevelEntry>
     */
    private function nested(): array
    {
        foreach (array_keys($this->props) as $key) {
            if (! self::isDotted($key)) {
                $this->nodes[self::id(null, (string) $key)] = [
                    'parent' => null, 'segment' => (string) $key, 'leaf' => $key, 'base' => null,
                ];
            }
        }

        foreach (array_keys($this->props) as $key) {
            if (is_string($key) && self::isDotted($key)) {
                $this->write($key);
            }
        }

        $nested = [];

        foreach ($this->nodes as $id => $node) {
            if ($node['parent'] !== null) {
                continue;
            }

            if ($node['leaf'] !== null) {
                $nested[$node['segment']] = $this->props[$node['leaf']];

                continue;
            }

            $printed = $this->printed($id);
            $pieces = self::merged($printed['pieces']);
            $nested[$node['segment']] = [
                'type' => implode('', array_column($pieces, 'text')),
                'optional' => $printed['optional'],
                'pieces' => $pieces,
            ];
        }

        return $nested;
    }

    /**
     * Write one dotted key as `Arr::set()` does.
     *
     * Each segment before the last opens the value it finds, and the last takes the key's value over anything there.
     */
    private function write(string $dotted): void
    {
        $segments = explode('.', $dotted);
        $last = (string) array_pop($segments);
        $parent = null;

        foreach ($segments as $segment) {
            $id = self::id($parent, $segment);
            $node = $this->nodes[$id] ?? null;

            if ($node === null) {
                $this->nodes[$id] = ['parent' => $parent, 'segment' => $segment, 'leaf' => null, 'base' => null];
            } elseif ($node['leaf'] !== null) {
                $entry = $this->props[$node['leaf']];
                $this->nodes[$id] = [...$node, 'leaf' => null, 'base' => [
                    'pieces' => [['text' => $entry['type'], 'from' => $node['leaf']]],
                    'text' => $entry['type'],
                    'optional' => $entry['optional'],
                    'direct' => true,
                ]];
            }

            $parent = $id;
        }

        $id = self::id($parent, $last);

        foreach (array_keys($this->nodes) as $other) {
            if (str_starts_with($other, $id."\0")) {
                unset($this->nodes[$other]);
            }
        }

        $this->nodes[$id] = ['parent' => $parent, 'segment' => $last, 'leaf' => $dotted, 'base' => null];
    }

    /**
     * The paths of a node's children, in the order Inertia's array holds them.
     *
     * @return list<string>
     */
    private function children(string $id): array
    {
        return array_keys(array_filter($this->nodes, static fn (array $node): bool => $node['parent'] === $id));
    }

    /**
     * A node's type and whether Inertia may leave it out.
     *
     * A key's own, or an object of what the writes put there, over the value a segment found: its own or its parent's.
     *
     * @param  Base|null  $member  the member of the parent's value this node writes into
     * @return Printed
     */
    private function printed(string $id, ?array $member = null): array
    {
        $node = $this->nodes[$id];

        if ($node['leaf'] !== null) {
            $entry = $this->props[$node['leaf']];

            return ['pieces' => [['text' => $entry['type'], 'from' => $node['leaf']]], 'optional' => $entry['optional']];
        }

        $base = $node['base'] ?? $member;
        $children = $this->children($id);

        if ($base === null) {
            return $this->wrapped($this->members($children));
        }

        $arms = TsTypeShape::splitTopLevel($base['text'], ['|']);
        $solid = array_values(array_filter($arms, static fn (string $arm): bool => ! in_array($arm, ['null', 'undefined'], true)));
        $nullable = count($arms) > count($solid);
        $one = count($solid) === 1 ? $solid[0] : null;
        $object = $one === null ? null : TsTypeShape::members($one);
        $element = $one === null ? null : TsTypeShape::elementType($one);
        $written = $this->members($children);
        $absent = $written['optional'];

        $pieces = match (true) {
            in_array('unknown', $solid, true) => $absent
                ? $base['pieces']
                : [...self::text('Record<string, unknown> & '), ...$this->wrapped($written)['pieces']],
            array_all($solid, static fn (string $arm): bool => TsTypeShape::isScalar($arm) || in_array($arm, self::EMPTY, true)) => [
                ...$this->wrapped($written)['pieces'],
                ...($absent ? [...self::text(' | '), ...$base['pieces']] : []),
            ],
            $one !== null && $object !== null && $object !== []
                && ! array_any($object, static fn (array $entry): bool => str_starts_with($entry[0], '[')) => $this->intoObject(
                    $base, $one, $object, $children, $nullable,
                ),
            $element !== null => $this->intoList($base, $element, $written, $nullable, $absent),
            default => $this->besideOther($base, $written, $absent),
        };

        return ['pieces' => $pieces, 'optional' => $absent && $base['optional']];
    }

    /**
     * The members the children print, joined, and whether every one may be left out.
     *
     * @param  list<string>  $children
     * @param  array<array-key, Base>  $members  each child's member of the value it writes into, by its segment
     * @param  array<array-key, Base>  $kept  the member a child left out keeps, by its segment
     * @return Printed
     */
    private function members(array $children, array $members = [], array $kept = []): array
    {
        $parts = [];
        $absent = true;

        foreach ($children as $child) {
            $segment = $this->nodes[$child]['segment'];
            $printed = $this->printed($child, $members[$segment] ?? null);
            $optional = $printed['optional'];
            $pieces = $printed['pieces'];

            if ($optional && isset($kept[$segment])) {
                $pieces = [...$pieces, ...self::text(' | '), ...$kept[$segment]['pieces']];
                $optional = $kept[$segment]['optional'];
            }

            $parts[] = [...self::text(JsEmitter::validJsObjectKey($segment).($optional ? '?: ' : ': ')), ...$pieces];
            $absent = $absent && $optional;
        }

        return ['pieces' => self::joined($parts, '; '), 'optional' => $absent];
    }

    /**
     * Members wrapped as one object type.
     *
     * @param  Printed  $members
     * @return Printed
     */
    private function wrapped(array $members): array
    {
        return ['pieces' => [...self::text('{ '), ...$members['pieces'], ...self::text(' }')], 'optional' => $members['optional']];
    }

    /**
     * The writes into an object literal.
     *
     * Its text with each new key appended, else without each key a write replaces or enters, the entered one read from
     * it; a value that may be absent or null leaves only the writes.
     *
     * @param  Base  $base
     * @param  list<array{string, string}>  $object  the object literal's members, each as it prints
     * @param  list<string>  $children
     * @return list<TypePiece>
     */
    private function intoObject(array $base, string $arm, array $object, array $children, bool $nullable): array
    {
        $held = [];

        foreach ($object as [$printed, $type]) {
            $held[self::memberName($printed)] = ['text' => $type, 'optional' => str_ends_with($printed, '?')];
        }

        $from = $base['pieces'][0]['from'];
        $source = match (true) {
            $base['direct'] => [['text' => $arm, 'from' => $from]],
            $nullable => [...self::text('NonNullable<'), ...$base['pieces'], ...self::text('>')],
            default => $base['pieces'],
        };
        $members = [];
        $kept = [];

        foreach ($children as $child) {
            $segment = $this->nodes[$child]['segment'];

            if (! isset($held[$segment])) {
                continue;
            }

            $read = [
                'pieces' => [...$source, ...self::text('['.(string) json_encode($segment).']')],
                'text' => $held[$segment]['text'],
                'optional' => $held[$segment]['optional'],
                'direct' => false,
            ];

            if ($this->nodes[$child]['leaf'] === null && $this->nodes[$child]['base'] === null) {
                $members[$segment] = $read;
            } else {
                $kept[$segment] = $read;
            }
        }

        $entered = array_keys($members + $kept);
        $written = $this->wrapped($this->members($children, $members, $kept))['pieces'];

        $pieces = match (true) {
            $entered === [] && $base['direct'] => [
                ['text' => rtrim(substr($arm, 0, -1), " \t\n;,"), 'from' => $from],
                ...self::text('; '),
                ...$this->members($children)['pieces'],
                ...self::text(' }'),
            ],
            $entered === [] => [...$source, ...self::text(' & '), ...$written],
            default => [
                ...self::text('Omit<'),
                ...$source,
                ...self::text(', '.implode(' | ', array_map(
                    static fn (string $key): string => "'".addcslashes($key, "'\\")."'",
                    $entered,
                )).'> & '),
                ...$written,
            ],
        };

        return $nullable || $base['optional']
            ? [...$pieces, ...self::text(' | '), ...$this->wrapped($this->members($children))['pieces']]
            : $pieces;
    }

    /**
     * The writes into a list, which Inertia sends as an object of its numbered elements and each key written.
     *
     * A list that may be absent or null leaves only the writes, and every write left out leaves the list itself.
     *
     * @param  Base  $base
     * @param  Printed  $written
     * @return list<TypePiece>
     */
    private function intoList(array $base, string $element, array $written, bool $nullable, bool $absent): array
    {
        $object = $this->wrapped($written)['pieces'];

        return [
            ...self::text('Record<number, '),
            ...($base['direct']
                ? [['text' => $element, 'from' => $base['pieces'][0]['from']]]
                : [...self::text('NonNullable<'), ...$base['pieces'], ...self::text('>[number]')]),
            ...self::text('> & '),
            ...$object,
            ...($nullable || $base['optional'] ? [...self::text(' | '), ...$object] : []),
            ...($absent ? [...self::text(' | '), ...$base['pieces']] : []),
        ];
    }

    /**
     * The writes into a value that may or may not be an array, such as a model or a resource.
     *
     * Either joined to it or alone, and the value untouched where every write may be left out.
     *
     * @param  Base  $base
     * @param  Printed  $written
     * @return list<TypePiece>
     */
    private function besideOther(array $base, array $written, bool $absent): array
    {
        $bare = ! $base['direct']
            || (count(TsTypeShape::splitTopLevel($base['text'], ['|', '?'])) === 1 && ! str_contains($base['text'], '=>'));
        $object = $this->wrapped($written)['pieces'];

        return [
            ...self::text('('),
            ...($bare ? $base['pieces'] : [...self::text('('), ...$base['pieces'], ...self::text(')')]),
            ...self::text(' & '),
            ...$object,
            ...self::text(') | '),
            ...$object,
            ...($absent ? [...self::text(' | '), ...$base['pieces']] : []),
        ];
    }
}
