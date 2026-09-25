<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;
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

    /** Either of two models that share a name, which one token cannot name both of. */
    protected function authorOrLead(): Attribute
    {
        return Attribute::get(fn () => $this->author ?? $this->crmAuthor);
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
}
