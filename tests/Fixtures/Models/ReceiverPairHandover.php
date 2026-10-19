<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only model on the `handovers` table whose accessors call a method on a receiver holding two models that share
 * a name, so the call answers one `User` token for two classes unless each class gets a token of its own.
 */
class ReceiverPairHandover extends Model
{
    protected $table = 'handovers';

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
     * A model whose name no other class in these tests shares.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'sender_id');
    }

    /**
     * The user who reviewed it, an application one or a CRM one.
     *
     * @return MorphTo<CrmUser|User, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** A copy of whichever party is set, behind a ternary. */
    protected function replica(): Attribute
    {
        return Attribute::get(fn () => $this->receiver_id === null ? ($this->sender ?? $this->receiver)?->replicate() : null);
    }

    /** A fluent call on the morph relation, behind a ternary. */
    protected function reviewerCopyWrapped(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('reviewable') ? $this->reviewable?->withoutRelations() : null);
    }

    /** The same call behind a coalesce. */
    protected function reviewerCopyOrLabel(): Attribute
    {
        return Attribute::get(fn () => $this->reviewable?->withoutRelations() ?? 'unreviewed');
    }

    /** The same call on its own. */
    protected function reviewerCopy(): Attribute
    {
        return Attribute::get(fn () => $this->reviewable?->withoutRelations());
    }
}
