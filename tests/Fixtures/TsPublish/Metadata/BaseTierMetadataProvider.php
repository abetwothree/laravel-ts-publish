<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/** A test-only provider whose untyped `$tier` its own method writes through `static::`, which a subclass redeclares. */
class BaseTierMetadataProvider implements ModelMetadataProvider
{
    public const TIER = 'a';

    protected $tier = 'x';

    /**
     * Provide the tier, once it is raised to the constant of the provider's class.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        $this->raise();

        return ['tier' => $this->tier];
    }

    /** Raise the tier to the constant the object's class declares. */
    protected function raise(): void
    {
        $this->tier = static::TIER;
    }
}
