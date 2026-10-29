<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that names no model: its helpers read a model column through a private `$this->` call and a
 * `self::` call, both of which run for the subclass that names the model.
 */
abstract class ModelHelperBaseResource extends JsonResource
{
    /** The title, through a private helper. */
    protected function viaProtected()
    {
        return $this->privateTitle();
    }

    /** The title, through a `self::` call. */
    protected function viaSelf()
    {
        return self::protectedTitle();
    }

    /** The title in a literal shape, through a `self::` call. */
    protected function viaSelfShape()
    {
        return self::shapeOf();
    }

    /** The title column. */
    protected function protectedTitle()
    {
        return $this->title;
    }

    /** The title column in a literal shape. */
    protected function shapeOf()
    {
        return ['t' => $this->title];
    }

    /** The title column, in a private body. */
    private function privateTitle()
    {
        return $this->title;
    }
}
