<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\Post;

/**
 * A payload that passes array_values() each kind of arm: a list or null, a tuple, no values and a model.
 */
final class ArrayValuesArms
{
    /** @var list<string>|null */
    public ?array $maybeTags = null;

    /** @var list{int, string} */
    public array $pair = [1, 'a'];

    public Post $post;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'maybe_tags' => array_values($this->maybeTags),
            'pair' => array_values($this->pair),
            'none' => array_values([]),
            'post' => array_values($this->post),
        ];
    }
}
