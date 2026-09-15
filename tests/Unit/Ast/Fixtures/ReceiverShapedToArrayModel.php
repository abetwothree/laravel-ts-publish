<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model whose toArray() states a precise shape, so only the toArray() exclusion can decline it. */
final class ReceiverShapedToArrayModel extends Model
{
    /** @return array{id: int} */
    public function toArray(): array
    {
        return ['id' => 1];
    }

    /** @return array{id: int} */
    public function shape(): array
    {
        return ['id' => 1];
    }
}
