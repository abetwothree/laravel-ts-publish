<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\ProvidesCacheSignature;
use AbeTwoThree\LaravelTsPublish\Cache\RouteCacheSignature;
use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<object>
 */
class RouteGenerator extends CoreGenerator implements ProvidesCacheSignature
{
    use RehydratesFromCache;

    public protected(set) RouteTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var RouteTransformer $transformer */
        $transformer = resolve(Config::string('ts-publish.routes.transformer_class', RouteTransformer::class), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var RouteWriter $writer */
        $writer = resolve(Config::string('ts-publish.routes.writer_class', RouteWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }

    /**
     * Signature of every route definition mapped to this controller. Route URIs,
     * methods, names, domains, action methods, and middleware live in route files
     * rather than the controller class, so they are folded into the cache
     * fingerprint here to bust the cache when a route changes.
     */
    #[Override]
    public static function cacheSignature(string $fqcn): string
    {
        return RouteCacheSignature::for($fqcn);
    }
}
