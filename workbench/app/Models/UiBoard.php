<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Panel;

/**
 * A board whose appended accessor is a value of a composed class, so its interface imports the class's module.
 */
class UiBoard extends Model
{
    protected $appends = ['chart'];

    /**
     * Lists whose elements `AsCollection::of()` maps into a class with an inherited override and into a composed class.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'panels' => AsCollection::of(Panel::class),
            'charts' => AsCollection::of(Chart::class),
        ];
    }

    /**
     * A chart, by a getter closure that declares its class.
     */
    protected function chart(): Attribute
    {
        return Attribute::make(get: fn (): Chart => new Chart);
    }
}
