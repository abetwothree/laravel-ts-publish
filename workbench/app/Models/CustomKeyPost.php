<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomKeyPost extends Model
{
    protected $table = 'posts';

    /**
     * Override getKeyName without overriding $primaryKey or getRouteKeyName.
     *
     * This exercises the getKeyName override detection path in RouteTransformer.
     */
    public function getKeyName(): string
    {
        return 'custom_key';
    }
}
