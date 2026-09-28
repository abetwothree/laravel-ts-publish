<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/**
 * A model-less subject whose properties carry only untyped literal defaults, so the default-literal
 * rule is the only thing that can type them.
 */
final class UntypedDefaultsSubject
{
    protected $extensions = ['png', 'jpg'];

    protected $limit = 10;

    protected $mixed = ['a', 1];
}
