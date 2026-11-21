<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * One arm the guard's bodies hold, each naming the classes its published member must resolve to, in print order.
 */
enum ArmKind: string
{
    case WrapAppStatus = 'wrap app Status';
    case WrapCrmStatus = 'wrap CRM Status';
    case WrapRole = 'wrap Role';
    case ReadAppStatus = 'read app Status';
    case ReadCrmStatus = 'read CRM Status';
    case AppUser = 'app User';
    case CrmUser = 'CRM User';
    case AppUserResource = 'app UserResource';
    case CrmUserResource = 'CRM UserResource';
    case AddressModel = 'Address model';
    case AddressResource = 'AddressResource';
    case Null = 'null';
    case Rows = 'rows';
    case Untyped = 'untyped';

    /**
     * The PHP expression the arm writes in a generated resource's toArray().
     */
    public function expression(): string
    {
        return match ($this) {
            self::WrapAppStatus => 'EnumResource::make($this->resource->status)',
            self::WrapCrmStatus => 'EnumResource::make($this->resource->crmStatus)',
            self::WrapRole => 'EnumResource::make($this->resource->role)',
            self::ReadAppStatus => '$this->resource->status',
            self::ReadCrmStatus => '$this->resource->crmStatus',
            self::AppUser => '$this->resource->user',
            self::CrmUser => '$this->resource->crmUser',
            self::AppUserResource => 'new UserResource($this->resource->user)',
            self::CrmUserResource => 'new CrmUserResource($this->resource->crmUser)',
            self::AddressModel => '$this->resource->address',
            self::AddressResource => 'new AddressResource($this->resource->address)',
            self::Null => 'null',
            self::Rows => "array_map(fn (GuardRow \$r) => ['c' => \$r->crmStatus, 's' => \$r->status], "
                .'$this->resource->rows)',
            self::Untyped => 'json_decode($this->resource->pick)',
        };
    }

    /**
     * The oracle: the class each token of the arm's published member names, in print order.
     *
     * @return list<class-string>
     */
    public function classes(): array
    {
        return match ($this) {
            self::WrapAppStatus, self::ReadAppStatus => [Status::class],
            self::WrapCrmStatus, self::ReadCrmStatus => [CrmStatus::class],
            self::WrapRole => [Role::class],
            self::AppUser => [User::class],
            self::CrmUser => [CrmUser::class],
            self::AppUserResource => [UserResource::class],
            self::CrmUserResource => [CrmUserResource::class],
            self::AddressModel => [Address::class],
            self::AddressResource => [AddressResource::class],
            self::Null, self::Untyped => [],
            self::Rows => [CrmStatus::class, Status::class],
        };
    }
}
