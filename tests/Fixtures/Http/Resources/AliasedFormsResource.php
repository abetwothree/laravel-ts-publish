<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasCode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LocalAliasSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Aliases whose definitions a local `@var` would bind or decline spelled directly: an unimported global class, an
 * intersection, a pseudo-type, an integer range and an alias naming itself, beside shapes, lists and a callable. The
 * file never imports `DateTimeImmutable`, so PHPStan reads the name in this namespace.
 *
 * @phpstan-type When DateTimeImmutable
 * @phpstan-type Whens list<DateTimeImmutable>
 * @phpstan-type WhenShape array{at: DateTimeImmutable}
 * @phpstan-type Coded Post&HasCode
 * @phpstan-type Loose scalar
 * @phpstan-type Range int<0, max>
 * @phpstan-type Tree list<Tree>
 * @phpstan-type Pair array{a: int, b: string}
 * @phpstan-type Pairs list<Pair>
 * @phpstan-type Callback array{fn: callable(int): string}
 *
 * @phpstan-import-type Flag from LocalAliasSource as Duo
 *
 * @mixin Post
 */
final class AliasedFormsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var When $when */
        $when = $this->opaque(date_create_immutable('2020-01-02'));
        /** @var Pairs $pairs */
        $pairs = $this->opaque([['a' => 1, 'b' => 'x']]);

        return [
            'when' => $when,
            'when_year' => $when->format('Y'),
            'pairs' => $pairs,
            'helper_when' => $this->untypedWhen($this->opaque(date_create_immutable('2020-01-02'))),
        ];
    }

    /**
     * Wrap a date its untyped parameter's `@param` names by an alias of an unimported global class.
     *
     * @param  When  $when
     * @return array<string, mixed>
     */
    private function untypedWhen($when): array
    {
        return ['w' => $when];
    }

    /** A value no rule types: it round-trips through serialize(), which the engine does not read. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
