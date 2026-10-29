<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Panel;

/**
 * Publishes a `new` of an overridden class through a helper that declares no return type, so its body answers.
 */
class UiUntypedHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['panel' => $this->panel()];
    }

    /**
     * A panel, with no declared return type.
     */
    protected function panel()
    {
        return new Panel;
    }
}
