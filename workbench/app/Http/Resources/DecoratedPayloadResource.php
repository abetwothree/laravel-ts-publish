<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Services\PayloadDecoratorService;

/**
 * Reads helpers that add keys to the array they are passed through each call path: a container instance, a static
 * call and the resource's own method.
 *
 * @mixin Post
 */
final class DecoratedPayloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'decorated' => resolve(PayloadDecoratorService::class)->decorate($this->options ?? []),
            'conditional' => resolve(PayloadDecoratorService::class)->decorateWhen($this->options ?? [], $this->id > 1),
            'static' => PayloadDecoratorService::decorateStatic($this->options ?? []),
            'helper' => $this->withSeen($this->options ?? []),
            'passed_through' => resolve(PayloadDecoratorService::class)->passThrough($this->options ?? []),
            'rebuilt' => resolve(PayloadDecoratorService::class)->rebuilt($this->options ?? []),
        ];
    }

    /**
     * Adds one key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withSeen(array $data): array
    {
        $data['seen'] = true;

        return $data;
    }
}
