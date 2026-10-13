<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose locals a `catch`, a by-reference argument, `unset()`, `static` or `global` rewrites,
 * beside closure parameters that shadow an outer local.
 *
 * @mixin Post
 */
class UnseenWritesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $caught = $this->title;

        try {
            throw new RuntimeException('x');
        } catch (RuntimeException $caught) {
        }

        /** @var string $declaredCaught */
        $declaredCaught = $this->title;

        try {
            throw new RuntimeException('x');
        } catch (RuntimeException $declaredCaught) {
        }

        $matches = [];
        preg_match('/(H)(e)/', (string) $this->title, $matches);

        $sorted = ['b' => 2, 'a' => 1];
        sort($sorted);

        $staticFilled = 'x';
        self::fillByReference($staticFilled);

        $instanceFilled = 'x';
        $this->fillInstance($instanceFilled);

        $gone = $this->title;
        unset($gone);

        $counter = 1;
        static $counter;

        $glob = 1;
        global $glob;

        $byRefLoop = ['a' => 1];

        foreach ($byRefLoop as &$value) {
            $value = 'z';
        }

        unset($value);

        $shadowed = $this->title;
        $doubled = collect([1, 2])->map(function (int $shadowed) {
            $shadowed = $shadowed * 2;

            return $shadowed;
        });

        $arrowShadowed = $this->title;
        $mapped = collect([1, 2])->map(fn (int $arrowShadowed) => $arrowShadowed * 2);

        $key = $this->title;
        $keyed = ['x' => 1];
        unset($keyed[$key]);

        $field = $this->title;
        $groups = ['x' => [3, 1]];
        sort($groups[$field]);

        $items = [1, 2];

        foreach (array_values($items) as &$item) {
            $item = 'z';
        }

        unset($item);

        $author = $this->author;
        unset($author->password);

        return [
            'caught' => $caught,
            'declared_caught' => $declaredCaught,
            'matches' => $matches,
            'sorted' => $sorted,
            'static_filled' => $staticFilled,
            'instance_filled' => $instanceFilled,
            'gone' => $gone ?? null,
            'counter' => $counter,
            'glob' => $glob,
            'by_ref_loop' => $byRefLoop,
            'shadowed' => $shadowed,
            'doubled' => $doubled,
            'arrow_shadowed' => $arrowShadowed,
            'mapped' => $mapped,
            'key' => $key,
            'field' => $field,
            'items' => $items,
            'author' => $author,
        ];
    }

    /** Overwrites its by-reference argument. */
    private static function fillByReference(mixed &$value): void
    {
        $value = 1;
    }

    /** Overwrites its by-reference argument. */
    private function fillInstance(mixed &$value): void
    {
        $value = 1;
    }
}
