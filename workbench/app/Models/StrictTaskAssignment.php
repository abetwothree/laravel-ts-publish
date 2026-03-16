<?php

namespace Workbench\App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrictTaskAssignment extends Model
{
    use Compoships;

    protected $fillable = [
        'title',
        'team_id',
        'category_id',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TaskOwner::class, ['team_id', 'category_id'], ['team_id', 'category_id']);
    }
}
