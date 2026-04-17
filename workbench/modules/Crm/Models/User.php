<?php

declare(strict_types=1);

namespace Workbench\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Workbench\App\Models\Image;
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

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
