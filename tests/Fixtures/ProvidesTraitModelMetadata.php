<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;

trait ProvidesTraitModelMetadata
{
    /**
     * Provide metadata from a trait, so the using class's own file holds no provide() node.
     *
     * @return array{label: string}
     */
    #[TsCasts(['flag' => 'boolean'])]
    public function provide(Model $model): array
    {
        return [
            'label' => 'trait',
            'table' => $model->getTable(),
            'flag' => true,
        ];
    }
}
