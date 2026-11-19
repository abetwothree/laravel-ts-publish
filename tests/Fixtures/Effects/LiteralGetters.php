<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Enums\Status;

/**
 * Getters a model may override an Eloquent method with: some only return a literal, a constant or a property, and some
 * run code or write first.
 */
class LiteralGetters
{
    public const string KEY = 'id';

    public string $hooked {
        get => $this->keyName;
    }

    protected string $keyName = 'uuid';

    /** @var list<string> */
    protected array $names = [];

    /** @var list<string> */
    protected static array $registry = [];

    /**
     * Reads a property the class lacks.
     */
    public function __get(string $name): mixed
    {
        return null;
    }

    /**
     * A string.
     */
    public function string(): string
    {
        return 'slug';
    }

    /**
     * A number.
     */
    public function number(): float
    {
        return 1.5;
    }

    /**
     * A global constant.
     */
    public function constant(): bool
    {
        return false;
    }

    /**
     * A class constant.
     */
    public function classConstant(): string
    {
        return static::KEY;
    }

    /**
     * An enum case.
     */
    public function enumCase(): Status
    {
        return Status::Published;
    }

    /**
     * A property the class declares.
     */
    public function property(): string
    {
        return $this->keyName;
    }

    /**
     * A list and a map of literals.
     *
     * @return array<int|string, string>
     */
    public function array(): array
    {
        return ['id', self::KEY, 'key' => $this->keyName];
    }

    /**
     * A string, with a comment after it.
     */
    public function commented(): string
    {
        return 'slug';
        // The key the route binds by.
    }

    /**
     * A local, set first.
     */
    public function local(): string
    {
        $key = 'slug';

        return $key;
    }

    /**
     * A call's result.
     */
    public function call(): string
    {
        return strtolower('SLUG');
    }

    /**
     * Two strings joined.
     */
    public function joined(): string
    {
        return 'post_'.'slug';
    }

    /**
     * A string holding a property.
     */
    public function interpolated(): string
    {
        return "post_{$this->keyName}";
    }

    /**
     * A property the class lacks, which PHP reads through `__get()`.
     */
    public function undeclared(): mixed
    {
        return $this->missing;
    }

    /**
     * A static property, which `$this->` reads through `__get()`.
     *
     * @return list<string>
     */
    public function staticProperty(): array
    {
        return $this->registry;
    }

    /**
     * A property whose get hook runs.
     */
    public function hookedProperty(): string
    {
        return $this->hooked;
    }

    /**
     * A member of a property.
     */
    public function propertyMember(): ?string
    {
        return $this->names[0] ?? null;
    }

    /**
     * An array holding a call's result.
     *
     * @return array<string, string>
     */
    public function arrayWithCall(): array
    {
        return ['key' => strtolower('SLUG')];
    }

    /**
     * An array spreading a property.
     *
     * @return list<string>
     */
    public function arrayWithSpread(): array
    {
        return [...$this->names];
    }

    /**
     * A property, set first where it is null.
     */
    public function coalesceAssigned(): string
    {
        return $this->keyName ??= 'uuid';
    }

    /**
     * A new object.
     */
    public function built(): self
    {
        return new self;
    }

    /**
     * An empty body.
     */
    public function nothing(): void {}
}
