<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

// An alias, so Pint keeps a call through the class's own name, which `self::` would turn into a forwarding call.
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseHelperLevelResource as BaseLevelByName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` reads untyped levels and `static::LEVEL` through helpers it reaches with a
 * private `$this->`, with `self::`, with its own name, through a `static` it returns, and with the calls that already
 * reach the runtime class.
 */
class BaseHelperLevelResource extends JsonResource
{
    public const LEVEL = 'base';

    protected $plevel = 'x';

    private $qlevel = 'x';

    /** The level constant of the class named, as a static call through a name never forwards the runtime class. */
    public static function staticConst()
    {
        return static::LEVEL;
    }

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
            'via_named' => BaseLevelByName::publicHelper(),
            'const_named' => BaseLevelByName::publicConst(),
            'static_named' => BaseLevelByName::staticConst(),
            'me_const' => $this->me()->publicConst(),
            'me_helper' => $this->me()->publicHelper(),
            'me_private_const' => $this->me()->privateConstVia(),
        ];
    }

    /** @return array<string, mixed> the same three bodies read through a receiver first, then through `$this->` */
    public function receiverFirst(): array
    {
        return [
            'held' => $this->held()->publicHelper(),
            'own' => $this->publicHelper(),
            'held_shape' => $this->held()->publicShape(),
            'own_shape' => $this->publicShape(),
            'held_macro' => $this->held()->viaMacro(),
            'own_macro' => $this->viaMacro(),
        ];
    }

    /** @return array<string, mixed> the same three bodies read through `$this->` first, then through a receiver */
    public function thisFirst(): array
    {
        return [
            'own' => $this->publicHelper(),
            'held' => $this->held()->publicHelper(),
            'own_shape' => $this->publicShape(),
            'held_shape' => $this->held()->publicShape(),
            'own_macro' => $this->viaMacro(),
            'held_macro' => $this->held()->viaMacro(),
        ];
    }

    /** @return array<string, mixed> the response of the class `static` names, spread */
    public function spreadOwn(): array
    {
        return [...static::make($this->resource)->resolve()];
    }

    /** @return array<string, mixed> a sibling's response, spread */
    public function spreadSibling(): array
    {
        return [...TrueHelperLevelResource::make($this->resource)->resolve()];
    }

    /** @return array<string, mixed> a sibling's method through its name, which PHP runs only on that sibling */
    public function siblingConst(): array
    {
        return ['sibling_const' => TrueHelperLevelResource::publicConst()];
    }

    /** Another instance, declared as this class, so its own subclass may be what it holds. */
    public function held(): self
    {
        return new self($this->resource);
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

    /** The runtime class's level constant, read through a private helper. */
    public function privateConstVia()
    {
        return $this->privateConst();
    }

    /** @return array<string, mixed> */
    public function publicShape(): array
    {
        return ['l' => $this->plevel];
    }

    /** The protected level, read by the `plevelMacro` macro ResourceMacroServiceProvider registers. */
    public function viaMacro()
    {
        return $this->plevelMacro();
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

    /** The object itself, declared as the class `static` names. */
    private function me(): static
    {
        return $this;
    }
}
