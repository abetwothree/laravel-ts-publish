<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** A post whose methods share names with Support\Collection's, with other returns. */
final class ProxyCollectedPost extends Model
{
    protected $table = 'posts';

    /** The title, under the name of Collection::first(). */
    public function first(): string
    {
        return (string) $this->title;
    }

    /** The content, under the name of Collection::values(). */
    public function values(): string
    {
        return (string) $this->content;
    }

    /** A word, under the name of Collection::count(). */
    public function count(): string
    {
        return 'many';
    }

    /** A flag, under the name of Collection::sum(). */
    public function sum(): bool
    {
        return true;
    }
}
