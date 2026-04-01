<?php

declare(strict_types=1);

namespace Workbench\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

class Reaction extends Model
{
    protected $fillable = [
        'article_id',
        'user_id',
        'emoji',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
