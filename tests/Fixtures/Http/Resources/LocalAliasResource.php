<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LocalAliasSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Locals and helper parameters whose docblock names a `@phpstan-type`, an imported alias and a renamed import.
 *
 * @phpstan-type Pair array{a: int, b: string}
 *
 * @phpstan-import-type Flag from LocalAliasSource
 * @phpstan-import-type Flag from LocalAliasSource as Duo
 *
 * @mixin Post
 */
final class LocalAliasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Pair $local */
        $local = json_decode('{"a":1,"b":"x"}', true);
        /** @var ?Pair $nullableLocal */
        $nullableLocal = json_decode('{"a":1,"b":"x"}', true);
        /** @var list<Pair> $listLocal */
        $listLocal = json_decode('[{"a":1,"b":"x"}]', true);
        /** @var Flag $imported */
        $imported = json_decode('{"c":true,"d":[1]}', true);
        /** @var Duo $renamed */
        $renamed = json_decode('{"c":true,"d":[1]}', true);

        return [
            'local' => $local,
            'nullable_local' => $nullableLocal,
            'list_local' => $listLocal,
            'imported' => $imported,
            'renamed' => $renamed,
            'helper' => $this->untypedPair(json_decode('{"a":1,"b":"x"}', true)),
            'helper_array' => $this->arrayPair(json_decode('{"a":1,"b":"x"}', true)),
            'helper_renamed' => $this->untypedDuo(json_decode('{"c":true,"d":[1]}', true)),
        ];
    }

    /**
     * Wrap a pair its untyped parameter's `@param` names by alias.
     *
     * @param  Pair  $pair
     * @return array<string, mixed>
     */
    private function untypedPair($pair): array
    {
        return ['p' => $pair];
    }

    /**
     * Wrap a pair its `array` parameter's `@param` names by alias.
     *
     * @param  Pair  $pair
     * @return array<string, mixed>
     */
    private function arrayPair(array $pair): array
    {
        return ['p' => $pair];
    }

    /**
     * Wrap a flag its untyped parameter's `@param` names by its renamed import.
     *
     * @param  Duo  $duo
     * @return array<string, mixed>
     */
    private function untypedDuo($duo): array
    {
        return ['p' => $duo];
    }
}
