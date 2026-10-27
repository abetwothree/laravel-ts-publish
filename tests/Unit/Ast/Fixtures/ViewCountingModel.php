<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A test-only model whose untyped `$views` its own method writes through `static::`, which a subclass redeclares. */
class ViewCountingModel extends Model
{
    public const START = 'a';

    public $views = 'x';

    protected $table = 'posts';

    /** The count, read in a body a resource forwards a call to. */
    public function getViews()
    {
        return $this->views;
    }

    /** Restart the count at the constant the object's class declares. */
    public function bump(): static
    {
        $this->views = static::START;

        return $this;
    }
}
