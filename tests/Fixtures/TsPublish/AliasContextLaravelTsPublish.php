<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use ReflectionClass;

/** A LaravelTsPublish that reads a docblock's return type while a class's type aliases are in reach. */
class AliasContextLaravelTsPublish extends LaravelTsPublish
{
    /**
     * The return type a docblock spells, read inside the class's alias context.
     *
     * @param  ReflectionClass<object>  $context
     */
    public function extractReturnTypeWithin(ReflectionClass $context, string $docComment): ?string
    {
        return $this->withAliasContext($context, fn (): ?string => $this->extractReturnTypeFromDocblock($docComment));
    }
}
