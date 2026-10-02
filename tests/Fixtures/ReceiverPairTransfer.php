<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Post;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only model on the `handovers` table that answers ReceiverPairHandover's `sender` and `item` with other models,
 * so a read of either through the two classes holds a pair that shares a name and a pair that does not.
 */
class ReceiverPairTransfer extends Model
{
    protected $table = 'handovers';

    /**
     * The CRM user sending.
     *
     * @return BelongsTo<CrmUser, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'sender_id');
    }

    /**
     * A model whose name no other class in these tests shares.
     *
     * @return BelongsTo<Post, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'receiver_id');
    }
}
