<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/**
 * Accessors whose getters call only() or except(), each read by a method whose vague `: array` return sends the
 * body fallback to its body.
 *
 * @property-read list<Comment> $comment_list
 * @property-read list<array{id: int}> $tagged_fields
 */
final class FilteringAccessorModel extends Model
{
    protected $table = 'posts';

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** @return BelongsTo<UntypedFilterOverrideModel, $this> */
    public function loose(): BelongsTo
    {
        return $this->belongsTo(UntypedFilterOverrideModel::class, 'user_id');
    }

    /** @return BelongsTo<FilteringAccessorModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_id');
    }

    /** @return HasMany<FilteringAccessorModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'user_id');
    }

    /** @return BelongsTo<AppendingFilteringAccessorModel, $this> */
    public function appending(): BelongsTo
    {
        return $this->belongsTo(AppendingFilteringAccessorModel::class, 'user_id');
    }

    /** @return Attribute<Comment|User|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }

    /** Filters bare `$this` by a literal key list. */
    protected function ownPicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->only(['id', 'title']), 'id' => $this->id]);
    }

    /** Filters bare `$this` by a runtime key list. */
    protected function ownRuntime(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->only(request()->input('fields')), 'id' => $this->id]);
    }

    /** Returns a runtime-key filter directly, so the getter's whole answer is vague with imports too. */
    protected function runtimeFields(): Attribute
    {
        return Attribute::get(fn () => $this->only(request()->input('fields')));
    }

    /** A vague signature and a vague body, so the `@property-read` tag types it. */
    protected function taggedFields(): Attribute
    {
        return Attribute::get(fn (): array => array_values($this->only(request()->input('fields'))));
    }

    /** Filters bare `$this` through `?->`. */
    protected function ownNullsafe(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this?->only(['id', 'content']), 'id' => $this->id]);
    }

    /** Filters a single relation, keeping an enum column. */
    protected function authorPicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->author?->only(['id', 'role']), 'id' => $this->id]);
    }

    /** Filters a to-many relation. */
    protected function commentPicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->comments->only([1, 2]), 'id' => $this->id]);
    }

    /** Returns a to-many relation's filter directly, so the getter's whole answer is the list. */
    protected function commentList(): Attribute
    {
        return Attribute::get(fn () => $this->comments->only([1, 2]));
    }

    /** Filters a relation to a model whose overrides declare no return. */
    protected function loosePicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->loose->only(['id', 'title']), 'id' => $this->id]);
    }

    /** Filters through an accessor typed as one of two models. */
    protected function counterpartPicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->counterpart?->only(['id']), 'id' => $this->id]);
    }

    /** A literal shape with no filter. */
    protected function literal(): Attribute
    {
        return Attribute::get(fn () => ['a' => 1, 'b' => $this->title]);
    }

    /** @return Attribute<User, never> */
    protected function owner(): Attribute
    {
        return Attribute::get(fn () => $this->author);
    }

    /** Reads loop_b, which reads this accessor back. */
    protected function loopA(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->loop_b, 'p' => $this->author->only(['id'])]);
    }

    /** Reads loop_a, which reads this accessor back. */
    protected function loopB(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->loop_a, 'p' => $this->author->only(['id'])]);
    }

    /**
     * An old-style accessor filtering a single relation.
     *
     * @return array<string, mixed>
     */
    public function getLegacyPicksAttribute(): array
    {
        return ['v' => $this->author->only(['id', 'name']), 'id' => $this->id];
    }

    /** Calls a method whose body reads this accessor back. */
    protected function selfReport(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->author->only(['id']), 'report' => $this->report()]);
    }

    /** @return array<string, mixed> */
    public function readOwnPicks(): array
    {
        return ['v' => $this->own_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readOwnRuntime(): array
    {
        return ['v' => $this->own_runtime, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readRuntimeFields(): array
    {
        return ['v' => $this->runtime_fields, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readTaggedFields(): array
    {
        return ['v' => $this->tagged_fields, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readOwnNullsafe(): array
    {
        return ['v' => $this->own_nullsafe, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readAuthorPicks(): array
    {
        return ['v' => $this->author_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readCommentPicks(): array
    {
        return ['v' => $this->comment_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readCommentList(): array
    {
        return ['v' => $this->comment_list, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readLoosePicks(): array
    {
        return ['v' => $this->loose_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readCounterpartPicks(): array
    {
        return ['v' => $this->counterpart_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readLiteral(): array
    {
        return ['v' => $this->literal, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readOwner(): array
    {
        return ['v' => $this->owner, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readLegacyPicks(): array
    {
        return ['v' => $this->legacy_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readLoop(): array
    {
        return ['v' => $this->loop_a, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return ['self' => $this->self_report, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readThroughRelation(): array
    {
        return ['v' => $this->twin?->author_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readCamelAlias(): array
    {
        return ['v' => $this->authorPicks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readThroughLocal(): array
    {
        $twin = $this->twin;

        return ['v' => $twin->author_picks, 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readThroughVariable(): array
    {
        return ['v' => $this->twins->map(fn (FilteringAccessorModel $twin) => $twin->comment_list), 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readAsFilteredKey(): array
    {
        return ['v' => $this->twin->only(['id', 'author_picks']), 'id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function readAsSpreadKey(): array
    {
        return [...$this->only(['id', 'author_picks']), 'x' => 1];
    }

    /** @return array<string, mixed> */
    public function readAsSpreadAlias(): array
    {
        return [...$this->only(['id', 'authorPicks']), 'x' => 1];
    }

    /** @return array<string, mixed> */
    public function readAsSpreadAppend(): array
    {
        return [...$this->appending->toArray(), 'x' => 1];
    }
}
