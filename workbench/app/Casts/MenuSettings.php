<?php

namespace Workbench\App\Casts;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

#[TsType(['type' => 'MenuSettingsType', 'import' => '@js/types/settings'])]
class MenuSettings implements CastsAttributes
{
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?array {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?string {
        if ($value === null) {
            return null;
        }

        return json_encode($value);
    }
}
