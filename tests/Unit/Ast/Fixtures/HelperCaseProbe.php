<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Enums\Status;

/**
 * Members read off the enum cases a helper returns: helpers whose declaration names no class, reached through `$this->`,
 * `static::`, `self::` and `parent::`, beside one returning a property and one declaring the enum. Not final, so Pint
 * keeps `static::` as written.
 */
class HelperCaseProbe extends HelperCaseProbeBase
{
    public bool $c = false;

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
