<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Closure;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/**
 * A post whose attributes Eloquent sets through mutators of each kind, beside ones only read through an accessor.
 */
class MutatingPost extends Post
{
    protected $table = 'posts';

    /**
     * Clears the pin as it stores the reset flag.
     */
    public function setPinResetAttribute(bool $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_reset'] = $value;
    }

    /**
     * Sets the pin's note, and clears the pin with it.
     *
     * @return Attribute<never, string>
     */
    protected function pinNote(): Attribute
    {
        return Attribute::set(fn (string $value): array => ['is_pinned' => false, 'pin_note' => $value]);
    }

    /**
     * Sets the pin's mark through a setter unpacked by name, and clears the pin with it.
     *
     * @return Attribute<never, string>
     */
    protected function pinMark(): Attribute
    {
        return Attribute::make(...['set' => fn (string $value): array => ['is_pinned' => false, 'pin_mark' => $value]]);
    }

    /**
     * Sets the pin's tag through the accessors a helper returns.
     *
     * @return Attribute<string, string>
     */
    protected function pinTag(): Attribute
    {
        return Attribute::make(...$this->tagAccessors());
    }

    /**
     * Sets the badge through a builder the source names no setter in.
     *
     * @return Attribute<string, string>
     */
    protected function badge(): Attribute
    {
        return $this->stamped();
    }

    /**
     * Reads the label, cached, with no setter.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::make(get: fn (): string => 'label')->shouldCache();
    }

    /**
     * Reads the summary, its setter left out by name.
     *
     * @return Attribute<string, never>
     */
    protected function summary(): Attribute
    {
        return new Attribute(get: fn (): string => 'summary', set: null);
    }

    /**
     * Reads the caption through a closure that returns it, with no setter.
     *
     * @return Attribute<string, never>
     */
    protected function caption(): Attribute
    {
        return Attribute::get(function (): string {
            return 'caption';
        });
    }

    /**
     * A getter and a setter that clears the pin, by the names Attribute::make() takes.
     *
     * @return array{get: Closure(string): string, set: Closure(string): array<string, mixed>}
     */
    private function tagAccessors(): array
    {
        return [
            'get' => fn (string $value): string => $value,
            'set' => fn (string $value): array => ['is_pinned' => false, 'pin_tag' => $value],
        ];
    }

    /**
     * An attribute holding what it is given.
     *
     * @return Attribute<string, string>
     */
    private function stamped(): Attribute
    {
        return Attribute::make(get: fn (string $value): string => $value, set: fn (string $value): string => $value);
    }
}
