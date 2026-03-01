<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    #[TsCasts(['metadata' => 'Record<string, unknown>'])]
    public function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
