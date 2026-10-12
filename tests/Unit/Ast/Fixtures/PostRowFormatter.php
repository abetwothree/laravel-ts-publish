<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Static helpers two resources read as values, one parameter untyped and one typed. */
final class PostRowFormatter
{
    /**
     * A title read off whatever the caller passes.
     *
     * @param  mixed  $subject
     * @return array<string, mixed>
     */
    public static function row($subject): array
    {
        return ['title' => $subject->title];
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
}
