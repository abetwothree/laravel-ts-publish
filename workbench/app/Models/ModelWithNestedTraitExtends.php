<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasNestedExtendsTrait;

class ModelWithNestedTraitExtends extends Model
{
    use HasNestedExtendsTrait;

    protected $table = 'users';
}
