<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class UuidPost extends Model
{
    public $incrementing = false;

    protected $table = 'posts';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';
}
