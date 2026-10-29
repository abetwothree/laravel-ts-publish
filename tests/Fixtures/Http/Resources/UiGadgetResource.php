<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Publishes a value of a composed class no scanned directory holds.
 */
class UiGadgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['gadget' => $this->gadget()];
    }

    /**
     * A gadget, by its declared class.
     */
    protected function gadget(): Gadget
    {
        return new Gadget;
    }
}
