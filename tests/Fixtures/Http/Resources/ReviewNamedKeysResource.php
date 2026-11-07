<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Builds shapes whose every key the engine names, through a helper spread, a public constant and a template key, under
 * `@return` members the bodies never send (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewNamedKeysResource extends JsonResource
{
    public const string KEY = 'count';

    /**
     * @return array{spreadHelper: list<array{by: CrmUser, id: int, extra: string}>,
     *     constKey: list<array{by: CrmUser, count: int, extra: string}>,
     *     tagged: list<array{by: CrmUser, extra: string}>, box: array{by: CrmUser, id: int, extra: string},
     *     keyed: array{by: CrmUser, count: int, extra: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'spreadHelper' => array_map(fn (ReviewRow $r) => [...$this->rowCommon($r), 'by' => $r->author], $this->resource->rows),
            'constKey' => array_map(fn ($r) => [self::KEY => $r->n, 'by' => $r->author], $this->resource->rows),
            'tagged' => array_map(fn ($r) => ['by' => $r->author, "{$r->title}_tag" => $r->n], $this->resource->rows),
            'box' => [...$this->boxCommon(), 'by' => $this->resource->author],
            'keyed' => [self::KEY => count($this->resource->rows), 'by' => $this->resource->author],
        ];
    }

    /**
     * A row's id, the keys every row shares.
     *
     * @return array{id: int}
     */
    private function rowCommon(ReviewRow $r): array
    {
        return ['id' => $r->n];
    }

    /**
     * The box's id.
     *
     * @return array{id: int}
     */
    private function boxCommon(): array
    {
        return ['id' => $this->resource->n];
    }
}
