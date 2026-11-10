<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * An image whose undocumented accessor maps its cast metadata with an arm no rule types (GH-154).
 */
final class TaggedImage extends Image
{
    protected $table = 'images';

    /**
     * One entry per metadata item: a value no rule types, or null.
     */
    protected function tagLists(): Attribute
    {
        return Attribute::get(fn () => array_map(fn ($r) => $r['ok'] ? cache('t') : null, $this->metadata ?? []));
    }
}
