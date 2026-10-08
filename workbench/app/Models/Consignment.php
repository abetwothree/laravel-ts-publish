<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A consignment whose casts publish what Laravel serializes: a `timestamp` cast is the Unix integer, while the
 * `created_at` and `updated_at` columns keep the date type.
 */
class Consignment extends Model
{
    protected $fillable = ['scanned_at', 'declared_value', 'legs'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'timestamp',
        ];
    }
}
