<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;

/** A LaravelTsPublish that shows which names its type-alias cache holds an answer for. */
class AliasCacheLaravelTsPublish extends LaravelTsPublish
{
    /**
     * The names the alias cache holds an answer for, alias or not, for one class.
     *
     * @param  class-string  $class
     * @return list<string>
     */
    public function cachedAliasNames(string $class): array
    {
        return array_map(strval(...), array_keys($this->phpstanTypeAliasCache[$class] ?? []));
    }
}
