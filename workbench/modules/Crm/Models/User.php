<?php

namespace Workbench\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\Crm\Enums\Status;

class User extends Model
{
    protected $table = 'crm_users';

    protected $fillable = [
        'name',
        'email',
        'company',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }
}
