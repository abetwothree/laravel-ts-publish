<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A work shift, whose resource publishes each value as json_encode() writes it.
 */
class Shift extends Model
{
    protected $fillable = ['name'];
}
