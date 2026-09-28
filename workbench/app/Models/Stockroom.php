<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Casts\MenuSettings;

/**
 * Accessors that each name one model and a `#[TsType(import:)]` class, for resources that filter this model with
 * only() and except().
 */
class Stockroom extends Model
{
    protected $table = 'warehouses';

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    protected function menuConfig(): Attribute
    {
        return Attribute::make(
            get: fn (): ?MenuSettings => null,
        );
    }

    /** @return Attribute<User|MenuSettings|null, never> */
    protected function contact(): Attribute
    {
        return Attribute::get(fn () => null);
    }

    protected function layout(): Attribute
    {
        return Attribute::get(fn () => ['manager' => $this->manager, 'settings' => $this->menu_config]);
    }
}
