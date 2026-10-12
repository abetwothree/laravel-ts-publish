<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\ArticleStatus;
use Workbench\App\Models\User;

/**
 * Vague `: array` helpers whose body names a class in one member, beside a member that does not.
 */
final class ClassNamingMemberModel extends Model
{
    protected $table = 'articles';

    /** @return array<string, ArticleStatus> */
    protected function casts(): array
    {
        return ['status' => ArticleStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, mixed> */
    public function withResource(): array
    {
        return ['r' => $this->author->toResource(), 'title' => $this->title];
    }

    /** @return array<string, mixed> */
    public function withEnumResource(): array
    {
        return ['s' => EnumResource::make($this->status), 'title' => $this->title];
    }
}
