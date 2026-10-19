<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only model on the `handovers` table whose accessors return arrays that name the application User and the CRM
 * User under separate keys. Some keys hold a union that queues its class off its tokens, so the next key's token reads
 * the wrong class unless each key hands on one queue entry per token. Its relations are the members they read.
 */
class HandoverLedger extends Model
{
    protected $table = 'handovers';

    protected $appends = ['parties'];

    /**
     * The application user sending.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The CRM user receiving.
     *
     * @return BelongsTo<CrmUser, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'receiver_id');
    }

    /**
     * The application users watching.
     *
     * @return HasMany<User, $this>
     */
    public function watchers(): HasMany
    {
        return $this->hasMany(User::class, 'id', 'sender_id');
    }

    /**
     * The CRM users watching.
     *
     * @return HasMany<CrmUser, $this>
     */
    public function crmWatchers(): HasMany
    {
        return $this->hasMany(CrmUser::class, 'id', 'receiver_id');
    }

    /**
     * Another ledger, so a resource can read an accessor through a relation.
     *
     * @return BelongsTo<self, $this>
     */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sender_id');
    }

    /** Appended: one key naming a single model, and one naming the union of both. */
    protected function parties(): Attribute
    {
        return Attribute::get(fn () => ['first' => $this->sender, 'either' => $this->sender ?? $this->receiver]);
    }

    /** The same body on a column, which no relation uses as its key. */
    protected function updatedAt(): Attribute
    {
        return Attribute::get(fn () => ['first' => $this->sender, 'either' => $this->sender ?? $this->receiver]);
    }

    /** A class queued twice behind one token, then a single and a list of the other: three entries for three tokens. */
    protected function whoAndCrm(): Attribute
    {
        return Attribute::get(fn () => [
            'who' => $this->exists ? ['k' => $this->sender, 'n' => 1] : ['k' => $this->sender, 'n' => 2],
            'crm' => $this->exists ? $this->receiver : $this->crmWatchers,
        ]);
    }

    /** A single and a list of one class queued once for two tokens, then the other class. */
    protected function watchersThenReceiver(): Attribute
    {
        return Attribute::get(fn () => [
            'a' => $this->exists ? $this->sender : $this->watchers,
            'b' => $this->receiver,
        ]);
    }

    /** The same shortfall first, then a class queued twice behind one token. */
    protected function crmThenLead(): Attribute
    {
        return Attribute::get(fn () => [
            'crm' => $this->exists ? $this->receiver : $this->crmWatchers,
            'lead' => $this->sender ?? ($this->exists ? 'none' : $this->sender),
        ]);
    }
}
