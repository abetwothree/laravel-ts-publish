<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Tag;

/**
 * A JSON:API resource whose toLinks() sets a key the walk cannot name, and whose payload() returns a variable built
 * on its own resolve() beside a literal. Laravel 12.45 and later.
 *
 * @mixin Tag
 */
class PartlyReadLinksDocumentApiResource extends JsonApiResource
{
    /**
     * The tag's name.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return ['name' => $this->name];
    }

    /**
     * A self link, and one link the request names.
     *
     * @return array<string, string>
     */
    public function toLinks(Request $request): array
    {
        $links = ['self' => 'https://example.test/tags'];
        $links[$request->string('key')->toString()] = 'https://example.test';

        return $links;
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
