<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Tag;

/**
 * A JSON:API resource whose toAttributes() sets a key the walk cannot name, and whose payload() returns a variable
 * built on its own resolve() beside a literal. Laravel 12.45 and later.
 *
 * @mixin Tag
 */
class PartlyReadAttributesDocumentApiResource extends JsonApiResource
{
    /**
     * The tag's name, and one attribute the request names.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $attributes = ['name' => $this->name];
        $attributes[$request->string('key')->toString()] = 1;

        return $attributes;
    }

    /**
     * A short payload, or the response body.
     *
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['data' => 1, 'extra' => 1];
        }

        $data = $this->resolve($request);

        return $data;
    }
}
