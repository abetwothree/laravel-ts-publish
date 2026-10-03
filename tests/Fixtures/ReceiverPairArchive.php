<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairThird\User as ThirdUser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only model on the `handovers` table whose accessors read a member typed by a docblock union of two models that
 * share a name: through a receiver of two classes, and through a ternary over one. Each is read as a property and as a
 * method, the two forms the receiver rules take. Its relations are the other members those reads sit beside.
 */
class ReceiverPairArchive extends Model
{
    protected $table = 'handovers';

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
     * A user of the third model that shares the name.
     *
     * @return BelongsTo<ThirdUser, $this>
     */
    public function third(): BelongsTo
    {
        return $this->belongsTo(ThirdUser::class, 'sender_id');
    }

    /**
     * The users of the third model watching.
     *
     * @return HasMany<ThirdUser, $this>
     */
    public function thirdWatchers(): HasMany
    {
        return $this->hasMany(ThirdUser::class, 'id', 'sender_id');
    }

    /** A property of two receivers that hold the same pair. */
    protected function ownersOfEither(): Attribute
    {
        return Attribute::get(fn () => ($this->exists ? new ReceiverPairDirectory : new ReceiverPairRegistry)->owners);
    }

    /** A method of two receivers that hold the same pair. */
    protected function ownerListOfEither(): Attribute
    {
        return Attribute::get(fn () => ($this->exists ? new ReceiverPairDirectory : new ReceiverPairRegistry)->ownerList());
    }

    /** A property of two receivers, the second holding a model that shares no name. */
    protected function ownersOfMixed(): Attribute
    {
        return Attribute::get(fn () => ($this->exists ? new ReceiverPairDirectory : new ReceiverPostDirectory)->owners);
    }

    /** A method of two receivers, the second holding a model that shares no name. */
    protected function ownerListOfMixed(): Attribute
    {
        return Attribute::get(fn () => ($this->exists ? new ReceiverPairDirectory : new ReceiverPostDirectory)->ownerList());
    }

    /** A property of one receiver, behind a ternary. */
    protected function ownersOrNull(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : null);
    }

    /** A method of one receiver, behind a ternary. */
    protected function ownerListOrNull(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->ownerList() : null);
    }

    /** A property of one receiver, on its own. */
    protected function ownersAlone(): Attribute
    {
        return Attribute::get(fn () => (new ReceiverPairDirectory)->owners);
    }

    /** A property of one receiver, beside a string. */
    protected function ownersOrLabel(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : 'none');
    }

    /** A property of one receiver, beside the watchers of the application class. */
    protected function ownersOrWatchers(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : $this->watchers);
    }

    /** A property of one receiver, beside the watchers of the CRM class. */
    protected function ownersOrCrmWatchers(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : $this->crmWatchers);
    }

    /** Both watchers, then the property of one receiver, in a ternary of a ternary. */
    protected function bothWatchersOrOwners(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->watchers : ($this->receiver_id ? $this->crmWatchers : (new ReceiverPairDirectory)->owners));
    }

    /** A property of one receiver, beside the CRM user or the third user. */
    protected function ownersOrReceiverOrThird(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : ($this->receiver_id ? $this->receiver : $this->third));
    }

    /** A property of one receiver, beside the third watchers or the CRM user. */
    protected function ownersOrThirdWatchersOrReceiver(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : ($this->receiver_id ? $this->thirdWatchers : $this->receiver));
    }

    /** A property of one receiver, beside the CRM watchers or the third watchers. */
    protected function ownersOrCrmWatchersOrThirdWatchers(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? (new ReceiverPairDirectory)->owners : ($this->receiver_id ? $this->crmWatchers : $this->thirdWatchers));
    }

    /** A property of one receiver or the CRM user, then the third user: the inner union is an arm of the outer one. */
    protected function ownersOrReceiverThenThird(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? ($this->receiver_id ? (new ReceiverPairDirectory)->owners : $this->receiver) : $this->third);
    }

    /** The same, with the application user in the inner union. */
    protected function ownersOrSenderThenThird(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? ($this->receiver_id ? (new ReceiverPairDirectory)->owners : $this->sender) : $this->third);
    }

    /** The third user, else the CRM user, else a property of one receiver: the inner union is the last arm. */
    protected function thirdOrElseReceiverOrElseOwners(): Attribute
    {
        return Attribute::get(fn () => $this->third ?? ($this->receiver ?? (new ReceiverPairDirectory)->owners));
    }
}
