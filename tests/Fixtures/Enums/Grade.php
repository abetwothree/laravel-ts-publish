<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

/**
 * A second enum named `Grade`, beside the workbench's, which the test-only `Grade` model casts alongside it.
 */
enum Grade: string
{
    case Pass = 'pass';
    case Fail = 'fail';
}
