<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use Illuminate\Database\Eloquent\Model;
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
    use ResolvesClassNames;

    /**
     * Resolve the backing model class.
     *
     * Precedence: #[TsResource(model:)], own @mixin/@extends, inherited @mixin/@extends, typed
     * $resource, naming convention, #[UseResource].
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    public function resolve(ReflectionClass $resource): ?string
    {
        $tsResourceAttrs = $resource->getAttributes(TsResource::class);

        if ($tsResourceAttrs) {
            $model = $tsResourceAttrs[0]->newInstance()->model;

            if ($model !== null && class_exists($model) && is_a($model, Model::class, true)) {
                return $model;
            }
        }

        $ownModel = $this->modelFromDocblock($resource);

        if ($ownModel !== null) {
            return $ownModel;
        }

        $inheritedModel = $this->modelFromAncestorDocblock($resource);

        if ($inheritedModel !== null) {
            return $inheritedModel;
        }

        $wrappedClass = $this->resolveClassOnProperty($resource);

        if ($wrappedClass !== null && class_exists($wrappedClass) && is_a($wrappedClass, Model::class, true)) {
            return $wrappedClass;
        }

        $guessed = $this->guessModelFromConvention($resource);

        if ($guessed !== null) {
            return $guessed;
        }

        return $this->guessModelFromUseResourceAttribute($resource);
    }

    /**
     * Read the model named by one class's own @mixin or @extends docblock tag.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    private function modelFromDocblock(ReflectionClass $resource): ?string
    {
        $docComment = $resource->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $resolved = null;

        // The "* " lookbehind keeps prose mentions of the tags mid-description from matching.
        if (preg_match('/(?<=\* )@mixin\s+([\w\\\\]+)/', $docComment, $matches)) {
            $resolved = $this->resolveDocblockType($matches[1], $resource);
        }

        if (preg_match('/(?<=\* )@extends\s+([\w\\\\]+)<([\w\\\\]+)>/', $docComment, $matches)) {
            $resolved = $this->resolveDocblockType($matches[2], $resource);
        }

        if ($resolved === null || ! class_exists($resolved) || ! is_a($resolved, Model::class, true)) {
            return null;
        }

        return $resolved;
    }

    /**
     * Find the nearest ancestor whose own docblock names a model.
     *
     * Runs for any resource lacking its own @mixin/@extends, not only a body-less one, so an
     * ancestor's model is picked up before falling back to the naming convention.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     * @return class-string<Model>|null
     */
    private function modelFromAncestorDocblock(ReflectionClass $resource): ?string
    {
        $parent = $resource->getParentClass();

        while ($parent !== false) {
            $model = $this->modelFromDocblock($parent);

            if ($model !== null) {
                return $model;
            }

            $parent = $parent->getParentClass();
        }

        return null;
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
