<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Grade as GradeEnum;

/**
 * Named like the enum it casts to, so its file declares `Grade` and imports a const named `Grade`.
 */
class Grade extends Model
{
    protected $fillable = ['subject', 'grade'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade' => GradeEnum::class,
        ];
    }
}
