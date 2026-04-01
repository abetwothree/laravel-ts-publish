<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskOwner extends Model
{
    use Compoships;

    protected $table = 'users';

    protected $fillable = [
        'name',
    ];

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, ['team_id', 'category_id'], ['team_id', 'category_id']);
    }
}
