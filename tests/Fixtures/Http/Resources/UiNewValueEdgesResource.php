<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\EveryWayNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Stamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Plain;

/**
 * A `new` of an overridden class in the shapes beside the workbench's: a class every serialization rule would answer, a
 * date, a nullable override and a parameter default; a model's, an API resource's and a plain class's keep theirs.
 */
class UiNewValueEdgesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'every_way' => new EveryWayNode,
            'stamp' => new Stamp,
            'maybe_node' => $request->boolean('node') ? new MaybeNode : null,
            'default_gauge' => $this->when(true, fn ($gauge = new Gauge) => $gauge),
            'model' => new InheritingChildModel,
            'plain' => new Plain,
            'default_resource' => $this->when(true, fn ($node = new UiNodeResource(null)) => $node),
        ];
    }
}
