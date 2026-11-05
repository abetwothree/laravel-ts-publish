<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use Workbench\App\Models\Post;

/** A value object whose properties are natively typed as intersections, one of them inside a DNF type. */
final class LabeledHolder
{
    public function __construct(
        public Post&HasLabel $post,
        public (AbstractEntry&HasLabel)|null $entry = null,
    ) {}

    /** The post, as the intersection its property declares. */
    public function primary(): Post&HasLabel
    {
        return $this->post;
    }
}
