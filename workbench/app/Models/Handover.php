<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Passes work between two models that share a name: an application User sends and a CRM User receives, so a union
 * of the two spells `User` twice and each occurrence has to name its own class.
 */
class Handover extends Model
{
    protected $fillable = ['sender_id', 'receiver_id'];

    /**
     * The application user handing the work over.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The CRM user taking the work on.
     *
     * @return BelongsTo<CrmUser, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'receiver_id');
    }

    /**
     * The application users watching the handover.
     *
     * @return HasMany<User, $this>
     */
    public function watchers(): HasMany
    {
        return $this->hasMany(User::class, 'id', 'sender_id');
    }

    /**
     * The CRM users watching the handover.
     *
     * @return HasMany<CrmUser, $this>
     */
    public function crmWatchers(): HasMany
    {
        return $this->hasMany(CrmUser::class, 'id', 'receiver_id');
    }

    /** Whichever party is set: a union of two models that share a name. */
    protected function party(): Attribute
    {
        return Attribute::get(fn () => $this->sender ?? $this->receiver);
    }

    /** The same union over to-many relations, picked by a ternary. */
    protected function audience(): Attribute
    {
        return Attribute::get(fn () => $this->receiver_id === null ? $this->watchers : $this->crmWatchers);
    }
}
