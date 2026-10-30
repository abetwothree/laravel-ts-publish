<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'assignee_id', 'title', 'description', 'status', 'settings', 'due_at', 'completed_at'])]
class Task extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** Whether the task is past its due date and still open */
    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn (): bool => $this->due_at !== null
            && $this->completed_at === null
            && $this->due_at->isPast());
    }
}
