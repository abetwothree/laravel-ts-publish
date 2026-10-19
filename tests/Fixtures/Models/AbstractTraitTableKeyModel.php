<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\DeclaresStringTableKey;
use Illuminate\Database\Eloquent\Model;

/** An abstract model whose string key comes from the #[Table] on a trait it uses. */
abstract class AbstractTraitTableKeyModel extends Model
{
    use DeclaresStringTableKey;
}
