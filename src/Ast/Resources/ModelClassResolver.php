<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Resolves the Eloquent model backing a resource class.
 *
 * The one home for this order, so AstEngine's public entry and ResourceTransformer's pipeline
 * never disagree about which model a resource wraps.
 *
 * @internal
 */
final class ModelClassResolver
{
    /**
     * Resolve the backing model class.
     *
     * Precedence: the model ProxyDeclaration::model() reads, `#[TsResource(model:)]` first, then the naming convention,
     * then `#[UseResource]`. A declared class the guess is not, such as a value object, stops the guess.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    public function resolve(ReflectionClass $resource): ?string
    {
        // A collection wraps the items it collects, never one model, whatever its docblock or attribute names.
        if ($resource->isSubclassOf(ResourceCollection::class)) {
            return null;
        }

        $declared = ProxyDeclaration::model($resource);

        if ($declared !== null) {
            return $declared;
        }

        $guessed = $this->guessModelFromConvention($resource) ?? $this->guessModelFromUseResourceAttribute($resource);
        $held = ProxyDeclaration::of($resource)['classes'] ?? null;

        // An interface the guessed model implements, as `@mixin Arrayable` names, keeps it; a value object does not.
        return $held === null || ($guessed !== null && array_any($held, fn (string $class): bool => is_a($guessed, $class, true)))
            ? $guessed
            : null;
    }

    /**
     * Guess the backing model by reversing Laravel's resource naming convention.
     *
     * Given `App\Http\Resources\{Sub}\{Name}Resource`, tries `App\Models\{Sub}\{Name}`.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    private function guessModelFromConvention(ReflectionClass $resource): ?string
    {
        $resourceFqcn = $resource->getName();

        if (! Str::contains($resourceFqcn, '\\Http\\Resources\\')) {
            return null;
        }

        $beforeResources = Str::before($resourceFqcn, '\\Http\\Resources\\');
        $afterResources = Str::after($resourceFqcn, '\\Http\\Resources\\');

        $basename = class_basename($resourceFqcn);

        $relativeNamespace = Str::contains($afterResources, '\\')
            ? Str::before($afterResources, '\\'.$basename)
            : '';

        $prefix = $beforeResources.'\\Models\\'
            .(strlen($relativeNamespace) > 0 ? $relativeNamespace.'\\' : '');

        // Try without "Resource" suffix first (most common convention)
        $withoutSuffix = Str::endsWith($basename, 'Resource')
            ? Str::beforeLast($basename, 'Resource')
            : null;

        if ($withoutSuffix !== null && $withoutSuffix !== '') {
            $candidate = $prefix.$withoutSuffix;

            if (class_exists($candidate) && is_a($candidate, Model::class, true)) {
                return $candidate;
            }
        }

        // Try the class name as-is (e.g., App\Http\Resources\User → App\Models\User)
        $candidate = $prefix.$basename;

        if (class_exists($candidate) && is_a($candidate, Model::class, true)) {
            return $candidate;
        }

        return null;
    }

    /**
     * Scan collected models for a #[UseResource] attribute pointing to this resource.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    private function guessModelFromUseResourceAttribute(ReflectionClass $resource): ?string
    {
        // Laravel 11 doesn't have the UseResource attribute
        if (! class_exists('Illuminate\\Database\\Eloquent\\Attributes\\UseResource')) {
            return null; // @codeCoverageIgnore
        }

        /** @var ModelsCollector $collector */
        $collector = resolve(Config::string('ts-publish.models.collector_class', ModelsCollector::class));

        foreach ($collector->collect() as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            $attrs = $reflection->getAttributes('Illuminate\\Database\\Eloquent\\Attributes\\UseResource');

            if ($attrs !== [] && $attrs[0]->newInstance()->class === $resource->getName()) {
                return $modelClass;
            }
        }

        return null;
    }
}
