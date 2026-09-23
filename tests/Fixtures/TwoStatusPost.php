<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

/** A test-only model on the `posts` table whose accessors return either of two enums that share a name. */
class TwoStatusPost extends Model
{
    protected $table = 'posts';

    /**
     * The CRM user sharing this post's author id, whose own status is the CRM enum.
     *
     * @return BelongsTo<CrmUser, $this>
     */
    public function crmAuthor(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'user_id');
    }

    /**
     * The status column's enum.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => Status::class];
    }

    /** An untyped getter that may return either enum. */
    protected function statusOrLead(): Attribute
    {
        return Attribute::get(fn () => $this->status ?? $this->crmAuthor?->status);
    }

    /** A getter whose native return names both enums. */
    protected function eitherStatus(): Attribute
    {
        return Attribute::get(fn (): Status|CrmStatus => $this->status);
    }
}
