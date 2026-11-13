<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\GapModeLabelCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** The `posts` table with an accessor whose body reads a helper, and a column cast to a caster whose get() does. */
class GapModeReadsPost extends Model
{
    protected $table = 'posts';

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => GapModeLabelCast::class];
    }

    /**
     * A summary its getter reads from a helper.
     */
    protected function summary(): Attribute
    {
        return Attribute::make(get: fn () => $this->summaryText());
    }

    /**
     * The summary's text.
     */
    private function summaryText(): string
    {
        return 'summary';
    }
}
