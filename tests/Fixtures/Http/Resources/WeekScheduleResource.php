<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SchedulePost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WeekSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource reading a value object keyed by numbers every way a key can, which its filter never enters,
 * beside an array it lists, and the object beside such an array or spread out of a collection, which one key's flag
 * cannot keep.
 *
 * @mixin SchedulePost
 */
final class WeekScheduleResource extends JsonResource
{
    protected ?WeekSchedule $fallback = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'schedule' => $this->schedule(),
            'schedule_new' => new WeekSchedule,
            'schedule_property' => $this->fallback,
            'schedule_cast' => $this->metadata,
            'schedule_default' => $this->when($this->id > 0, fn ($schedule = new WeekSchedule) => $schedule),
            'listed' => [1 => 'mon', 2 => 'tue'],
            'by_week' => ['week' => $this->schedule()],
            'weeks' => ['week' => [$this->schedule()]],
            'week_and_days' => ['week' => $this->schedule(), 'days' => [1 => 'mon', 2 => 'tue']],
            'schedule_or_listed' => $this->id > 5 ? $this->schedule() : [1 => 'mon', 2 => 'tue'],
            'spread_schedules' => ['o' => [...collect([$this->schedule()])]],
        ];
    }

    /**
     * The schedule this post keeps.
     */
    public function schedule(): WeekSchedule
    {
        return new WeekSchedule;
    }
}
