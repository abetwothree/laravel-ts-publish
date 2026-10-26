<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` reads untyped levels and `static::LEVEL` through helpers it reaches with a
 * private `$this->`, with `self::`, and with the calls that already reach the runtime class.
 */
class BaseHelperLevelResource extends JsonResource
{
    public const LEVEL = 'base';

    protected $plevel = 'x';

    private $qlevel = 'x';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'via_private' => $this->privateHelper(),
            'q_private' => $this->qPrivate(),
            'via_self' => self::publicHelper(),
            'const_private' => $this->privateConst(),
            'const_self' => self::publicConst(),
            'shaped_private' => $this->privateShape(),
            'via_protected' => $this->protectedHelper(),
            'via_static' => static::publicHelper(),
            'via_this_static' => $this::publicHelper(),
        ];
    }

    /** Raise both levels to the constant the runtime class declares. */
    public function raise(): static
    {
        $this->plevel = static::LEVEL;
        $this->qlevel = static::LEVEL;

        return $this;
    }

    /** The protected level, read in a public body. */
    public function publicHelper()
    {
        return $this->plevel;
    }

    /** The runtime class's level constant, read in a public body. */
    public function publicConst()
    {
        return static::LEVEL;
    }

    /** The protected level, read in a protected body. */
    protected function protectedHelper()
    {
        return $this->plevel;
    }

    /** The protected level, read in a private body. */
    private function privateHelper()
    {
        return $this->plevel;
    }

    /** The private level, read in a private body. */
    private function qPrivate()
    {
        return $this->qlevel;
    }

    /** The runtime class's level constant, read in a private body. */
    private function privateConst()
    {
        return static::LEVEL;
    }

    /** @return array<string, mixed> */
    private function privateShape(): array
    {
        return ['l' => $this->plevel];
    }
}
