<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Closure;
use DateTimeImmutable;
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
     * Clears the pin, then reads the pin's label with no setter: Eloquent runs this on a write of `pin_label`, to look
     * for a setter.
     *
     * @return Attribute<string, never>
     */
    protected function pinLabel(): Attribute
    {
        $this->attributes['is_pinned'] = false;

        return Attribute::get(fn (): string => 'label');
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
     * Reads the pin's title through a getter a helper that clears the pin builds, which Eloquent's look-up runs.
     *
     * @return Attribute<string, never>
     */
    protected function pinTitle(): Attribute
    {
        return Attribute::get($this->clearingGetter());
    }

    /**
     * Reads the pin's code, cached by a call handed a helper's result, which Eloquent's look-up runs.
     *
     * @return Attribute<string, never>
     */
    protected function pinCode(): Attribute
    {
        return Attribute::get(fn (): string => 'code')->shouldCache($this->clearPin());
    }

    /**
     * Reads the pin's tone through a first-class callable of what a helper that clears the pin returns.
     *
     * @return Attribute<string, never>
     */
    protected function pinTone(): Attribute
    {
        return Attribute::get($this->clearingFormatter()->format(...));
    }

    /**
     * Reads the pin's mode, cached through a method a helper that clears the pin names, which Eloquent's look-up runs.
     *
     * @return Attribute<string, never>
     */
    protected function pinMode(): Attribute
    {
        return Attribute::get(fn (): string => 'mode')->{$this->clearAndName()}();
    }

    /**
     * Reads the pin's shade through a static arrow function, with no setter.
     *
     * @return Attribute<string, never>
     */
    protected function pinShade(): Attribute
    {
        return Attribute::get(static fn (): string => 'shade');
    }

    /**
     * Reads the pin's hue through a first-class callable of its own method, with no setter.
     *
     * @return Attribute<string, never>
     */
    protected function pinHue(): Attribute
    {
        return Attribute::get($this->hueOf(...));
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
     * Clears the pin, then hands back a getter.
     *
     * @return Closure(): string
     */
    private function clearingGetter(): Closure
    {
        $this->attributes['is_pinned'] = false;

        return fn (): string => 'title';
    }

    /**
     * Clears the pin.
     */
    private function clearPin(): bool
    {
        $this->attributes['is_pinned'] = false;

        return true;
    }

    /**
     * Clears the pin, then names the method that caches an attribute.
     */
    private function clearAndName(): string
    {
        $this->attributes['is_pinned'] = false;

        return 'shouldCache';
    }

    /**
     * Clears the pin, then hands back a formatter.
     */
    private function clearingFormatter(): DateTimeImmutable
    {
        $this->attributes['is_pinned'] = false;

        return new DateTimeImmutable;
    }

    /**
     * Names the hue.
     */
    private function hueOf(): string
    {
        return 'hue';
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
