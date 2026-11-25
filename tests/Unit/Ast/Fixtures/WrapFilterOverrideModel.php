<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/**
 * A model whose only() override wraps the app's status, beside an accessor holding it or a model whose override wraps
 * the CRM's: a filter on the accessor unions two wraps one const name spells.
 */
final class WrapFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): EnumResource
    {
        return EnumResource::make(Status::Draft);
    }

    /** @return Attribute<WrapFilterOverrideModel|CrmWrapFilterOverrideModel|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }
}
