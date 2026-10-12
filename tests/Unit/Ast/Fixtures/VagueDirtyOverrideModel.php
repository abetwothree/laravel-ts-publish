<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model whose getDirty() override declares only a vague `array<string, mixed>`, which the receiver rules decline. */
final class VagueDirtyOverrideModel extends Model
{
    protected $table = 'posts';

    /** @return array<string, mixed> */
    public function getDirty(): array
    {
        return parent::getDirty();
    }
}
