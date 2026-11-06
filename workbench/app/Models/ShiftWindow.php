<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Casts\WallClockTime;

/**
 * When a shift's window opens (a NOT NULL column) and closes (a nullable one), both through one serializing cast
 * whose `get()` is nullable (GH-154).
 */
class ShiftWindow extends Model
{
    protected $fillable = ['opens_at', 'closes_at'];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => WallClockTime::class,
            'closes_at' => WallClockTime::class,
        ];
    }
}
