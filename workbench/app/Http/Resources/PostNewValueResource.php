<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use DateTime;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\DockLabel;
use Workbench\App\ValueObjects\ShiftClockReading;
use Workbench\App\ValueObjects\StampedDate;

/**
 * A `new` of a class that is not a resource, in place and as a parameter default.
 *
 * @mixin Post
 */
class PostNewValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'error' => new RuntimeException('x'),
            'markup' => new HtmlString('<b>x</b>'),
            'opened' => new DateTime('2026-01-01'),
            'opened_immutable' => new DateTimeImmutable('2026-01-01'),
            'stamped' => new StampedDate('2026-01-01'),
            'dock' => new DockLabel,
            'reading' => new ShiftClockReading,
            'reading_returned' => $this->reading(),
            'error_default' => $this->when($this->title !== '', fn ($e = new RuntimeException('x')) => $e),
            'opened_default' => $this->when($this->title !== '', fn ($d = new DateTime('2026-01-01')) => $d),
            'stamped_default' => $this->when($this->title !== '', fn ($d = new StampedDate('2026-01-01')) => $d),
            'dock_default' => $this->when($this->title !== '', fn ($d = new DockLabel) => $d),
            'reading_default' => $this->when($this->title !== '', fn ($r = new ShiftClockReading) => $r),
        ];
    }

    /**
     * A reading, typed only by its declared class.
     */
    protected function reading(): ShiftClockReading
    {
        return new ShiftClockReading;
    }
}
