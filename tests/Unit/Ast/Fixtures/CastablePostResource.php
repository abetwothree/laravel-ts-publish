<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastablePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Calls a method through two Castable-cast columns: one whose castUsing() spells its caster, one only a call names.
 *
 * @mixin CastablePost
 */
final class CastablePostResource extends JsonResource
{
    /**
     * One label per Castable-cast column.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title_label' => $this->title->toString(),
            'options_label' => $this->options->toString(),
        ];
    }
}
