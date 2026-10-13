<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Casts\StatusOrStatusesCast;
use Workbench\App\Enums\Status;

/**
 * A docblock union naming a class or an enum both inside a container and bare publishes both arms, in either order:
 * an `@property` tag, a cast's get() `@return` and an accessor's `Attribute<…>`. The enum column and accessor keep
 * the model's Resource interface, printed as a list of the AsEnum wrap or the wrap alone.
 *
 * @property Collection<int, User>|User|null $tags
 * @property User[]|User $related_users
 * @property array<string, User>|User $owner_snapshot
 */
class DocblockUnionFixture extends Model
{
    protected $table = 'property_docblock_fixtures';

    protected $appends = ['grades'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'related_users' => 'array',
            'meta_info' => StatusOrStatusesCast::class,
            'owner_snapshot' => 'array',
        ];
    }

    /**
     * The grades, a list of statuses or one status.
     *
     * @return Attribute<Status[]|Status, never>
     */
    protected function grades(): Attribute
    {
        return Attribute::get(fn (): array => []);
    }
}
