<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class PrecedenceModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide one key where body inference and the docblock disagree, and one where TsCasts overrides both.
     *
     * @return array{count: string, label: string}
     */
    #[TsCasts(['label' => ['type' => 'LabelToken', 'import' => '@/types/label-token']])]
    public function provide(Model $model): array
    {
        return [
            'count' => $this->count(),
            'label' => 'primary',
        ];
    }

    /**
     * Native union so body inference cannot agree with the docblock's `string`.
     */
    private function count(): int|string
    {
        return '3';
    }
}
