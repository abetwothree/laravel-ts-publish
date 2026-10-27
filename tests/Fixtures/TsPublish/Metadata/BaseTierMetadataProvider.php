<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * A test-only provider that reads its `TIER` through `static::`, directly and through an untyped `$tier` its own
 * method writes; a subclass redeclares it.
 */
class BaseTierMetadataProvider implements ModelMetadataProvider
{
    public const TIER = 'a';

    protected $tier = 'x';

    /**
     * Provide the tier, once it is raised to the constant of the provider's class, and that constant.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        $this->raise();

        return ['tier' => $this->tier, 'constant' => static::TIER];
    }

    /** Raise the tier to the constant the object's class declares. */
    protected function raise(): void
    {
        $this->tier = static::TIER;
    }
}
