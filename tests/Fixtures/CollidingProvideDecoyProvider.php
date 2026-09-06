<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Declared first, so the file's own AST offers this provide() as candidate zero; only MethodLocator's
 * end-line match sends the metadata analyzer past it to the provider below. Its keys are deliberately
 * typed against that one: `label` disagrees and `decoy` exists nowhere else.
 */
final class CollidingProvideDecoy
{
    /**
     * Provide a payload the real provider never returns.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['label' => 1, 'decoy' => true];
    }
}

final class CollidingProvideDecoyProvider implements ModelMetadataProvider
{
    /**
     * Provide the payload the metadata phase must infer, from the second provide() in this file.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['label' => $model->getTable(), 'real' => 1.5];
    }
}
