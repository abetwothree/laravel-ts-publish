<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Model;
use Workbench\Crm\Enums\Status as CrmStatus;

/** A model whose only() override wraps the CRM's status, which prints the const name the app's status does. */
final class CrmWrapFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): EnumResource
    {
        return EnumResource::make(CrmStatus::Lead);
    }
}
