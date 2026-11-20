<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. A member's helper casts away its wrap of the app
 * enum, beside a member wrapping the CRM enum.
 *
 * @mixin Warehouse
 */
class InlineCastWrapBesideCrmWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => ['a' => $this->held(), 'b' => EnumResource::make($this->current_crm_status)],
        ];
    }

    /**
     * The app enum's wrap, which the cast publishes as a string.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['w' => 'string'])]
    protected function held(): array
    {
        return ['w' => EnumResource::make($this->status)];
    }
}
