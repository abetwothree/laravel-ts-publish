<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Stringable;
use Workbench\App\Models\Post;

/**
 * Spread helpers whose own `@return` types what their bodies cannot.
 *
 * @mixin Post
 */
final class LiteralSpreadPostResource extends JsonResource
{
    /**
     * The keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->literals(),
            ...$this->names(),
        ];
    }

    /**
     * PHPStan literal types, and a union whose arms type alike.
     *
     * @return array{
     *     mode: 'draft'|'live',
     *     quoted: "draft"|"live",
     *     escaped: 'it\'s',
     *     level: 1|2|3,
     *     sign: -1|0|1,
     *     ratio: 1.5,
     *     maybe: 'draft'|'live'|null,
     *     mixed: 'a'|int,
     *     amount: int|float,
     * }
     */
    protected function literals(): array
    {
        return [
            'mode' => $this->opaque(),
            'quoted' => $this->opaque(),
            'escaped' => $this->opaque(),
            'level' => $this->opaque(),
            'sign' => $this->opaque(),
            'ratio' => $this->opaque(),
            'maybe' => $this->opaque(),
            'mixed' => $this->opaque(),
            'amount' => $this->opaque(),
        ];
    }

    /**
     * A name no class answers to, which the app declares for TypeScript, and a class the shape cannot import.
     *
     * @return array{custom: CustomObject, text: Stringable}
     */
    protected function names(): array
    {
        return [
            'custom' => $this->opaque(),
            'text' => $this->opaque(),
        ];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return json_decode('{}');
    }
}
