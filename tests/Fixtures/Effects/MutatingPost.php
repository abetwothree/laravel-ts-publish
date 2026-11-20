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
    /** Set from anywhere, so a write reaches the property and runs no mutator. */
    public $pin_open;

    /** Unset by the constructor, so a write reaches `__set()`. */
    public $pin_memo;

    /** Set only by the model's family, so a write from anywhere else throws. */
    public protected(set) ?string $pin_slot = null;

    /** Set only by the model, so a write from anywhere else throws. */
    public private(set) ?string $pin_seal = null;

    protected $table = 'posts';

    /** Hidden outside the model's family, so a write from anywhere else reaches `__set()`. */
    protected $pin_guard;

    /** Hidden outside the model's family, with an `Attribute` setter. */
    protected $pin_lock;

    /** Hidden outside the model. */
    private $pin_secret;

    /**
     * A post whose `pin_memo` reaches `__set()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        unset($this->pin_memo);
    }

    /**
     * Clears the pin as it stores the reset flag.
     */
    public function setPinResetAttribute(bool $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_reset'] = $value;
    }

    /**
     * Clears the pin as it stores the open note.
     */
    public function setPinOpenAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_open'] = $value;
    }

    /**
     * Clears the pin as it stores the memo.
     */
    public function setPinMemoAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_memo'] = $value;
    }

    /**
     * Clears the pin as it stores the slot.
     */
    public function setPinSlotAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_slot'] = $value;
    }

    /**
     * Clears the pin as it stores the seal.
     */
    public function setPinSealAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_seal'] = $value;
    }

    /**
     * Clears the pin as it stores the guard.
     */
    public function setPinGuardAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_guard'] = $value;
    }

    /**
     * Clears the pin as it stores the secret.
     */
    public function setPinSecretAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['pin_secret'] = $value;
    }

    /**
     * Clears the pin as it stores the attributes Eloquent's own protected `$visible` is named for.
     */
    public function setVisibleAttribute(string $value): void
    {
        $this->attributes['is_pinned'] = false;
        $this->attributes['visible'] = $value;
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
     * Sets the lock, and clears the pin with it.
     *
     * @return Attribute<never, string>
     */
    protected function pinLock(): Attribute
    {
        return Attribute::set(fn (string $value): array => ['is_pinned' => false, 'pin_lock' => $value]);
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
     * Reads the pin through a builder of an Attribute subclass that runs the getter, which clears the pin.
     *
     * @return Attribute<string, never>
     */
    protected function pinEager(): Attribute
    {
        return EagerAttribute::get(function (): string {
            $this->attributes['is_pinned'] = false;

            return 'eager';
        });
    }

    /**
     * Reads the pin through a built Attribute subclass that runs the getter, which clears the pin.
     *
     * @return Attribute<string, never>
     */
    protected function pinBuilt(): Attribute
    {
        return new EagerAttribute(get: function (): string {
            $this->attributes['is_pinned'] = false;

            return 'built';
        });
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
