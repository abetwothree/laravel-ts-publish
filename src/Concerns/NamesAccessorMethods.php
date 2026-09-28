<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use Illuminate\Support\Str;

/**
 * The one place that spells the method names Eloquent resolves an accessor through.
 */
trait NamesAccessorMethods
{
    /**
     * The new-style `Attribute`-returning method name and the old-style `get{Name}Attribute()` name.
     *
     * @return array{newStyle: string, oldStyle: string}
     */
    protected function accessorMethodNames(string $attributeName): array
    {
        return [
            'newStyle' => Str::camel($attributeName),
            'oldStyle' => 'get'.Str::studly($attributeName).'Attribute',
        ];
    }
}
