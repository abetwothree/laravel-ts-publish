<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\PicksHelperCases;
use LogicException;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Status as Level;

/**
 * Members read off the enum cases a helper returns: helpers whose declaration names no class, reached through `$this->`,
 * `$this::`, `static::`, `self::`, `parent::` and a trait, beside ones PHP may send no case from. Not final, so Pint
 * keeps `static::` as written.
 */
class HelperCaseProbe extends HelperCaseProbeBase
{
    use PicksHelperCases;

    public const FALLBACK = Level::Published;

    public bool $c = false;

    public int $n = 1;

    public Status $status = Status::Draft;

    /** A case, called through `static::` and `self::`. */
    public static function staticDraft(): mixed
    {
        return Status::Draft;
    }

    /**
     * Each member read off a helper's return.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mixed_value' => $this->mixedDraft()->value,
            'mixed_name' => $this->mixedDraft()->name,
            'untyped_value' => $this->draft()->value,
            'either_value' => $this->either()->value,
            'nullsafe_value' => $this->maybeDraft()?->value,
            'static_value' => static::staticDraft()->value,
            'self_value' => self::staticDraft()->value,
            'parent_value' => parent::published()->value,
            'this_static_value' => $this::published()->value,
            'trait_value' => $this->traitHigh()->value,
            'trait_name' => $this->traitHigh()->name,
            'trait_self_constant' => $this->traitFallback()->value,
            'private_parent' => $this->baseArray(),
            'matched_value' => $this->matched()->value,
            'matched_name' => $this->matched()->name,
            'constant_value' => $this->constant()->value,
            'falls_off_value' => $this->fallsOff()->value,
            'property_value' => $this->heldStatus()->value,
            'typed_value' => $this->typedDraft()->value,
        ];
    }

    /** A case behind a `mixed` declaration. */
    public function mixedDraft(): mixed
    {
        return Status::Draft;
    }

    /** A case behind no declaration. */
    public function draft()
    {
        return Status::Draft;
    }

    /** A ternary of cases. */
    public function either(): mixed
    {
        return $this->c ? Status::Draft : Status::Published;
    }

    /** A case, or null. */
    public function maybeDraft(): mixed
    {
        if ($this->c) {
            return Status::Draft;
        }

        return null;
    }

    /** A case its parent's helper does not return. */
    public function published(): mixed
    {
        return Status::Draft;
    }

    /** A case the parent's private `pick()` does not return. */
    public function pick(): mixed
    {
        return Status::Published;
    }

    /** A `match` of cases with a `throw` default. */
    public function matched(): mixed
    {
        return match ($this->n) {
            1 => Status::Draft,
            2 => Status::Published,
            default => throw new LogicException('No status'),
        };
    }

    /** A class constant holding a case. */
    public function constant(): mixed
    {
        return self::FALLBACK;
    }

    /** A case, or a `null` PHP sends by running off the end, which no return spells. */
    public function fallsOff()
    {
        if (! $this->c) {
            return Status::Draft;
        }
    }

    /** A property, which no case spells. */
    public function heldStatus(): mixed
    {
        return $this->status;
    }

    /** A case behind the enum's own declaration. */
    public function typedDraft(): Status
    {
        return Status::Draft;
    }
}
