<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;
use Workbench\Crm\Models\User as CrmUser;

/** A test-only model on the `posts` table whose untyped getters return a related model. */
class AuthoredPost extends Model
{
    protected $table = 'posts';

    /**
     * The post's author.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The CRM user sharing the author's id.
     *
     * @return BelongsTo<CrmUser, $this>
     */
    public function crmAuthor(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'user_id');
    }

    /** The author model itself. */
    protected function authorModel(): Attribute
    {
        return Attribute::get(fn () => $this->author);
    }

    /** The CRM user model itself. */
    protected function lead(): Attribute
    {
        return Attribute::get(fn () => $this->crmAuthor);
    }

    /** Either of two models that share a name, each of which needs a token of its own. */
    protected function authorOrLead(): Attribute
    {
        return Attribute::get(fn () => $this->author ?? $this->crmAuthor);
    }

    /** One key naming a single model, beside one naming the union of the two that share a name. */
    protected function authorAndEither(): Attribute
    {
        return Attribute::get(fn () => ['author' => $this->author, 'either' => $this->author ?? $this->crmAuthor]);
    }

    /** One model queued twice behind a single token, before a key that names the other model. */
    protected function leadOrLabelAndAuthor(): Attribute
    {
        return Attribute::get(fn () => [
            'lead' => $this->crmAuthor ?? ($this->exists ? 'none' : $this->crmAuthor),
            'author' => $this->author,
        ]);
    }

    /** Either model behind an operand the engine cannot type: the fall-through keeps a token for each. */
    protected function cachedAuthorOrLead(): Attribute
    {
        return Attribute::get(fn () => cache('post.author') ?? $this->author ?? $this->crmAuthor);
    }

    /** Both models that share a name, each under a key of its own. */
    protected function bothAuthors(): Attribute
    {
        return Attribute::get(fn () => ['author' => $this->author, 'lead' => $this->crmAuthor]);
    }

    /** A resource, which the model file has no channel to import. */
    protected function authorResource(): Attribute
    {
        return Attribute::get(fn () => new UserResource($this->author));
    }

    /** The resource toResource() builds. */
    protected function authorToResource(): Attribute
    {
        return Attribute::get(fn () => $this->author?->toResource());
    }

    /**
     * A class that is neither a model nor a resource, so no file is ever generated for it.
     *
     * @return Attribute<OpaqueHandle, never>
     */
    protected function handle(): Attribute
    {
        return Attribute::get(fn (): OpaqueHandle => new OpaqueHandle);
    }
}
