<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Post;

/** Static helpers two resources read as values: one parameter untyped, one an abstract class, one a concrete model. */
final class PostRowFormatter
{
    /**
     * A title read off whatever the caller passes, beside a detail only this body names.
     *
     * @param  mixed  $subject
     * @return array<string, mixed>
     */
    public static function row($subject): array
    {
        return ['title' => $subject->title, 'detail' => (new PostRowDetail)->detail()];
    }

    /**
     * A key read off any model.
     *
     * @return array<string, mixed>
     */
    public static function typed(Model $model): array
    {
        return ['key' => $model->getKey()];
    }

    /**
     * A title read off the post.
     *
     * @return array<string, mixed>
     */
    public static function titled(Post $post): array
    {
        return ['title' => $post->title];
    }
}
