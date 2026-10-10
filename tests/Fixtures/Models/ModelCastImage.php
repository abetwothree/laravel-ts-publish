<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A test-only model whose #[TsCasts] retypes a morph relation that reads two same-named User models. */
#[TsCasts(['reviewable' => ['type' => 'User | null', 'import' => '@js/types/user']])]
class ModelCastImage extends Model
{
    protected $table = 'images';

    /** @return MorphTo<CrmUser|User, $this> */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
