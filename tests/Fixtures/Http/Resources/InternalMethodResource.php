<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DocumentedFormatDate;
use ArrayIterator;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/** Reads methods of PHP's own classes, which declare only a tentative return type, off locals and a `@var`. */
final class InternalMethodResource extends JsonResource
{
    /** @var Post */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $native = new DateTimeImmutable('2020-01-02');
        /** @var DateTimeImmutable $declared */
        $declared = Opaque::value($native);
        $iterator = new ArrayIterator([1, 2]);
        $documented = new DocumentedFormatDate('2020-01-02');

        return [
            'native_year' => $native->format('Y'),
            'declared_year' => $declared->format('Y'),
            'iterator_count' => $iterator->count(),
            'documented_year' => $documented->format('Y'),
        ];
    }
}
