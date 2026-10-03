<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;

/**
 * Shares three keys between attributes and relations, each attribute bringing an import of its own: a `handler`
 * column cast to an enum, an appended `sender` accessor typed by the same enum, and a `manifest` column typed by a
 * `#[TsCasts]` import. The `priority` column is cast to an enum no relation shares.
 */
class Parcel extends Model
{
    protected $fillable = ['handler', 'handler_id', 'sender_id', 'manifest', 'manifest_id', 'priority'];

    protected $appends = ['sender'];

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'manifest_id');
    }

    /** The role the parcel was sent under. */
    public function getSenderAttribute(): Role
    {
        return Role::User;
    }

    #[TsCasts(['manifest' => ['type' => 'ParcelManifest', 'import' => '@js/types/manifest']])]
    protected function casts(): array
    {
        return [
            'handler' => Role::class,
            'manifest' => 'array',
            'priority' => Priority::class,
        ];
    }
}
