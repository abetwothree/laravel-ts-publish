<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Casts\CoordinateCast;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Status;
use Workbench\App\ValueObjects\Coordinate;
use Workbench\Crm\Enums\Status as CrmStatus;

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

    protected function casts(): array
    {
        return [
            'coordinate_data' => CoordinateCast::class,
            'status' => Status::class,
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
        return $this->belongsTo(\Workbench\Crm\Models\User::class, 'primary_contact_id');
    }

    public function secondaryContact(): BelongsTo
    {
        return $this->belongsTo(\Workbench\Crm\Models\User::class, 'secondary_contact_id');
    }
}
