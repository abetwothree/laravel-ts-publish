<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\UiTone;
use Workbench\App\Packages\Ui\Gauge;

/**
 * Publishes an overridden enum's case and an overridden class through a helper that declares no return type, so its
 * body answers each with its override and import.
 */
class UiUntypedToneHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['picked' => $this->picked()];
    }

    /**
     * A tone, a gauge and a count, with no declared return type.
     */
    protected function picked()
    {
        return ['tone' => UiTone::Bright, 'gauge' => new Gauge, 'n' => 1];
    }
}
