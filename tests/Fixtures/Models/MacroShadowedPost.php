<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** A model on the `posts` table declaring methods that resource macros share a name with. */
class MacroShadowedPost extends Model
{
    protected $table = 'posts';

    /** A number the resource macro of the same name never returns. */
    public function label(): int
    {
        return 7;
    }

    /** A date the resource macro of the same name replaces with a Stringable. */
    public function moment(): Carbon
    {
        return Carbon::parse('2026-01-02');
    }

    /** A number a mixin method of the same name replaces. */
    public function badge(): int
    {
        return 1;
    }

    /** A string an invokable macro replaces with a float. */
    public function ticket(): string
    {
        return 'T';
    }

    /** A string a docblock-only macro replaces with a list. */
    public function codes(): string
    {
        return 'c';
    }
}
