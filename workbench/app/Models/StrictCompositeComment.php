<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Relations\CompositeMorphTo;

class StrictCompositeComment extends Model
{
    protected $fillable = [
        'body',
        'commentable_type',
        'commentable_id_1',
        'commentable_id_2',
    ];

    public function commentable(): CompositeMorphTo
    {
        return new CompositeMorphTo(
            $this->newQuery()->setEagerLoads([]),
            $this,
            ['commentable_id_1', 'commentable_id_2'],
            null,
            'commentable_type',
            'commentable',
        );
    }
}
