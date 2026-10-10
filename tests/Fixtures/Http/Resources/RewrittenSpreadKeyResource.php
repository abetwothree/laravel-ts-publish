<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource that overrides a spread helper's key of two same-named models beside a key reading
 * one of them.
 *
 * @mixin Warehouse
 */
class RewrittenSpreadKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->who(),
            'who' => 'nobody',
            'manager' => $this->manager,
        ];
    }

    /** @return array<string, mixed> */
    protected function who(): array
    {
        return ['who' => $this->last_user_activity_by_typed];
    }
}
