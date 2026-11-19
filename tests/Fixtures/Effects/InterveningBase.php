<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The parent InterveningResource's helpers reach through `parent::`, an ancestor's name, or a body whose `self::` names
 * this class rather than the subclass.
 */
class InterveningBase extends JsonResource
{
    /**
     * Reads the title.
     */
    protected function baseReader(): string
    {
        return (string) $this->resource->title;
    }

    /**
     * Clears the pin.
     */
    protected function baseWriter(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Runs this class's pick(), which writes, where the subclass's own pick() only reads.
     */
    protected function viaSelf(): bool
    {
        return self::pick();
    }

    /**
     * Clears the pin.
     */
    private function pick(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }
}
