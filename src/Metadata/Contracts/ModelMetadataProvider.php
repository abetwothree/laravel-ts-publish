<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Metadata\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

interface ModelMetadataProvider
{
    /**
     * Provide runtime metadata for a model.
     *
     * Values may contain nested scalars, arrays, enums, Arrayable instances, or JsonSerializable instances.
     *
     * Prefer an array shape for static analysis; body inference only supplements generic array declarations.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array;
}
