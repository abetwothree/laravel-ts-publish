<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only model on the `handovers` table whose accessors are typed by a docblock union whose arms render alike for
 * two models that share a name: a mutator, an append and one on a column.
 */
class HandoverCrew extends Model
{
    protected $table = 'handovers';

    protected $appends = ['standby'];

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
     * Another crew, so a resource can read an accessor through a relation.
     *
     * @return BelongsTo<self, $this>
     */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sender_id');
    }

    /**
     * The crew, as an array of either model.
     *
     * @return User[]|CrmUser[]
     */
    public function getCrewAttribute(): array
    {
        return [];
    }

    /**
     * Appended: the crew on standby, as a list of either model.
     *
     * @return list<User>|list<CrmUser>
     */
    public function getStandbyAttribute(): array
    {
        return [];
    }

    /**
     * The crew that last updated the handover, on a column, as an array of either model.
     *
     * @return User[]|CrmUser[]
     */
    public function getUpdatedAtAttribute(): array
    {
        return [];
    }
}
