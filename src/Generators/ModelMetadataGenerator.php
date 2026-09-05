<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\ProvidesCacheSignature;
use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelMetadataWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;
use Throwable;

/**
 * @extends CoreGenerator<Model>
 */
class ModelMetadataGenerator extends CoreGenerator implements ProvidesCacheSignature
{
    use RehydratesFromCache;

    public protected(set) ModelMetadataTransformer $transformer;

    /**
     * Generate the model metadata content.
     */
    #[Override]
    public function generate(): string
    {
        /** @var ModelMetadataTransformer $transformer */
        $transformer = resolve(Config::string(
            'ts-publish.model_metadata.transformer_class',
            ModelMetadataTransformer::class,
        ), ['findable' => $this->findable]);
        $this->transformer = $transformer;

        /** @var ModelMetadataWriter $writer */
        $writer = resolve(Config::string('ts-publish.model_metadata.writer_class', ModelMetadataWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    /**
     * Get the generated metadata filename without its TypeScript extension.
     */
    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }

    /**
     * Build a signature from the provider's runtime metadata payload.
     */
    #[Override]
    public static function cacheSignature(string $fqcn): string
    {
        $provider = resolve(ModelMetadataProviderResolver::class)->resolve();

        /** @var Model $model */
        $model = resolve($fqcn);
        $metadata = $provider->provide($model);

        try {
            return hash('xxh128', serialize([$provider::class, $metadata]));
        } catch (Throwable) {
            return hash('xxh128', $provider::class.random_bytes(16));
        }
    }
}
