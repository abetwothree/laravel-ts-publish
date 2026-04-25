<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Casts\CoordinateCast;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\ValueObjects\Coordinate;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

#[TsExtends('HasTimestamps', import: '@/types/common')]
#[TsExtends('Pick<Auditable, "created_by" | "updated_by">', import: '@/types/audit', types: ['Auditable'])]
class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'coordinate_data',
        'status',
        'manager_id',
        'primary_contact_id',
        'secondary_contact_id',
    ];

    /** @var list<string> */
    protected $appends = [
        'location',
        'current_crm_status',
    ];

    protected function casts(): array
    {
        return [
            'coordinate_data' => CoordinateCast::class,
            'status' => Status::class,
            'color' => Color::class,
            'priority' => Priority::class,
        ];
    }

    /** Write-only accessor on DB column 'phone' — normalizes on set, no get */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => preg_replace('/[^0-9+]/', '', $value) ?? $value,
        );
    }

    /** Non-column accessor returning a plain class (Coordinate) */
    protected function location(): Attribute
    {
        return Attribute::make(
            get: fn (): Coordinate => Coordinate::fromString((string) ($this->coordinate_data ?? '0,0')),
        );
    }

    /** Non-column accessor returning a TsType class (MenuSettings) with custom import */
    protected function menuConfig(): Attribute
    {
        return Attribute::make(
            get: fn (): ?MenuSettings => null,
        );
    }

    /** Non-column accessor returning CRM Status enum — creates name conflict with column 'status' */
    protected function currentCrmStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CrmStatus => null,
        );
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'primary_contact_id');
    }

    public function secondaryContact(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'secondary_contact_id');
    }

    /** @return Attribute<CrmUser|User|null, never> */
    protected function lastUserActivityBy(): Attribute
    {
        return Attribute::get(function () {
            if ($this->updated_by_primary_contact_id) {
                return $this->primaryContact;
            } elseif ($this->updated_by_user_id) {
                return $this->manager;
            }

            return null;
        });
    }

    protected function lastUserActivityByTyped(): Attribute
    {
        return Attribute::get(function (): CrmUser|User|null {
            return $this->last_user_activity_by;
        });
    }

    protected function lastUserActivityByTypedShort(): Attribute
    {
        return Attribute::get(fn (): CrmUser|User|null => $this->last_user_activity_by);
    }

    /** @return Attribute<Status|Priority|null, never> */
    protected function reviewPriority(): Attribute
    {
        return Attribute::get(function () {
            if ($this->status !== null) {
                return $this->status;
            } elseif ($this->priority !== null) {
                return $this->priority;
            }

            return null;
        });
    }

    protected function reviewPriorityTyped(): Attribute
    {
        return Attribute::get(function (): Status|Priority|null {
            return $this->review_priority;
        });
    }

    protected function reviewPriorityTypedShort(): Attribute
    {
        return Attribute::get(fn (): Status|Priority|null => $this->review_priority);
    }
}
