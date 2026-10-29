<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

/** A test-only provider that narrows every helper the provide() it inherits calls. */
final class HelperOverrideMetadataProvider extends BaseHelperMetadataProvider
{
    /** The narrowed helper `static::` reaches, though `self::` does not. */
    public static function own(): string
    {
        return 'x';
    }

    /** The natively narrowed helper. */
    public function helper(): string
    {
        return 'x';
    }

    /**
     * The helper narrowed by docblock only.
     *
     * @return string
     */
    public function doc()
    {
        return 'x';
    }
}
