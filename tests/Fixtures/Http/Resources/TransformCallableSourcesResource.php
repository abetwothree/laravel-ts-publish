<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * transform() callbacks a constant, a method's literal return or a value no literal spells holds.
 *
 * @mixin Post
 */
class TransformCallableSourcesResource extends JsonResource
{
    /** A function's name, held by a constant. */
    private const string LENGTH = 'strlen';

    /** A function's name, held by a property a subclass redeclares. */
    public string $measure = 'strlen';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $map = ['a' => 'strlen'];
        $map['a'] = 'ucfirst';

        return [
            'class_const' => $this->transform($this->resource->title, self::LENGTH),
            'method_string' => $this->transform($this->resource->title, $this->lengthName()),
            'coalesce_method' => $this->transform($this->resource->title, $this->maybeName() ?? 'strlen'),
            'coalesce_fall_off' => $this->transform($this->resource->title, $this->nameOrNothing() ?? 'strlen'),
            'string_param' => $this->viaString('strlen'),
            'config_string' => $this->transform($this->resource->title, config('app.transform_callable', 'strlen')),
            'dim_rewritten' => $this->transform($this->resource->title, $map['a']),
            'own_property' => $this->transform($this->resource->title, $this->measure),
        ];
    }

    /** A function's name, as a literal every return spells. */
    private function lengthName(): string
    {
        return 'strlen';
    }

    /** A function's name, as a literal its one return spells under a nullable type. */
    private function maybeName(): ?string
    {
        return 'ucfirst';
    }

    /**
     * A function's name for a large id, else nothing, as the body ends without a return.
     *
     * @return string|null
     */
    private function nameOrNothing()
    {
        if ($this->resource->id > 100) {
            return 'ucfirst';
        }
    }

    /** The title transformed by a callable a parameter holds. */
    private function viaString(string $callback): mixed
    {
        return $this->transform($this->resource->title, $callback);
    }
}
