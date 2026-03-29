<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class SlugPost extends Model
{
    protected $table = 'posts';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
