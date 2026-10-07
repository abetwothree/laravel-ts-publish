<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray() over a model whose `$snakeAttributes` is off.
 *
 * @mixin RelationKeyCaseUser
 */
final class RelationKeyCaseResource extends JsonResource {}
