<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\WeekDays;
use Workbench\App\ValueObjects\GridConfigDto;

/**
 * @phpstan-import-type GridConfig from GridConfigDto
 * @phpstan-import-type GridPreset from GridConfigDto
 *
 * @property GridConfig|null $grid_config
 * @property GridPreset|null $grid_preset
 * @property array<string, mixed>|null $settings
 */
class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
        'is_active',
        'settings',
        'grid_config',
        'grid_preset',
        'week_days',
        'grid_configs',
    ];

    /** The user who owns this team */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Named literally 'map' to pin the relation-filter guard against Laravel's ->map proxy. */
    public function map(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Members of the team (pivot includes role and joined_at) */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'grid_config' => 'array',
            'grid_preset' => 'array',
            'week_days' => AsEnumCollection::class.':'.WeekDays::class,
            'grid_configs' => AsCollection::of(GridConfigDto::class),
        ];
    }

    /** Whether the team has any members */
    protected function hasMember(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->members()->count() > 0,
        );
    }

    /** Number of members */
    protected function memberCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->members()->count(),
        );
    }

    /** @return Attribute<list<Status>, never> */
    protected function statusHistory(): Attribute
    {
        return Attribute::make(
            get: fn (): array => [Status::Draft, Status::Published],
        );
    }

    /**
     * A single scalar Status, distinct from statusHistory()'s array shape.
     *
     * @return Attribute<Status, never>
     */
    protected function latestStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): Status => Status::Draft,
        );
    }
}
