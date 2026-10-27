<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NinthViewCounter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StartedViewCounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` reads a private untyped `$level` its own method writes through `static::`,
 * and a holder's untyped property the holder's subclass writes through `static::`, read directly and through the
 * holder's own methods.
 */
class BasePrivateLevelResource extends JsonResource
{
    public const LEVEL = 'base';

    private $level = 'x';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->level,
            'views' => $this->counter()->views,
            'counter_getviews' => $this->counter()->getViews(),
            'counter_startconst' => $this->counter()->startConst(),
        ];
    }

    /** @return array<string, mixed> the count a method macro ResourceMacroServiceProvider registers reads */
    public function macroViews(): array
    {
        return ['views' => $this->ninthViews()];
    }

    /** Raise the level to the constant the runtime class declares. */
    public function raise(): static
    {
        $this->level = static::LEVEL;

        return $this;
    }

    /** A counter declared as the base holder, though it is the subclass, bumped. */
    public function counter(): StartedViewCounter
    {
        $counter = new NinthViewCounter;
        $counter->bump();

        return $counter;
    }
}
