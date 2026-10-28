<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Panel;

/**
 * Publishes values of a class that inherits its parent's override, through a declared helper and a `@return` list.
 */
class UiPanelHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'panel' => $this->panel(),
            'panels' => $this->panels(),
        ];
    }

    protected function panel(): Panel
    {
        return new Panel;
    }

    /** @return list<Panel> */
    protected function panels(): array
    {
        return [new Panel, new Panel];
    }
}
