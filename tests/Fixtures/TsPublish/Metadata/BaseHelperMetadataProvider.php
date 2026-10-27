<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * A test-only provider whose provide() a subclass inherits: `$this->` and `static::` reach that subclass's helpers,
 * while `self::` stays on this class.
 */
abstract class BaseHelperMetadataProvider implements ModelMetadataProvider
{
    /** The helper `self::` reads here, whichever class runs provide(). */
    public static function own(): int|string
    {
        return 1;
    }

    /**
     * Provide what each helper returns.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'helper' => $this->helper(),
            'doc' => $this->doc(),
            'own' => self::own(),
            'late' => static::own(),
        ];
    }

    /** The helper a subclass narrows natively. */
    public function helper(): int|string
    {
        return 1;
    }

    /**
     * The helper a subclass narrows by docblock only.
     *
     * @return int
     */
    public function doc()
    {
        return 1;
    }
}
