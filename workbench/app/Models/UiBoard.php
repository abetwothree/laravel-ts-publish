<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;

/**
 * A board whose appended accessor is a value of a composed class, so its interface imports the class's module.
 */
class UiBoard extends Model
{
    protected $appends = ['chart'];

    /**
     * A chart, by a getter closure that declares its class.
     */
    protected function chart(): Attribute
    {
        return Attribute::make(get: fn (): Chart => new Chart);
    }
}
