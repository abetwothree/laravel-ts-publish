<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A test-only model with no table of its own: its helpers read the title column through `self::` and a private
 * `$this->` call, both of which run for SelfHelperPost.
 */
abstract class SelfHelperBaseModel extends Model
{
    /** The title, through a `self::` call. */
    public function viaSelf()
    {
        return self::titleOf();
    }

    /** The title in a literal shape, through a `self::` call. */
    public function viaSelfShape()
    {
        return self::shapeOf();
    }

    /** The title filtered with only(), through a `self::` call. */
    public function viaSelfOnly()
    {
        return self::onlyOf();
    }

    /** The title, through a private helper. */
    public function viaPrivate()
    {
        return $this->privateTitle();
    }

    /** The title column. */
    protected function titleOf()
    {
        return $this->title;
    }

    /** The title column in a literal shape, beside it filtered with only(). */
    protected function shapeOf()
    {
        return ['t' => $this->title, 'picked' => $this->only('title')];
    }

    /** The title column, filtered with only(). */
    protected function onlyOf()
    {
        return $this->only('title');
    }

    /** The title column, in a private body. */
    private function privateTitle()
    {
        return $this->title;
    }
}
