<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shares keys between attributes and relations: a `supervisor` column beside a `supervisor()` relation, and an
 * `orders_count` counter-cache column beside the `orders()` relation's own count key.
 */
class Depot extends Model
{
    protected $fillable = ['name', 'supervisor', 'supervisor_id', 'orders_count'];

    /**
     * The user who runs the depot.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * The orders the depot ships.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'depot_id');
    }
}
