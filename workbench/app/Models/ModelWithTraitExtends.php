<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasExtendsTrait;

class ModelWithTraitExtends extends Model
{
    use HasExtendsTrait;

    protected $table = 'users';
}
