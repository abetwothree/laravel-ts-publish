<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The helpers InterveningWritesTest calls between a discriminant's keys: ones that write the pin or only read, ones
 * that call themselves or each other, and a chain four levels deep.
 *
 * @mixin Post
 */
class InterveningResource extends JsonResource
{
    public bool $pinned = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /**
     * Upper-cases a value, as a static method.
     */
    private static function shout(?string $value): string
    {
        return strtoupper((string) $value);
    }

    /**
     * Clears the flag it is handed.
     */
    private function clearFlag(bool &$flag): bool
    {
        $flag = false;

        return true;
    }

    /**
     * Clears the pin on the model.
     */
    private function writer(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Clears the pin through another helper.
     */
    private function writesThroughWriter(): bool
    {
        return $this->writer();
    }

    /**
     * Reads the title, with a suffix.
     */
    private function reader(?string $suffix = null): string
    {
        return $this->title.$suffix;
    }

    /**
     * Clears the pin once it has called itself down to zero.
     */
    private function unpinAfter(int $times): bool
    {
        if ($times > 0) {
            return $this->unpinAfter($times - 1);
        }

        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Counts down by calling itself, writing nothing.
     */
    private function countDown(int $from): int
    {
        return $from > 0 ? $this->countDown($from - 1) : 0;
    }

    /**
     * Reads the title through pong(), which calls back.
     */
    private function ping(int $times): string
    {
        return $times > 0 ? $this->pong($times - 1) : $this->reader();
    }

    /**
     * Calls ping() back.
     */
    private function pong(int $times): string
    {
        return $this->ping($times);
    }

    /**
     * The first of four helpers, each calling the next; only the last reads.
     */
    private function depthOne(): string
    {
        return $this->depthTwo();
    }

    /**
     * The second of four helpers.
     */
    private function depthTwo(): string
    {
        return $this->depthThree();
    }

    /**
     * The third of four helpers.
     */
    private function depthThree(): string
    {
        return $this->depthFour();
    }

    /**
     * The last of four helpers, which reads.
     */
    private function depthFour(): string
    {
        return (string) $this->title;
    }
}
