<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\SharedExtendsTrait;

class BaseSharedExtendableModel extends Model
{
    use SharedExtendsTrait;

    protected $table = 'users';
}
