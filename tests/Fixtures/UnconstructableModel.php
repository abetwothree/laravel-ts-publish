<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** A test-only model whose constructor throws, as one that needs a dependency the application cannot give does. */
class UnconstructableModel extends Model
{
    public function __construct()
    {
        throw new RuntimeException('This model cannot be constructed.');
    }
}
