<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Role;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

/**
 * A test-only model on the `parcels` table where one relation shares its key with the one enum-cast column and another
 * with an accessor typed by a model, and where accessors take both relations' count and exists keys.
 */
class ShadowedEnumParcel extends Model
{
    protected $table = 'parcels';

    /**
     * The parcel's handler.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /**
     * The parcel's courier.
     *
     * @return BelongsTo<User, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** How many handlers the parcel has had, under the key the relation's count would take. */
    public function getHandlerCountAttribute(): int
    {
        return 1;
    }

    /** Whether the parcel has a handler, under the key the relation's exists flag would take. */
    public function getHandlerExistsAttribute(): bool
    {
        return true;
    }

    /** The order the courier carries, under the key the relation also takes. */
    public function getCourierAttribute(): Order
    {
        return new Order;
    }

    /** How many couriers the parcel has had, under the key the relation's count would take. */
    public function getCourierCountAttribute(): int
    {
        return 1;
    }

    /** Whether the parcel has a courier, under the key the relation's exists flag would take. */
    public function getCourierExistsAttribute(): bool
    {
        return true;
    }

    /**
     * The handler column holds a role.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handler' => Role::class];
    }
}
