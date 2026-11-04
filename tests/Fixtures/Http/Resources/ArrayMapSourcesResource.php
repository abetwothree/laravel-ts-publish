<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The arrays array_map() and collect()->map() read, typed lists, a Record and an opaque value, beside the callables
 * they pass; its keys map an `@var` local and two int-keyed arrays, which the resource re-indexes.
 *
 * @mixin Post
 */
final class ArrayMapSourcesResource extends JsonResource
{
    /** @var list<array{message: string}> */
    public array $records = [['message' => 'a'], ['message' => 'b']];

    /** @var array<string, int> */
    public array $scores = ['x' => 1, 'y' => 2];

    /** Doubles one count. */
    public static function twice(int $count): int
    {
        return $count * 2;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<array{message: string}> $rows */
        $rows = $this->opaque();

        return [
            'typed_local' => array_map(fn (array $row): string => $row['message'], $rows),
            'untyped_local' => array_map(fn ($row) => $row['message'], $rows),
            'gap_keyed' => array_map(fn (string $value): string => strtoupper($value), [1 => 'a', 3 => 'b']),
            'filtered' => array_map(fn (string $name): string => strtoupper($name), array_filter($this->names())),
        ];
    }

    /** @return list<array{message: string}> */
    public function rows(): array
    {
        return $this->records;
    }

    /** @return list<string> */
    public function names(): array
    {
        return ['ann', 'bob'];
    }

    /** @return list<int> */
    public function counts(): array
    {
        return [1, 2, 3];
    }

    /** A value nothing types. */
    public function opaque(): mixed
    {
        return unserialize('a:0:{}');
    }

    /** Upper-cases one name. */
    public function shout(string $name): string
    {
        return strtoupper($name);
    }

    /** Upper-cases one value nothing types. */
    public function shoutUntyped($name)
    {
        return strtoupper($name);
    }
}
