<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A new-style accessor whose value is a composed class; its test creates the `ui_gadget_holders` table.
 */
class UiGadgetHolder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $appends = ['gadget'];

    /**
     * A gadget, by a getter closure that declares its class.
     */
    protected function gadget(): Attribute
    {
        return Attribute::make(get: fn (): Gadget => new Gadget);
    }
}
