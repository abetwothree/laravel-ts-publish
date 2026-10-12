<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Post;

/** A post whose static helpers differ in visibility and in whether the wrapped class also declares them. */
class ForwardedMethodPost extends Post
{
    protected $table = 'posts';

    public static function onModel(): string
    {
        return 'model';
    }

    public static function onBoth(): string
    {
        return 'model';
    }

    protected static function guarded(): string
    {
        return 'model';
    }
}
