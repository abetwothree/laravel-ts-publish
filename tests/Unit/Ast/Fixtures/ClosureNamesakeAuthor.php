<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\User;

/** A related model whose methods share names with a Carbon date's and a value object's, with other returns. */
class ClosureNamesakeAuthor extends User
{
    protected $table = 'users';

    public static function label(): int
    {
        return 1;
    }

    public function format(): int
    {
        return 1;
    }
}
