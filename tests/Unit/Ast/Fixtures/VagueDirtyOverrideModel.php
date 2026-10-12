<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model whose getDirty() and getCasts() overrides declare only vague arrays, which the receiver rules decline. */
final class VagueDirtyOverrideModel extends Model
{
    protected $table = 'posts';

    /** @return array<string, mixed> */
    public function getDirty(): array
    {
        return parent::getDirty();
    }

    public function getCasts(): array
    {
        return parent::getCasts();
    }
}
