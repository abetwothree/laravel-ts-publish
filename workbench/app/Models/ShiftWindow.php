<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Casts\WallClock;
use Workbench\App\Casts\WallClockTime;

/**
 * When a shift's window opens (a NOT NULL column) and closes (a nullable one), both through one serializing cast
 * whose `get()` is nullable, and when its break starts (NOT NULL) and ends (nullable) through a Castable built on that
 * cast, beside a plain string label (GH-154).
 */
class ShiftWindow extends Model
{
    protected $fillable = ['label', 'opens_at', 'closes_at', 'break_starts_at', 'break_ends_at'];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => WallClockTime::class,
            'closes_at' => WallClockTime::class,
            'break_starts_at' => WallClock::class,
            'break_ends_at' => WallClock::class,
        ];
    }
}
