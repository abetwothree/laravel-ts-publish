<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    /** Internal moderation notes — never published to the frontend. */
    protected $hidden = ['internal_notes'];

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'filename',
        'size_bytes',
        'internal_notes',
    ];

    /** Polymorphic parent (Post and friends) */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }
}
